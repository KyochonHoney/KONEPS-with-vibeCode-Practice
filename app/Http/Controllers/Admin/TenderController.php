<?php

// [BEGIN nara:admin_tender_controller]
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\TenderMention;
use App\Services\TenderCollectorService;
use App\Services\NaraApiService;
use App\Services\ProposalFileCrawlerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * 관리자용 입찰공고 관리 컨트롤러
 * 
 * @package App\Http\Controllers\Admin
 */
class TenderController extends Controller
{
    private TenderCollectorService $collector;
    private NaraApiService $naraApi;

    public function __construct(TenderCollectorService $collector, NaraApiService $naraApi)
    {
        $this->collector = $collector;
        $this->naraApi = $naraApi;
    }

    /**
     * 입찰공고 관리 메인 페이지
     * 
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Tender::with('category');

        // 검색 필터 적용
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('agency', 'like', "%{$search}%")
                  ->orWhere('tender_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // 업종코드 패턴 필터링
        if ($request->filled('industry_pattern')) {
            $pattern = $request->get('industry_pattern');
            if ($pattern === 'EMPTY') {
                // 빈 업종상세코드 또는 기타 카테고리 필터링
                $query->where(function($q) {
                    $q->where('pub_prcrmnt_clsfc_no', '')
                      ->orWhereNull('pub_prcrmnt_clsfc_no')
                      ->orWhere('category_id', 4); // 기타 카테고리
                });
            } else {
                $query->where('pub_prcrmnt_clsfc_no', 'like', $pattern . '%');
            }
        }

        // 날짜 범위 필터 (date_type + date_start/date_end)
        if ($request->filled('date_type') && $request->filled('date_start') && $request->filled('date_end')) {
            $dateType = $request->get('date_type');
            $dateStart = $request->get('date_start');
            $dateEnd = $request->get('date_end');

            if ($dateType === 'deadline') {
                // 마감일 기준 (bid_clse_dt)
                $query->whereDate('bid_clse_dt', '>=', $dateStart)
                      ->whereDate('bid_clse_dt', '<=', $dateEnd);
            } elseif ($dateType === 'registered') {
                // 등록일시 기준 (rgst_dt)
                $query->whereDate('rgst_dt', '>=', $dateStart)
                      ->whereDate('rgst_dt', '<=', $dateEnd);
            }
        }

        // 즐겨찾기 필터
        if ($request->filled('favorites_only') && $request->get('favorites_only') === '1') {
            $query->where('is_favorite', true);
        }

        // 메모 있는 공고만 필터
        if ($request->filled('has_mention') && $request->get('has_mention') === '1') {
            $query->whereHas('mentions', function($q) {
                $q->where('user_id', auth()->id())
                  ->whereNotNull('mention')
                  ->where('mention', '!=', '');
            });
        }

        // 비적합 공고만 필터
        if ($request->filled('unsuitable_only') && $request->get('unsuitable_only') === '1') {
            $query->where('is_unsuitable', true);
        }

        $tenders = $query->latest('collected_at')
                         ->paginate(20)
                         ->withQueryString();

        // 통계 데이터
        $stats = $this->collector->getCollectionStats();

        // 업종코드 패턴별 통계
        $industryStats = $this->getIndustryPatternStats();

        return view('admin.tenders.index', compact('tenders', 'stats', 'industryStats'));
    }

    /**
     * 업종코드 패턴별 통계 조회
     * 
     * @return array
     */
    private function getIndustryPatternStats(): array
    {
        $patterns = [
            '81112002' => '데이터처리/빅데이터분석서비스',
            '81112299' => '소프트웨어유지및지원서비스', 
            '81111811' => '운영위탁서비스',
            '81111899' => '정보시스템유지관리서비스',
            '81112199' => '인터넷지원개발서비스',
            '81111598' => '패키지소프트웨어/정보시스템개발서비스',
            '81111599' => '시스템소프트웨어개발서비스',
            '81151699' => '공간정보DB구축서비스'
        ];

        $stats = [];
        foreach ($patterns as $pattern => $name) {
            $count = Tender::where('pub_prcrmnt_clsfc_no', 'like', $pattern . '%')->count();
            $stats[] = [
                'pattern' => $pattern,
                'name' => $name,
                'count' => $count
            ];
        }

        // 기타 카테고리 (빈 업종상세코드) 추가
        $emptyCount = Tender::where(function($q) {
            $q->where('pub_prcrmnt_clsfc_no', '')
              ->orWhereNull('pub_prcrmnt_clsfc_no')
              ->orWhere('category_id', 4);
        })->count();
        
        $stats[] = [
            'pattern' => 'EMPTY',
            'name' => '기타 (업종상세코드 없음)',
            'count' => $emptyCount
        ];

        return $stats;
    }

    /**
     * 입찰공고 상세 정보
     *
     * @param Tender $tender
     * @return View
     */
    public function show(Tender $tender): View
    {
        $tender->load('category');

        // 현재 사용자의 멘션 가져오기
        $userMention = TenderMention::where('tender_id', $tender->id)
            ->where('user_id', auth()->id())
            ->first();

        // 제안요청정보 파일의 상주 검사 결과 미리 계산
        // 모든 상태의 파일을 표시하되, completed 파일만 상주 검사 수행
        $proposalFiles = $tender->attachments()->where('type', 'proposal')->get();
        foreach ($proposalFiles as $file) {
            if ($file->download_status === 'completed') {
                // 다운로드 완료된 파일만 상주 검사
                $file->sangju_status = $this->checkFileSangju($file);
            } else {
                // pending/failed 파일은 검사 안 함
                $file->sangju_status = [
                    'checked' => false,
                    'has_sangju' => false,
                    'occurrences' => 0,
                    'error' => '다운로드 ' . ($file->download_status === 'pending' ? '대기중' : '실패')
                ];
            }
        }

        return view('admin.tenders.show', compact('tender', 'userMention', 'proposalFiles'));
    }

    /**
     * 개별 파일의 상주 키워드 검사 (페이지 로드용)
     *
     * @param \App\Models\Attachment $attachment
     * @return array
     */
    private function checkFileSangju($attachment): array
    {
        try {
            // 파일 경로 확인
            $fullPath = storage_path('app/' . $attachment->local_path);
            if (!file_exists($fullPath)) {
                $fullPath = storage_path('app/private/' . $attachment->local_path);
            }

            if (!file_exists($fullPath)) {
                return ['checked' => false, 'has_sangju' => false, 'occurrences' => 0, 'error' => '파일 없음'];
            }

            // 확장자 확인
            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if (empty($extension) || $extension === pathinfo($fullPath, PATHINFO_BASENAME)) {
                $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
            }

            // 지원하는 포맷인지 확인
            if (!in_array($extension, ['hwp', 'hwpx', 'pdf', 'doc', 'docx', 'txt'])) {
                return ['checked' => false, 'has_sangju' => false, 'occurrences' => 0, 'error' => '지원하지 않는 포맷'];
            }

            // 텍스트 추출 (여러 방법 시도)
            $extractedText = null;
            if ($extension === 'hwp' || $extension === 'hwpx') {
                if ($extension === 'hwp') {
                    // 방법 1: hwp5.py 스크립트
                    $scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
                    $command = "timeout 10 python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                    $extractedText = shell_exec($command);

                    // 방법 2: pyhwp (backup)
                    if (empty($extractedText) || strlen($extractedText) < 100) {
                        $scriptPath2 = base_path('scripts/extract_hwp_text_pyhwp.py');
                        $command2 = "timeout 10 python3 " . escapeshellarg($scriptPath2) . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText2 = shell_exec($command2);
                        if (!empty($extractedText2) && strlen($extractedText2) > strlen($extractedText)) {
                            $extractedText = $extractedText2;
                        }
                    }

                    // 방법 3: hwp5txt 명령어 (추가 backup)
                    if (empty($extractedText) || strlen($extractedText) < 100) {
                        $command3 = "timeout 10 " . storage_path('../storage/hwp_venv/bin/hwp5txt') . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText3 = shell_exec($command3);
                        if (!empty($extractedText3) && strlen($extractedText3) > strlen($extractedText)) {
                            $extractedText = $extractedText3;
                        }
                    }
                } else {
                    $scriptPath = base_path('scripts/extract_hwpx_text.py');
                    $command = "timeout 10 python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                    $extractedText = shell_exec($command);
                }
            } elseif ($extension === 'pdf') {
                $command = "timeout 10 pdftotext " . escapeshellarg($fullPath) . " - 2>&1";
                $extractedText = shell_exec($command);
            } elseif (in_array($extension, ['doc', 'docx'])) {
                if ($extension === 'doc') {
                    $command = "timeout 10 antiword " . escapeshellarg($fullPath) . " 2>&1";
                } else {
                    $command = "timeout 10 docx2txt " . escapeshellarg($fullPath) . " - 2>&1";
                }
                $extractedText = shell_exec($command);
            } elseif ($extension === 'txt') {
                $extractedText = file_get_contents($fullPath);
            }

            // 상주 검사
            if ($extractedText && mb_stripos($extractedText, '상주') !== false) {
                $occurrences = substr_count(mb_strtolower($extractedText), '상주');
                return ['checked' => true, 'has_sangju' => true, 'occurrences' => $occurrences];
            }

            return ['checked' => true, 'has_sangju' => false, 'occurrences' => 0];

        } catch (\Exception $e) {
            return ['checked' => false, 'has_sangju' => false, 'occurrences' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * 데이터 수집 페이지
     * 
     * @return View
     */
    public function collect(): View
    {
        $stats = $this->collector->getCollectionStats();
        
        return view('admin.tenders.collect', compact('stats'));
    }

    /**
     * 수동 데이터 수집 실행
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function executeCollection(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:today,recent,custom,advanced',
            'start_date' => 'required_if:type,custom|date|date_format:Y-m-d',
            'end_date' => 'required_if:type,custom|date|date_format:Y-m-d|after_or_equal:start_date',
            'filter_start_date' => 'required_if:type,advanced|date|date_format:Y-m-d',
            'filter_end_date' => 'required_if:type,advanced|date|date_format:Y-m-d|after_or_equal:filter_start_date',
            'regions' => 'sometimes|array',
            'industry_codes' => 'sometimes|array',
            'product_codes' => 'sometimes|array',
        ]);

        try {
            $stats = match($request->get('type')) {
                'today' => $this->collector->collectTodayTenders(),
                'recent' => $this->collector->collectRecentTenders(),
                'custom' => $this->collector->collectTendersByDateRange(
                    $request->get('start_date'),
                    $request->get('end_date'),
                    [] // 빈 필터 배열
                ),
                'advanced' => $this->collector->collectTendersWithAdvancedFilters(
                    $request->get('filter_start_date'),
                    $request->get('filter_end_date')
                ),
            };

            return response()->json([
                'success' => true,
                'message' => '데이터 수집이 완료되었습니다.',
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('수동 데이터 수집 오류', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => '데이터 수집 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API 연결 테스트
     * 
     * @return JsonResponse
     */
    public function testApi(): JsonResponse
    {
        try {
            $isConnected = $this->naraApi->testConnection();

            if ($isConnected) {
                return response()->json([
                    'success' => true,
                    'message' => '나라장터 API 연결이 정상입니다.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'API 연결에 실패했습니다. 서비스 키를 확인해주세요.'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API 테스트 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 입찰공고 데이터 삭제 (관련 데이터 포함)
     * 
     * @param Tender $tender
     * @return JsonResponse
     */
    public function destroy(Tender $tender): JsonResponse
    {
        try {
            $tenderNo = $tender->tender_no;
            
            // 관련 데이터와 함께 삭제
            $this->deleteTenderWithRelatedData($tender);

            return response()->json([
                'success' => true,
                'message' => "공고 {$tenderNo}가 삭제되었습니다."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '삭제 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 입찰공고 상태 업데이트
     * 
     * @param Request $request
     * @param Tender $tender
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Tender $tender): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,closed,cancelled'
        ]);

        try {
            $tender->update([
                'status' => $request->get('status')
            ]);

            return response()->json([
                'success' => true,
                'message' => '상태가 업데이트되었습니다.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '상태 업데이트 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 대시보드용 통계 데이터
     * 
     * @return JsonResponse
     */
    public function dashboardStats(): JsonResponse
    {
        try {
            $stats = $this->collector->getCollectionStats();
            
            // 추가 통계 정보
            $recentTrends = Tender::selectRaw('DATE(collected_at) as date, COUNT(*) as count')
                ->where('collected_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $statusDistribution = Tender::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'basic_stats' => $stats,
                    'recent_trends' => $recentTrends,
                    'status_distribution' => $statusDistribution
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '통계 데이터 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 일괄 상태 업데이트
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'tender_ids' => 'required|array|min:1',
            'tender_ids.*' => 'exists:tenders,id',
            'status' => 'required|in:active,closed,cancelled'
        ]);

        try {
            $updatedCount = Tender::whereIn('id', $request->get('tender_ids'))
                ->update(['status' => $request->get('status')]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount}건의 공고 상태가 업데이트되었습니다."
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '일괄 업데이트 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 마감된 공고 정리 페이지
     * 
     * @return View
     */
    public function cleanup(): View
    {
        // 마감된 공고 통계
        $stats = $this->getCleanupStats();
        
        return view('admin.tenders.cleanup', compact('stats'));
    }
    
    /**
     * 마감된 공고 자동 정리 실행 (수동)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function executeCleanup(Request $request): JsonResponse
    {
        $request->validate([
            'expired_days' => 'required|integer|min:1|max:365',
            'dry_run' => 'boolean'
        ]);

        try {
            $expiredDays = $request->get('expired_days', 7);
            $dryRun = $request->get('dry_run', false);
            
            $result = $this->collector->cleanupExpiredTenders($expiredDays, $dryRun);
            
            $action = $dryRun ? '검사' : '정리';
            $message = "마감된 공고 {$action} 완료: {$result['deleted_count']}개 처리";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '마감 공고 정리 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 마감된 공고 통계 조회
     * 
     * @return array
     */
    private function getCleanupStats(): array
    {
        $now = Carbon::now();
        
        // 다양한 기간별 마감 공고 수 조회
        $stats = [
            'expired_1_day' => $this->getExpiredCount(1),
            'expired_7_days' => $this->getExpiredCount(7),
            'expired_30_days' => $this->getExpiredCount(30),
            'expired_90_days' => $this->getExpiredCount(90),
            'total_active' => Tender::where('status', 'active')->count(),
            'total_all' => Tender::count(),
        ];
        
        // 최근 마감된 공고 목록 (미리보기용)
        $recentExpired = Tender::where(function($query) use ($now) {
            $expiredDate = $now->copy()->subDays(7)->format('Y-m-d');
            $query->where('end_date', '<=', $expiredDate)
                  ->whereNotNull('end_date');
        })
        ->orWhere(function($query) use ($now) {
            $expiredDate = $now->copy()->subDays(7)->format('Y-m-d');
            $query->whereRaw("DATE(bid_clse_dt) <= ?", [$expiredDate])
                  ->whereNotNull('bid_clse_dt');
        })
        ->orderBy('end_date', 'desc')
        ->limit(10)
        ->get();
        
        $stats['recent_expired'] = $recentExpired;
        
        return $stats;
    }
    
    /**
     * 특정 기간 이후 마감된 공고 수 조회
     * 
     * @param int $days
     * @return int
     */
    private function getExpiredCount(int $days): int
    {
        $expiredDate = Carbon::now()->subDays($days)->format('Y-m-d');
        
        return Tender::where(function($query) use ($expiredDate) {
            $query->where('end_date', '<=', $expiredDate)
                  ->whereNotNull('end_date');
        })
        ->orWhere(function($query) use ($expiredDate) {
            $query->whereRaw("DATE(bid_clse_dt) <= ?", [$expiredDate])
                  ->whereNotNull('bid_clse_dt');
        })
        ->orWhere(function($query) use ($expiredDate) {
            $query->whereRaw("DATE(openg_dt) < ?", [$expiredDate])
                  ->whereNotNull('openg_dt');
        })
        ->count();
    }
    
    /**
     * 즐겨찾기 토글
     *
     * @param Tender $tender
     * @return JsonResponse
     */
    public function toggleFavorite(Tender $tender): JsonResponse
    {
        try {
            $tender->is_favorite = !$tender->is_favorite;
            $tender->save();

            $message = $tender->is_favorite
                ? '즐겨찾기에 추가되었습니다.'
                : '즐겨찾기에서 제거되었습니다.';

            return response()->json([
                'success' => true,
                'is_favorite' => $tender->is_favorite,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '즐겨찾기 토글 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 비적합 상태 토글
     *
     * @param Tender $tender
     * @return JsonResponse
     */
    public function toggleUnsuitable(Tender $tender): JsonResponse
    {
        try {
            $tender->is_unsuitable = !$tender->is_unsuitable;
            $tender->save();

            $message = $tender->is_unsuitable
                ? '비적합으로 표시되었습니다.'
                : '적합으로 표시되었습니다.';

            return response()->json([
                'success' => true,
                'is_unsuitable' => $tender->is_unsuitable,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '비적합 표시 토글 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * "상주" 단어 검사 (모든 첨부파일 다운로드 및 파싱)
     *
     * @param Tender $tender
     * @return JsonResponse
     */
    public function checkSangju(Tender $tender): JsonResponse
    {
        try {
            $hasSangju = false;
            $foundInFiles = [];
            $totalFiles = 0;
            $checkedFiles = 0;

            // 1. 제안요청정보 파일 검사 (Attachment 모델 - proposal_files)
            $proposalAttachments = $tender->attachments()
                ->where('type', 'proposal')
                ->where('download_status', 'completed')
                ->get();

            foreach ($proposalAttachments as $attachment) {
                $totalFiles++;
                $filePath = $attachment->local_path;

                // 파일 경로 확인 (두 경로 모두 체크)
                $fullPath = storage_path('app/' . $filePath);
                if (!file_exists($fullPath)) {
                    $fullPath = storage_path('app/private/' . $filePath);
                }

                if (!file_exists($fullPath)) {
                    continue; // 파일이 없으면 건너뛰기
                }

                // 파일 확장자 확인
                $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

                // 확장자가 없는 경우 (예: 'download') file_name에서 확장자 가져오기
                if (empty($extension) || $extension === pathinfo($fullPath, PATHINFO_BASENAME)) {
                    $extension = strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION));
                }

                // 텍스트 추출 가능한 파일만 처리 (hwp, hwpx, pdf, doc, docx, txt)
                if (!in_array($extension, ['hwp', 'hwpx', 'pdf', 'doc', 'docx', 'txt'])) {
                    continue;
                }

                $checkedFiles++;

                // 파일 형식별 텍스트 추출 (여러 방법 시도)
                $extractedText = null;

                if ($extension === 'hwp' || $extension === 'hwpx') {
                    if ($extension === 'hwp') {
                        // 방법 1: hwp5.py 스크립트
                        $scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
                        $command = "timeout 30 python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText = shell_exec($command);

                        // 방법 2: pyhwp (backup)
                        if (empty($extractedText) || strlen($extractedText) < 100) {
                            $scriptPath2 = base_path('scripts/extract_hwp_text_pyhwp.py');
                            $command2 = "timeout 30 python3 " . escapeshellarg($scriptPath2) . " " . escapeshellarg($fullPath) . " 2>&1";
                            $extractedText2 = shell_exec($command2);
                            if (!empty($extractedText2) && strlen($extractedText2) > strlen($extractedText)) {
                                $extractedText = $extractedText2;
                            }
                        }

                        // 방법 3: hwp5txt 명령어 (추가 backup)
                        if (empty($extractedText) || strlen($extractedText) < 100) {
                            $command3 = "timeout 30 " . storage_path('../storage/hwp_venv/bin/hwp5txt') . " " . escapeshellarg($fullPath) . " 2>&1";
                            $extractedText3 = shell_exec($command3);
                            if (!empty($extractedText3) && strlen($extractedText3) > strlen($extractedText)) {
                                $extractedText = $extractedText3;
                            }
                        }
                    } else {
                        // HWPX 파일 - ZIP/XML 파싱 스크립트 사용
                        $scriptPath = base_path('scripts/extract_hwpx_text.py');
                        $command = "timeout 30 python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
                        $extractedText = shell_exec($command);
                    }
                } elseif ($extension === 'pdf') {
                    // PDF 파일 - pdftotext 사용
                    $command = "pdftotext " . escapeshellarg($fullPath) . " - 2>&1";
                    $extractedText = shell_exec($command);
                } elseif (in_array($extension, ['doc', 'docx'])) {
                    // DOC/DOCX 파일
                    if ($extension === 'doc') {
                        $command = "antiword " . escapeshellarg($fullPath) . " 2>&1";
                    } else {
                        $command = "docx2txt " . escapeshellarg($fullPath) . " - 2>&1";
                    }
                    $extractedText = shell_exec($command);
                } elseif ($extension === 'txt') {
                    // 텍스트 파일 - 직접 읽기
                    $extractedText = file_get_contents($fullPath);
                }

                // "상주" 단어 검색 (대소문자 구분 없음)
                if ($extractedText && mb_stripos($extractedText, '상주') !== false) {
                    $hasSangju = true;
                    $foundInFiles[] = [
                        'file_name' => ($attachment->file_name ?: $attachment->original_name),
                        'file_type' => '제안요청정보',
                        'extension' => $extension,
                        'occurrences' => substr_count(mb_strtolower($extractedText), '상주'),
                        'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                        'file_path' => $attachment->local_path
                    ];
                }
            }

            // 2. 나라장터 첨부파일 다운로드 및 검사
            $attachmentFiles = $tender->attachment_files;

            if (is_array($attachmentFiles) && !empty($attachmentFiles)) {
                foreach ($attachmentFiles as $fileInfo) {
                    $totalFiles++;

                    // 파일명과 다운로드 URL 확인
                    $fileName = $fileInfo['name'] ?? '첨부파일';
                    $downloadUrl = $fileInfo['url'] ?? null;

                    if (!$downloadUrl) {
                        continue;
                    }

                    // 파일 확장자 확인
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    // 텍스트 추출 가능한 파일만 처리 (hwp, hwpx, pdf, doc, docx, txt)
                    if (!in_array($extension, ['hwp', 'hwpx', 'pdf', 'doc', 'docx', 'txt'])) {
                        continue;
                    }

                    try {
                        // 임시 파일 경로
                        $tempDir = storage_path('app/temp_sangju_check/' . $tender->id);
                        if (!file_exists($tempDir)) {
                            mkdir($tempDir, 0755, true);
                        }

                        $tempFilePath = $tempDir . '/' . $fileName;

                        // 파일 다운로드 (G2B 서버는 브라우저 헤더 필요)
                        $response = \Illuminate\Support\Facades\Http::withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                            'Referer' => 'https://www.g2b.go.kr/',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                            'Accept-Language' => 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
                        ])
                        ->timeout(30)
                        ->get($downloadUrl);

                        if (!$response->successful()) {
                            \Log::warning('G2B 첨부파일 다운로드 실패', [
                                'tender_id' => $tender->id,
                                'file_name' => $fileName,
                                'status' => $response->status(),
                                'url' => $downloadUrl
                            ]);
                            continue; // 다운로드 실패 시 건너뛰기
                        }

                        file_put_contents($tempFilePath, $response->body());
                        $checkedFiles++;

                        // 파일 형식별 텍스트 추출
                        $extractedText = null;

                        if ($extension === 'hwp' || $extension === 'hwpx') {
                            if ($extension === 'hwp') {
                                // HWP 파일 - hwp5txt 기반 스크립트 사용
                                $scriptPath = base_path('scripts/extract_hwp_text_hwp5.py');
                                $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFilePath) . " 2>&1";
                                $extractedText = shell_exec($command);
                            } else {
                                // HWPX 파일 - ZIP/XML 파싱 스크립트 사용
                                $scriptPath = base_path('scripts/extract_hwpx_text.py');
                                $command = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFilePath) . " 2>&1";
                                $extractedText = shell_exec($command);
                            }
                        } elseif ($extension === 'pdf') {
                            // PDF 파일 - pdftotext 사용
                            $command = "pdftotext " . escapeshellarg($tempFilePath) . " - 2>&1";
                            $extractedText = shell_exec($command);
                        } elseif (in_array($extension, ['doc', 'docx'])) {
                            // DOC/DOCX 파일 - antiword 또는 catdoc 사용
                            if ($extension === 'doc') {
                                $command = "antiword " . escapeshellarg($tempFilePath) . " 2>&1";
                            } else {
                                $command = "docx2txt " . escapeshellarg($tempFilePath) . " - 2>&1";
                            }
                            $extractedText = shell_exec($command);
                        } elseif ($extension === 'txt') {
                            // 텍스트 파일 - 직접 읽기
                            $extractedText = file_get_contents($tempFilePath);
                        }

                        // "상주" 단어 검색
                        if ($extractedText && mb_stripos($extractedText, '상주') !== false) {
                            $hasSangju = true;
                            $foundInFiles[] = [
                                'file_name' => $fileName,
                                'file_type' => '첨부파일',
                                'extension' => $extension,
                                'occurrences' => substr_count(mb_strtolower($extractedText), '상주'),
                                'file_size' => file_exists($tempFilePath) ? filesize($tempFilePath) : 0,
                                'file_path' => 'temp/' . $fileName
                            ];
                        }

                        // 임시 파일 삭제
                        @unlink($tempFilePath);

                    } catch (\Exception $e) {
                        // 개별 파일 처리 오류는 무시하고 계속 진행
                        continue;
                    }
                }

                // 임시 디렉토리 삭제
                $tempDir = storage_path('app/temp_sangju_check/' . $tender->id);
                if (file_exists($tempDir)) {
                    @rmdir($tempDir);
                }
            }

            // 검사할 파일이 없는 경우
            if ($totalFiles === 0) {
                return response()->json([
                    'success' => false,
                    'message' => '검사할 첨부파일이 없습니다.'
                ]);
            }

            // 총 발견 횟수 계산
            $totalOccurrences = 0;
            foreach ($foundInFiles as $fileInfo) {
                $totalOccurrences += $fileInfo['occurrences'];
            }

            // "상주"가 발견되면 비적합으로 자동 표시
            if ($hasSangju) {
                $tender->is_unsuitable = true;
                $tender->save();

                return response()->json([
                    'success' => true,
                    'has_sangju' => true,
                    'total_files' => $totalFiles,
                    'checked_files' => $checkedFiles,
                    'found_in_files' => $foundInFiles,
                    'total_occurrences' => $totalOccurrences,
                    'message' => '"상주" 키워드가 ' . count($foundInFiles) . '개 파일에서 총 ' . $totalOccurrences . '회 발견되었습니다. (검사: ' . $checkedFiles . '/' . $totalFiles . '개 파일)'
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'has_sangju' => false,
                    'total_files' => $totalFiles,
                    'checked_files' => $checkedFiles,
                    'found_in_files' => [],
                    'total_occurrences' => 0,
                    'message' => '모든 첨부파일에서 "상주" 단어를 찾을 수 없습니다. (검사: ' . $checkedFiles . '/' . $totalFiles . '개 파일) 이 공고는 적합합니다.'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '"상주" 검사 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 멘션(메모) 저장 또는 업데이트
     *
     * @param Request $request
     * @param Tender $tender
     * @return JsonResponse
     */
    public function storeMention(Request $request, Tender $tender): JsonResponse
    {
        try {
            $validated = $request->validate([
                'mention' => 'nullable|string|max:5000',
            ]);

            $mention = TenderMention::updateOrCreate(
                [
                    'tender_id' => $tender->id,
                    'user_id' => auth()->id(),
                ],
                [
                    'mention' => $validated['mention'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'mention' => $mention->mention,
                'message' => '메모가 저장되었습니다.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '메모 저장 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 멘션(메모) 삭제
     *
     * @param Tender $tender
     * @return JsonResponse
     */
    public function destroyMention(Tender $tender): JsonResponse
    {
        try {
            $deleted = TenderMention::where('tender_id', $tender->id)
                ->where('user_id', auth()->id())
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => '메모가 삭제되었습니다.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => '삭제할 메모가 없습니다.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '메모 삭제 중 오류가 발생했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 제안요청정보 파일 크롤링
     *
     * @param Tender $tender
     * @return JsonResponse
     */
    public function crawlProposalFiles(Tender $tender): JsonResponse
    {
        try {
            $crawler = new ProposalFileCrawlerService();
            $result = $crawler->crawlProposalFiles($tender);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '크롤링 중 오류 발생: ' . $e->getMessage(),
                'files_found' => 0,
                'files_downloaded' => 0,
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * 공고와 관련된 모든 데이터 삭제 (컨트롤러용)
     *
     * @param Tender $tender
     * @return void
     */
    private function deleteTenderWithRelatedData(Tender $tender): void
    {
        \DB::transaction(function () use ($tender) {
            // 1. 분석 결과 삭제
            $tender->analyses()->delete();

            // 2. 제안서 삭제
            $tender->proposals()->delete();

            // 3. 첨부파일 삭제
            $attachments = $tender->attachments()->get();
            foreach ($attachments as $attachment) {
                // 실제 파일 삭제
                if ($attachment->file_path && file_exists(storage_path('app/' . $attachment->file_path))) {
                    unlink(storage_path('app/' . $attachment->file_path));
                }
                $attachment->delete();
            }

            // 4. 공고 자체 삭제
            $tender->delete();
        });
    }

    /**
     * 나라장터 첨부파일 프록시 다운로드
     *
     * 나라장터 파일은 직접 접근이 불가능하므로 서버에서 대신 다운로드하여 사용자에게 전달
     *
     * @param Tender $tender
     * @param int $seq
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadAttachment(Tender $tender, int $seq)
    {
        try {
            // 첨부파일 정보 조회
            $attachmentFiles = $tender->attachment_files;
            $file = collect($attachmentFiles)->firstWhere('seq', $seq);

            if (!$file) {
                abort(404, '첨부파일을 찾을 수 없습니다.');
            }

            $url = $file['url'];
            $filename = $file['name'];

            // HTTP 클라이언트로 나라장터에서 파일 다운로드
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Referer' => 'https://www.g2b.go.kr/',
            ])->timeout(60)->get($url);

            if (!$response->successful()) {
                abort(500, '나라장터 파일 다운로드에 실패했습니다. (HTTP ' . $response->status() . ')');
            }

            // 파일 다운로드 응답 반환
            return response()->streamDownload(function() use ($response) {
                echo $response->body();
            }, $filename, [
                'Content-Type' => $response->header('Content-Type') ?? 'application/octet-stream',
                'Content-Length' => strlen($response->body()),
            ]);

        } catch (\Exception $e) {
            \Log::error('첨부파일 다운로드 실패: ' . $e->getMessage(), [
                'tender_id' => $tender->id,
                'seq' => $seq,
            ]);

            abort(500, '파일 다운로드 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }
}
// [END nara:admin_tender_controller]
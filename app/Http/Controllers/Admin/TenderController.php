<?php

// [BEGIN nara:admin_tender_controller]
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Services\TenderCollectorService;
use App\Services\NaraApiService;
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
            $query->where('pub_prcrmnt_clsfc_no', 'like', $pattern . '%');
        }

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->get('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('end_date', '<=', $request->get('end_date'));
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
        
        return view('admin.tenders.show', compact('tender'));
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
     * 입찰공고 데이터 삭제
     * 
     * @param Tender $tender
     * @return JsonResponse
     */
    public function destroy(Tender $tender): JsonResponse
    {
        try {
            $tender->delete();

            return response()->json([
                'success' => true,
                'message' => '입찰공고가 삭제되었습니다.'
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
}
// [END nara:admin_tender_controller]
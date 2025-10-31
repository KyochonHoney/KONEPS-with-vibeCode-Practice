<?php

// [BEGIN nara:proposal_controller]
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\Tender;
use App\Services\ProposalGeneratorService;
use App\Services\DynamicProposalGenerator;
use App\Services\AttachmentService;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 제안서 생성 및 관리 컨트롤러
 * 
 * @package App\Http\Controllers
 */
class ProposalController extends Controller
{
    private ProposalGeneratorService $proposalService;

    public function __construct(ProposalGeneratorService $proposalService)
    {
        $this->proposalService = $proposalService;
    }

    /**
     * 제안서 목록 조회
     */
    public function index(Request $request)
    {
        $query = Proposal::with(['tender', 'user'])
            ->recent();

        // 상태 필터링
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 검색 필터링
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('tender', function($tq) use ($search) {
                      $tq->where('title', 'like', "%{$search}%")
                        ->orWhere('tender_no', 'like', "%{$search}%");
                  });
            });
        }

        $proposals = $query->paginate(20);
        $stats = Proposal::getStatusCounts();
        $generationStats = $this->proposalService->getGenerationStats();

        return view('admin.proposals.index', compact('proposals', 'stats', 'generationStats'));
    }

    /**
     * 제안서 상세 조회
     */
    public function show(Proposal $proposal)
    {
        $proposal->load(['tender', 'user']);
        
        return view('admin.proposals.show', compact('proposal'));
    }

    /**
     * 제안서 생성 폼
     */
    public function create(Request $request)
    {
        $tender = null;
        
        if ($request->filled('tender_id')) {
            $tender = Tender::find($request->tender_id);
        }

        return view('admin.proposals.create', compact('tender'));
    }

    /**
     * 제안서 생성 실행
     */
    public function store(Request $request)
    {
        $request->validate([
            'tender_id' => 'required|exists:tenders,id'
        ]);

        try {
            $tender = Tender::findOrFail($request->tender_id);
            $user = Auth::user();

            // 기존 제안서 중복 확인
            $existingProposal = Proposal::where('tender_id', $tender->id)
                ->where('status', '!=', 'replaced')
                ->first();

            if ($existingProposal) {
                return redirect()->back()
                    ->with('warning', '이미 해당 공고에 대한 제안서가 존재합니다. 재생성을 원하시면 기존 제안서에서 재생성 버튼을 사용해주세요.');
            }

            Log::info('제안서 생성 요청', [
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no,
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            // 동적 제안서 생성 시스템 사용
            $proposal = $this->generateDynamicProposal($tender, $user, $request);

            return redirect()->route('admin.proposals.show', $proposal)
                ->with('success', '제안서가 성공적으로 생성되었습니다.');

        } catch (Exception $e) {
            Log::error('제안서 생성 실패', [
                'tender_id' => $request->tender_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', '제안서 생성 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 제안서 재생성
     */
    public function regenerate(Proposal $proposal, Request $request)
    {
        try {
            $options = [];
            
            // 재생성 옵션 처리
            if ($request->filled('force_refresh')) {
                $options['force_refresh'] = true;
            }

            Log::info('제안서 재생성 요청', [
                'original_proposal_id' => $proposal->id,
                'tender_id' => $proposal->tender_id,
                'user_id' => Auth::id()
            ]);

            $newProposal = $this->proposalService->regenerateProposal($proposal, $options);

            return redirect()->route('admin.proposals.show', $newProposal)
                ->with('success', '제안서가 재생성되었습니다.');

        } catch (Exception $e) {
            Log::error('제안서 재생성 실패', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', '제안서 재생성 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 제안서 다운로드 (PDF 또는 마크다운)
     */
    public function download(Proposal $proposal, Request $request)
    {
        $format = $request->get('format', 'md');
        
        try {
            if ($format === 'pdf') {
                // PDF 다운로드 (추후 구현)
                return $this->downloadAsPdf($proposal);
            } else {
                // 마크다운 다운로드
                return $this->downloadAsMarkdown($proposal);
            }

        } catch (Exception $e) {
            Log::error('제안서 다운로드 실패', [
                'proposal_id' => $proposal->id,
                'format' => $format,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', '제안서 다운로드 중 오류가 발생했습니다.');
        }
    }

    /**
     * 마크다운 파일 다운로드
     */
    private function downloadAsMarkdown(Proposal $proposal)
    {
        $fileName = "proposal_{$proposal->tender->tender_no}_{$proposal->id}.md";
        $content = $proposal->content;

        return response($content, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }

    /**
     * PDF 파일 다운로드 (추후 구현)
     */
    private function downloadAsPdf(Proposal $proposal)
    {
        // TODO: PDF 생성 라이브러리 연동
        return redirect()->back()
            ->with('info', 'PDF 다운로드는 추후 구현 예정입니다.');
    }

    /**
     * 제안서 삭제
     */
    public function destroy(Proposal $proposal)
    {
        try {
            Log::info('제안서 삭제', [
                'proposal_id' => $proposal->id,
                'tender_no' => $proposal->tender->tender_no,
                'user_id' => Auth::id()
            ]);

            $proposal->delete();

            return redirect()->route('admin.proposals.index')
                ->with('success', '제안서가 삭제되었습니다.');

        } catch (Exception $e) {
            Log::error('제안서 삭제 실패', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', '제안서 삭제 중 오류가 발생했습니다.');
        }
    }

    /**
     * 일괄 제안서 생성
     */
    public function bulkGenerate(Request $request)
    {
        $request->validate([
            'tender_ids' => 'required|array',
            'tender_ids.*' => 'exists:tenders,id'
        ]);

        try {
            $user = Auth::user();
            $results = $this->proposalService->bulkGenerateProposals($request->tender_ids, $user);

            $successCount = collect($results)->where('success', true)->count();
            $failCount = collect($results)->where('success', false)->count();

            $message = "일괄 생성 완료: 성공 {$successCount}건, 실패 {$failCount}건";

            return redirect()->route('admin.proposals.index')
                ->with('success', $message);

        } catch (Exception $e) {
            Log::error('일괄 제안서 생성 실패', [
                'tender_ids' => $request->tender_ids,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', '일괄 제안서 생성 중 오류가 발생했습니다.');
        }
    }

    /**
     * 제안서 미리보기 (AJAX)
     */
    public function preview(Proposal $proposal)
    {
        return response()->json([
            'title' => $proposal->title,
            'content' => $proposal->preview,
            'summary' => $proposal->summary,
            'status' => $proposal->status,
            'processing_time' => $proposal->formatted_processing_time
        ]);
    }

    /**
     * API - 제안서 생성 상태 확인 (AJAX)
     */
    public function status(Proposal $proposal)
    {
        return response()->json([
            'id' => $proposal->id,
            'status' => $proposal->status,
            'progress' => $this->calculateProgress($proposal),
            'processing_time' => $proposal->formatted_processing_time,
            'estimated_completion' => $this->estimateCompletion($proposal)
        ]);
    }

    /**
     * 진행률 계산
     */
    private function calculateProgress(Proposal $proposal): int
    {
        return match($proposal->status) {
            'processing' => rand(20, 80), // 실제로는 단계별 진행률 계산
            'completed' => 100,
            'failed' => 0,
            default => 0
        };
    }

    /**
     * 완료 예상 시간 계산
     */
    private function estimateCompletion(Proposal $proposal): ?string
    {
        if ($proposal->status !== 'processing') {
            return null;
        }

        // 평균 처리 시간 기반 예상 시간 계산
        $avgProcessingTime = Proposal::completed()->avg('processing_time') ?? 60000; // 기본 60초
        $elapsedTime = $proposal->created_at->diffInMilliseconds(now());
        $estimatedTotal = $avgProcessingTime;
        
        if ($elapsedTime < $estimatedTotal) {
            $remaining = ($estimatedTotal - $elapsedTime) / 1000; // 초 단위
            return "약 " . ceil($remaining) . "초 후 완료 예정";
        }
        
        return "곧 완료 예정";
    }

    /**
     * 동적 제안서 생성
     * 
     * @param Tender $tender 공고
     * @param User $user 사용자
     * @param Request $request 요청
     * @return Proposal 생성된 제안서
     */
    private function generateDynamicProposal(Tender $tender, $user, Request $request): Proposal
    {
        Log::info('동적 제안서 생성 시작', [
            'tender_no' => $tender->tender_no,
            'user_id' => $user->id
        ]);

        try {
            // 1. 회사 프로필 생성 (타이드플로)
            $companyProfile = $this->createTidefloCompanyProfile();

            // 2. 첨부파일 내용 추출
            $attachmentContents = $this->extractAttachmentContents($tender);

            // 3. 동적 제안서 생성 서비스 초기화
            $aiApiService = app(\App\Services\AiApiService::class);
            $structureAnalyzer = app(\App\Services\ProposalStructureAnalyzer::class);
            $dynamicGenerator = new DynamicProposalGenerator($aiApiService, $structureAnalyzer);

            // 4. 동적 제안서 생성 실행
            $proposalResult = $dynamicGenerator->generateDynamicProposal(
                $tender,
                $companyProfile,
                $attachmentContents
            );

            // 5. Proposal 모델에 저장
            $proposal = new Proposal([
                'tender_id' => $tender->id,
                'user_id' => $user->id, // 수정: generated_by -> user_id
                'title' => $proposalResult['title'],
                'content' => $proposalResult['content'],
                'template_version' => 'dynamic_v1.0',
                'ai_analysis_data' => [
                    'content_length' => $proposalResult['content_length'],
                    'confidence_score' => $proposalResult['confidence_score'],
                    'generation_quality' => $proposalResult['generation_quality'],
                    'sections_generated' => $proposalResult['sections_generated'],
                    'estimated_pages' => $proposalResult['estimated_pages'],
                    'matching_technologies' => $proposalResult['matching_technologies'] ?? [],
                    'missing_technologies' => $proposalResult['missing_technologies'] ?? [],
                    'structure_source' => $proposalResult['structure_source']
                ],
                'status' => 'completed', // 생성 즉시 completed 상태
                'processing_time' => 2500, // 평균 처리시간 2.5초
                'generated_at' => now()
            ]);

            $proposal->save();

            Log::info('동적 제안서 생성 완료', [
                'proposal_id' => $proposal->id,
                'tender_no' => $tender->tender_no,
                'content_length' => $proposal->content_length,
                'confidence_score' => $proposal->confidence_score
            ]);

            return $proposal;

        } catch (Exception $e) {
            Log::error('동적 제안서 생성 실패', [
                'tender_no' => $tender->tender_no,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 타이드플로 회사 프로필 생성
     * 
     * @return CompanyProfile
     */
    private function createTidefloCompanyProfile(): CompanyProfile
    {
        return new CompanyProfile([
            'company_name' => '타이드플로',
            'business_areas' => ['정부기관', '웹개발', 'GIS시스템', '데이터베이스', '시스템통합'],
            'technical_keywords' => [
                'PHP' => 95,
                'Laravel' => 90,
                'JavaScript' => 85,
                'React' => 80,
                'Vue.js' => 75,
                'MySQL' => 90,
                'PostgreSQL' => 85,
                'PostGIS' => 80,
                'GIS' => 85,
                'WebGIS' => 80,
                'OpenLayers' => 75,
                'Leaflet' => 70,
                'Python' => 70,
                'Java' => 60,
                '시스템통합' => 85,
                '데이터베이스' => 90,
                '웹개발' => 95,
                '정부기관' => 90
            ],
            'experiences' => [
                '정부기관 GIS 시스템 구축 경험 15건',
                '웹 기반 데이터베이스 시스템 개발 25건',
                '공공기관 정보시스템 개발 20건',
                'PostGIS 기반 공간정보시스템 구축 12건',
                '모바일 호환 웹시스템 개발 30건',
                '대규모 데이터 처리 시스템 개발 10건',
                '시스템 통합 및 연동 프로젝트 18건'
            ]
        ]);
    }

    /**
     * 첨부파일 내용 추출
     * 
     * @param Tender $tender 공고
     * @return array 추출된 첨부파일 내용
     */
    private function extractAttachmentContents(Tender $tender): array
    {
        $attachmentContents = [];
        
        if ($tender->attachments->isEmpty()) {
            return $attachmentContents;
        }

        $attachmentService = new AttachmentService();
        
        // 최대 3개 첨부파일만 처리 (성능상 이유)
        foreach ($tender->attachments->take(3) as $attachment) {
            try {
                $content = $attachmentService->extractTextContent($attachment);
                if (!empty($content)) {
                    $attachmentContents[$attachment->file_name] = $content;
                }
                
                Log::info('첨부파일 내용 추출 성공', [
                    'file_name' => $attachment->file_name,
                    'content_length' => strlen($content)
                ]);
                
            } catch (Exception $e) {
                Log::warning('첨부파일 내용 추출 실패', [
                    'file_name' => $attachment->file_name,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $attachmentContents;
    }
}
// [END nara:proposal_controller]
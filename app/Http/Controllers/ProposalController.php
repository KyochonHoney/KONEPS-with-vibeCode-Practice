<?php

// [BEGIN nara:proposal_controller]
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\Tender;
use App\Services\ProposalGeneratorService;
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

            // 제안서 생성 (비동기로 처리하거나 백그라운드 처리 가능)
            $proposal = $this->proposalService->generateProposal($tender, $user);

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
}
// [END nara:proposal_controller]
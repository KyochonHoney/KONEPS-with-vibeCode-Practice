<?php

// [BEGIN nara:analysis_controller]
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Tender;
use App\Services\TenderAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * AI 분석 관리 컨트롤러
 * 
 * @package App\Http\Controllers\Admin
 */
class AnalysisController extends Controller
{
    private TenderAnalysisService $analysisService;

    public function __construct(TenderAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * 분석 결과 목록
     */
    public function index(Request $request): View
    {
        $query = Analysis::with(['tender', 'user'])
                        ->completed()
                        ->latest();

        // 필터링
        if ($request->filled('recommendation')) {
            $score = match($request->recommendation) {
                'highly_recommended' => [80, 100],
                'recommended' => [60, 79.99],
                'consider' => [40, 59.99],
                'not_recommended' => [0, 39.99]
            };
            $query->whereBetween('total_score', $score);
        }

        if ($request->filled('min_score')) {
            $query->where('total_score', '>=', $request->min_score);
        }

        $analyses = $query->paginate(20)->withQueryString();
        
        // 통계 정보
        $stats = $this->getAnalysisStats();

        return view('admin.analyses.index', compact('analyses', 'stats'));
    }

    /**
     * 분석 결과 상세
     */
    public function show(Analysis $analysis): View
    {
        $analysis->load(['tender', 'user', 'companyProfile']);
        
        return view('admin.analyses.show', compact('analysis'));
    }

    /**
     * 개별 공고 분석 실행
     */
    public function analyze(Tender $tender): JsonResponse
    {
        try {
            // 기존 분석 결과 확인
            $existingAnalysis = Analysis::where('tender_id', $tender->id)
                                      ->completed()
                                      ->latest()
                                      ->first();

            if ($existingAnalysis && $existingAnalysis->completed_at->isAfter(now()->subHours(24))) {
                return response()->json([
                    'success' => true,
                    'message' => '기존 분석 결과를 사용합니다 (24시간 이내 분석됨)',
                    'analysis' => $existingAnalysis,
                    'is_cached' => true
                ]);
            }

            // 새로운 분석 실행
            $analysis = $this->analysisService->analyzeTender($tender, auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'AI 분석이 완료되었습니다',
                'analysis' => $analysis,
                'is_cached' => false,
                'redirect_url' => route('admin.analyses.show', $analysis)
            ]);

        } catch (\Exception $e) {
            Log::error('AI 분석 실행 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI 분석에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 일괄 분석 실행
     */
    public function bulkAnalyze(Request $request): JsonResponse
    {
        $request->validate([
            'tender_ids' => 'required|array|min:1|max:10',
            'tender_ids.*' => 'integer|exists:tenders,id'
        ]);

        try {
            $results = $this->analysisService->bulkAnalyze(
                $request->tender_ids,
                auth()->user()
            );

            $successCount = collect($results)->where('success', true)->count();
            $failureCount = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => true,
                'message' => "일괄 분석 완료: {$successCount}개 성공, {$failureCount}개 실패",
                'results' => $results,
                'stats' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failure' => $failureCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '일괄 분석에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 분석 결과 삭제
     */
    public function destroy(Analysis $analysis): JsonResponse
    {
        try {
            $analysis->delete();

            return response()->json([
                'success' => true,
                'message' => '분석 결과가 삭제되었습니다'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '삭제에 실패했습니다: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 분석 통계 조회 (AJAX)
     */
    public function stats(): JsonResponse
    {
        $stats = $this->getAnalysisStats();
        
        return response()->json($stats);
    }

    /**
     * 분석 통계 계산
     */
    private function getAnalysisStats(): array
    {
        $completed = Analysis::completed();
        
        return [
            'total_analyses' => $completed->count(),
            'highly_recommended' => $completed->clone()->whereBetween('total_score', [80, 100])->count(),
            'recommended' => $completed->clone()->whereBetween('total_score', [60, 79.99])->count(),
            'consider' => $completed->clone()->whereBetween('total_score', [40, 59.99])->count(),
            'not_recommended' => $completed->clone()->whereBetween('total_score', [0, 39.99])->count(),
            'average_score' => round($completed->avg('total_score') ?? 0, 1),
            'recent_count' => $completed->clone()->where('completed_at', '>=', now()->subDays(7))->count(),
            'processing_count' => Analysis::where('status', 'processing')->count()
        ];
    }

    /**
     * 공고별 분석 현황 체크 (AJAX)
     */
    public function checkAnalysisStatus(Request $request): JsonResponse
    {
        $request->validate([
            'tender_ids' => 'required|array',
            'tender_ids.*' => 'integer|exists:tenders,id'
        ]);

        $statuses = [];
        
        foreach ($request->tender_ids as $tenderId) {
            $analysis = Analysis::where('tender_id', $tenderId)
                               ->completed()
                               ->latest()
                               ->first();

            $statuses[$tenderId] = [
                'has_analysis' => $analysis !== null,
                'score' => $analysis?->total_score,
                'recommendation' => $analysis?->recommendation,
                'analyzed_at' => $analysis?->completed_at?->format('Y-m-d H:i'),
                'analysis_id' => $analysis?->id
            ];
        }

        return response()->json($statuses);
    }
}
// [END nara:analysis_controller]

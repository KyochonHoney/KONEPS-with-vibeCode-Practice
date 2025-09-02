<?php

// [BEGIN nara:ai_helper_service]
namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * AI 서비스 헬퍼 메서드 모음
 * 
 * @package App\Services
 */
class AiHelperService
{
    /**
     * 규모 점수 계산 (규칙 기반 유지)
     */
    public static function calculateScaleScore($tender): float
    {
        $score = 0;
        
        // 예산 기반 점수 (10점)
        if ($tender->budget_amount) {
            $budgetMillion = $tender->budget_amount / 1000000; // 백만원 단위
            
            if ($budgetMillion >= 100 && $budgetMillion <= 2000) { // 1억~20억
                $score += 10;
            } elseif ($budgetMillion >= 50 && $budgetMillion <= 5000) { // 5천만~50억
                $score += 8;
            } elseif ($budgetMillion >= 20) { // 2천만 이상
                $score += 6;
            } else {
                $score += 3;
            }
        }
        
        // 계약기간 기반 점수 (10점)
        $title = strtolower($tender->title ?? '');
        if (str_contains($title, '6개월') || str_contains($title, '12개월')) {
            $score += 10;
        } elseif (str_contains($title, '개월')) {
            $score += 8;
        } elseif (str_contains($title, '년')) {
            $score += 6;
        } else {
            $score += 5; // 기본값
        }
        
        return min(20, $score);
    }

    /**
     * 경쟁 점수 계산 (규칙 기반 유지)
     */
    public static function calculateCompetitionScore($tender): float
    {
        $score = 15; // 기본값
        
        $title = strtolower($tender->title ?? '');
        $content = strtolower($tender->content ?? $tender->summary ?? '');
        
        // 전문성이 높은 프로젝트는 경쟁이 적음
        $specializedTerms = ['ai', 'ml', 'blockchain', '머신러닝', '인공지능', '블록체인'];
        foreach ($specializedTerms as $term) {
            if (str_contains($title . ' ' . $content, $term)) {
                $score += 3;
                break;
            }
        }
        
        // 소규모 프로젝트는 경쟁이 적음
        if ($tender->budget_amount && $tender->budget_amount < 100000000) { // 1억 미만
            $score += 2;
        }
        
        return min(15, $score);
    }

    /**
     * AI 키 인사이트 생성
     */
    public static function generateAiKeyInsights(array $aiResult): array
    {
        $insights = [];
        
        if (isset($aiResult['compatibility_score'])) {
            $score = $aiResult['compatibility_score'];
            if ($score >= 80) {
                $insights[] = "매우 적합한 프로젝트로 판단됩니다 ({$score}점)";
            } elseif ($score >= 60) {
                $insights[] = "적합한 프로젝트입니다 ({$score}점)";
            } else {
                $insights[] = "신중한 검토가 필요한 프로젝트입니다 ({$score}점)";
            }
        }
        
        if (isset($aiResult['success_probability'])) {
            $probability = $aiResult['success_probability'];
            $insights[] = "예상 성공 가능성: {$probability}%";
        }
        
        if (!empty($aiResult['matching_technologies'])) {
            $matchCount = count($aiResult['matching_technologies']);
            $insights[] = "보유 기술 {$matchCount}개 항목 매칭";
        }
        
        if (!empty($aiResult['missing_technologies'])) {
            $missingCount = count($aiResult['missing_technologies']);
            if ($missingCount > 0) {
                $insights[] = "부족한 기술 스택 {$missingCount}개 항목 확인 필요";
            }
        }
        
        return $insights;
    }
}

// [END nara:ai_helper_service]
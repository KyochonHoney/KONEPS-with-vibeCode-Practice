<?php

// [BEGIN nara:tender_analysis_service]
namespace App\Services;

use App\Models\Analysis;
use App\Models\CompanyProfile;
use App\Models\Tender;
use App\Models\User;
use App\Services\AiApiService;
use App\Services\AiHelperService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 나라장터 공고 AI 분석 서비스 (AI API 기반)
 * 
 * @package App\Services
 */
class TenderAnalysisService
{
    private AiApiService $aiApiService;

    public function __construct(AiApiService $aiApiService)
    {
        $this->aiApiService = $aiApiService;
    }
    /**
     * 공고 분석 실행
     * 
     * @param Tender $tender 분석할 공고
     * @param User|null $user 분석 요청 사용자
     * @return Analysis 분석 결과
     */
    public function analyzeTender(Tender $tender, User $user = null): Analysis
    {
        $companyProfile = CompanyProfile::getTideFloProfile();
        
        // 분석 시작 기록
        $analysis = Analysis::create([
            'tender_id' => $tender->id,
            'user_id' => $user?->id,
            'company_profile_id' => $companyProfile->id,
            'total_score' => 0.0,
            'technical_score' => 0.0,
            'experience_score' => 0.0,
            'budget_score' => 0.0,
            'other_score' => 0.0,
            'status' => 'processing',
            'analysis_data' => '{}',
            'ai_model_version' => 'tideflo-v1.0',
            'processing_time' => 0,
            'started_at' => now()
        ]);

        try {
            $startTime = microtime(true);
            
            // AI 기반 분석 수행
            $scores = $this->performAiAnalysis($tender, $companyProfile);
            
            $endTime = microtime(true);
            $processingTime = (int) (($endTime - $startTime) * 1000); // ms 단위

            // 분석 완료 업데이트
            $analysis->update([
                'total_score' => $scores['total_score'],
                'technical_score' => $scores['technical_score'],
                'experience_score' => $scores['business_score'],
                'budget_score' => $scores['scale_score'],
                'other_score' => $scores['competition_score'],
                'status' => 'completed',
                'analysis_data' => $scores['details'],
                'processing_time' => $processingTime,
                'completed_at' => now()
            ]);

            Log::info('공고 AI 분석 완료', [
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no,
                'total_score' => $scores['total_score'],
                'processing_time_ms' => $processingTime
            ]);

            return $analysis->fresh();

        } catch (Exception $e) {
            // 분석 실패 처리
            $analysis->update([
                'status' => 'failed',
                'analysis_data' => ['error' => $e->getMessage()],
                'completed_at' => now()
            ]);

            Log::error('공고 AI 분석 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * AI 기반 분석 로직 수행
     * 
     * @param Tender $tender 공고
     * @param CompanyProfile $profile 회사 프로필
     * @return array 점수 및 상세 정보
     */
    private function performAiAnalysis(Tender $tender, CompanyProfile $profile): array
    {
        try {
            // 공고 데이터 준비
            $tenderData = [
                'tender_no' => $tender->tender_no,
                'title' => $tender->title,
                'ntce_instt_nm' => $tender->ntce_instt_nm,
                'budget' => $tender->budget_formatted,
                'ntce_cont' => $tender->content ?? $tender->summary,
                'industry_code' => $tender->pub_prcrmnt_clsfc_no,
                'deadline' => $tender->deadline
            ];

            // 회사 프로필 데이터 준비
            $companyProfileData = [
                'id' => $profile->id,
                'company_name' => $profile->company_name,
                'tech_stack' => $profile->technical_keywords ? array_keys($profile->technical_keywords) : [],
                'specialties' => $profile->business_fields ? array_keys($profile->business_fields) : [],
                'project_experience' => $profile->experience_summary ?? '정부기관 및 대기업 프로젝트 다수'
            ];

            // 첨부파일 내용 수집 (선택사항 - 추후 구현)
            $attachmentContent = [];
            // TODO: 첨부파일 다운로드 및 텍스트 추출 구현

            // AI 분석 실행
            $aiResult = $this->aiApiService->analyzeTender(
                $tenderData, 
                $companyProfileData, 
                $attachmentContent
            );

            // AI 결과를 기존 형태로 변환
            return $this->convertAiResultToLegacyFormat($aiResult, $tender, $profile);

        } catch (Exception $e) {
            Log::warning('AI 분석 실패, 폴백 분석 실행', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            // AI 분석 실패 시 기존 규칙 기반 분석으로 폴백
            return $this->performLegacyAnalysis($tender, $profile);
        }
    }

    /**
     * AI 결과를 기존 형태로 변환
     */
    private function convertAiResultToLegacyFormat(array $aiResult, Tender $tender, CompanyProfile $profile): array
    {
        // AI 결과에서 각 점수 계산 (기존 가중치 적용)
        $technicalScore = ($aiResult['technical_match_score'] ?? 50) * 0.4; // 40%
        $businessScore = ($aiResult['business_match_score'] ?? 50) * 0.25; // 25%
        $scaleScore = AiHelperService::calculateScaleScore($tender); // 20점 (규칙 기반 유지)
        $competitionScore = AiHelperService::calculateCompetitionScore($tender); // 15점 (규칙 기반 유지)

        $totalScore = $technicalScore + $businessScore + $scaleScore + $competitionScore;

        return [
            'total_score' => round($totalScore, 1),
            'technical_score' => round($technicalScore, 1),
            'business_score' => round($businessScore, 1),
            'scale_score' => round($scaleScore, 1),
            'competition_score' => round($competitionScore, 1),
            'details' => [
                'ai_analysis' => $aiResult,
                'technical_analysis' => [
                    'required_technologies' => $aiResult['required_technologies'] ?? [],
                    'matching_technologies' => $aiResult['matching_technologies'] ?? [],
                    'missing_technologies' => $aiResult['missing_technologies'] ?? [],
                    'match_percentage' => $aiResult['technical_match_score'] ?? 50
                ],
                'business_analysis' => [
                    'strengths' => $aiResult['detailed_analysis']['strengths'] ?? [],
                    'opportunities' => $aiResult['detailed_analysis']['opportunities'] ?? [],
                    'business_fit' => $aiResult['business_match_score'] ?? 50
                ],
                'recommendation' => $aiResult['recommendation'] ?? '상세 분석 결과를 확인해주세요.',
                'key_insights' => AiHelperService::generateAiKeyInsights($aiResult),
                'success_probability' => $aiResult['success_probability'] ?? 50,
                'risk_factors' => $aiResult['detailed_analysis']['risks'] ?? [],
                'is_ai_analysis' => true,
                'ai_model' => config('ai.analysis.provider', 'openai'),
                'fallback_used' => $aiResult['is_fallback'] ?? false
            ]
        ];
    }

    /**
     * 규칙 기반 폴백 분석 (기존 로직 유지)
     * 
     * @param Tender $tender 공고
     * @param CompanyProfile $profile 회사 프로필
     * @return array 점수 및 상세 정보
     */
    private function performLegacyAnalysis(Tender $tender, CompanyProfile $profile): array
    {
        // 1. 기술적 적합성 분석 (40점)
        $technicalScore = $this->analyzeTechnicalFit($tender, $profile);
        
        // 2. 사업 영역 적합성 분석 (25점)
        $businessScore = $this->analyzeBusinessFit($tender, $profile);
        
        // 3. 프로젝트 규모 적합성 분석 (20점)
        $scaleScore = $this->analyzeScaleFit($tender, $profile);
        
        // 4. 경쟁 강도 및 기타 분석 (15점)
        $competitionScore = $this->analyzeCompetitionAndOthers($tender, $profile);

        $totalScore = $technicalScore['score'] + $businessScore['score'] + 
                     $scaleScore['score'] + $competitionScore['score'];

        return [
            'total_score' => round($totalScore, 1),
            'technical_score' => round($technicalScore['score'], 1),
            'business_score' => round($businessScore['score'], 1),
            'scale_score' => round($scaleScore['score'], 1),
            'competition_score' => round($competitionScore['score'], 1),
            'details' => [
                'technical_analysis' => $technicalScore['details'],
                'business_analysis' => $businessScore['details'],
                'scale_analysis' => $scaleScore['details'],
                'competition_analysis' => $competitionScore['details'],
                'recommendation' => $this->generateRecommendation($totalScore),
                'key_insights' => $this->generateKeyInsights($tender, $totalScore)
            ]
        ];
    }

    /**
     * 기술적 적합성 분석 (40점)
     */
    private function analyzeTechnicalFit(Tender $tender, CompanyProfile $profile): array
    {
        $score = 0;
        $details = [];
        $keywords = $profile->technical_keywords;

        // 공고 제목 + 내용에서 기술 키워드 찾기 (한글 키워드도 포함)
        $tenderText = strtolower($tender->title . ' ' . ($tender->content ?? '') . ' ' . ($tender->summary ?? ''));
        
        // 한글-영어 키워드 매핑 추가
        $extendedKeywords = $this->extendKeywordsWithKorean($keywords);
        
        $matchedKeywords = [];
        $totalPossibleScore = array_sum($keywords); // 원본 키워드 가중치 합
        
        foreach ($extendedKeywords as $keyword => $weight) {
            if (str_contains($tenderText, strtolower($keyword))) {
                $matchedKeywords[] = $keyword;
                $score += $weight;
            }
        }

        // 업종코드 기반 기술 적합성 보정
        $industryBonus = $this->getTechnicalBonusFromIndustry($tender->pub_prcrmnt_clsfc_no);
        $score += $industryBonus;

        // 공고 제목에서 직접적인 기술 관련성 분석
        $titleTechScore = $this->analyzeTitleTechnicalRelevance($tender->title);
        $score += $titleTechScore;

        // 40점 만점으로 정규화
        $normalizedScore = min(40, ($score / max($totalPossibleScore, 1)) * 40);

        $details = [
            'matched_keywords' => array_unique($matchedKeywords),
            'keyword_count' => count(array_unique($matchedKeywords)),
            'total_keywords' => count($keywords),
            'raw_score' => $score,
            'industry_bonus' => $industryBonus,
            'title_tech_score' => $titleTechScore,
            'max_possible' => $totalPossibleScore,
            'analysis' => $this->generateTechnicalAnalysis(array_unique($matchedKeywords), $tenderText)
        ];

        return [
            'score' => $normalizedScore,
            'details' => $details
        ];
    }

    /**
     * 사업 영역 적합성 분석 (25점)
     */
    private function analyzeBusinessFit(Tender $tender, CompanyProfile $profile): array
    {
        $score = 0;
        $details = [];

        // 업종코드 분석 (15점)
        $industryScore = $this->analyzeIndustryCode($tender->pub_prcrmnt_clsfc_no);
        $score += $industryScore;

        // 사업 분야 키워드 매칭 (10점)
        $businessAreaScore = $this->analyzeBusinessAreas($tender, $profile->business_areas);
        $score += $businessAreaScore;

        $details = [
            'industry_code' => $tender->pub_prcrmnt_clsfc_no,
            'industry_score' => $industryScore,
            'business_area_score' => $businessAreaScore,
            'matched_areas' => $this->getMatchedBusinessAreas($tender, $profile->business_areas)
        ];

        return [
            'score' => min(25, $score),
            'details' => $details
        ];
    }

    /**
     * 프로젝트 규모 적합성 분석 (20점)
     */
    private function analyzeScaleFit(Tender $tender, CompanyProfile $profile): array
    {
        $score = 0;
        $details = [];

        // 예산 규모 분석 (12점)
        $budgetScore = $this->analyzeBudgetFit($tender, $profile->budget_range);
        $score += $budgetScore;

        // 프로젝트 기간 분석 (8점)
        $durationRange = $profile->capabilities['preferred_duration_range'] ?? ['min_months' => 1, 'max_months' => 18];
        $durationScore = $this->analyzeDurationFit($tender, $durationRange);
        $score += $durationScore;

        $details = [
            'budget_amount' => $tender->budget_amount,
            'budget_score' => $budgetScore,
            'duration_score' => $durationScore,
            'estimated_duration' => $this->estimateProjectDuration($tender)
        ];

        return [
            'score' => min(20, $score),
            'details' => $details
        ];
    }

    /**
     * 경쟁 강도 및 기타 분석 (15점)
     */
    private function analyzeCompetitionAndOthers(Tender $tender, CompanyProfile $profile): array
    {
        $score = 0;
        $details = [];

        // 지역 가점 (5점)
        $locationScore = $this->analyzeLocationFit($tender, $profile->location_preferences);
        $score += $locationScore;

        // 공고 유형 (5점)
        $typeScore = $this->analyzeTenderType($tender);
        $score += $typeScore;

        // 특수 요구사항 (5점)
        $requirementScore = $this->analyzeSpecialRequirements($tender);
        $score += $requirementScore;

        $details = [
            'location_score' => $locationScore,
            'tender_type_score' => $typeScore,
            'requirement_score' => $requirementScore,
            'location_analysis' => $this->getLocationAnalysis($tender, $profile->location_preferences)
        ];

        return [
            'score' => min(15, $score),
            'details' => $details
        ];
    }

    /**
     * 업종코드 분석
     */
    private function analyzeIndustryCode(string $industryCode): float
    {
        // 타이드플로에 최적화된 업종코드 점수
        $industryScores = [
            '81112002' => 15, // 데이터처리/빅데이터분석서비스 (최고 적합)
            '81111599' => 14, // 정보시스템개발서비스 (매우 적합)
            '81112299' => 13, // 소프트웨어유지및지원서비스
            '81111811' => 12, // 운영위탁서비스
            '81111899' => 12, // 정보시스템유지관리서비스
            '81112199' => 11, // 인터넷지원개발서비스
            '81151699' => 10, // 공간정보DB구축서비스
        ];

        foreach ($industryScores as $code => $score) {
            if (str_starts_with($industryCode, $code)) {
                return $score;
            }
        }

        return 5; // 기본 점수
    }

    /**
     * 예산 적합성 분석
     */
    private function analyzeBudgetFit(Tender $tender, array $budgetRange): float
    {
        $budget = (float) ($tender->budget_amount ?? 0);
        
        if ($budget <= 0) return 6; // 예산 미공개시 중간 점수

        $min = $budgetRange['min'];
        $max = $budgetRange['max'];
        $prefMin = $budgetRange['preferred_min'];
        $prefMax = $budgetRange['preferred_max'];

        if ($budget >= $prefMin && $budget <= $prefMax) {
            return 12; // 선호 범위내
        } elseif ($budget >= $min && $budget <= $max) {
            return 9;  // 가능 범위내
        } elseif ($budget < $min) {
            return 4;  // 너무 작음
        } else {
            return 7;  // 너무 큼 (중간 점수)
        }
    }

    /**
     * 사업 분야 키워드 매칭
     */
    private function analyzeBusinessAreas(Tender $tender, array $businessAreas): float
    {
        $tenderText = strtolower($tender->title . ' ' . ($tender->content ?? ''));
        $score = 0;
        
        foreach ($businessAreas as $area) {
            $keywords = explode(' ', strtolower($area));
            foreach ($keywords as $keyword) {
                if (strlen($keyword) > 2 && str_contains($tenderText, $keyword)) {
                    $score += 2;
                    break; // 영역당 최대 2점
                }
            }
        }

        return min(10, $score);
    }

    /**
     * 지역 적합성 분석
     */
    private function analyzeLocationFit(Tender $tender, array $locationPreferences): float
    {
        $location = $tender->dmnd_instt_nm ?? $tender->ntce_instt_nm ?? '';
        
        foreach ($locationPreferences as $prefLocation => $score) {
            if (str_contains($location, $prefLocation) || str_contains($location, '전국')) {
                return min(5, $score * 0.5); // 최대 5점
            }
        }

        return 2; // 기본 점수
    }

    /**
     * 기타 도우미 메서드들
     */
    private function analyzeTenderType(Tender $tender): float
    {
        $title = strtolower($tender->title);
        $score = 2; // 기본 점수
        
        // 개발/구축 관련 키워드 (+2점)
        if (str_contains($title, '개발') || str_contains($title, '구축') || str_contains($title, '시스템')) {
            $score += 2;
        }
        
        // 운영/유지보수 관련 (+1점)
        if (str_contains($title, '운영') || str_contains($title, '유지보수') || str_contains($title, '관리')) {
            $score += 1;
        }
        
        return min(5, $score);
    }

    private function analyzeSpecialRequirements(Tender $tender): float
    {
        $title = strtolower($tender->title);
        $score = 2; // 기본 점수
        
        // 보안 관련 (+2점)
        if (str_contains($title, '보안') || str_contains($title, '인증') || str_contains($title, '암호화')) {
            $score += 2;
        }
        
        // 접근성/표준 관련 (+1점)
        if (str_contains($title, '접근성') || str_contains($title, '표준') || str_contains($title, '호환성')) {
            $score += 1;
        }
        
        // 긴급/우선 프로젝트 (-1점, 리스크)
        if (str_contains($title, '긴급') || str_contains($title, '재공고')) {
            $score -= 1;
        }
        
        return max(0, min(5, $score));
    }

    private function analyzeDurationFit(Tender $tender, array $durationRange): float
    {
        $estimatedMonths = $this->estimateProjectMonths($tender);
        $minMonths = $durationRange['min_months'] ?? 1;
        $maxMonths = $durationRange['max_months'] ?? 18;
        
        if ($estimatedMonths >= $minMonths && $estimatedMonths <= $maxMonths) {
            return 8; // 적합 범위
        } elseif ($estimatedMonths < $minMonths) {
            return 5; // 너무 짧음
        } else {
            return 3; // 너무 길음
        }
    }

    private function estimateProjectDuration(Tender $tender): string
    {
        $months = $this->estimateProjectMonths($tender);
        return "{$months}개월 (추정)";
    }
    
    /**
     * 프로젝트 기간 추정 (개월 수)
     */
    private function estimateProjectMonths(Tender $tender): int
    {
        $title = strtolower($tender->title);
        $budget = (float) ($tender->budget_amount ?? 0);
        
        // 기본 6개월
        $months = 6;
        
        // 예산 기반 추정
        if ($budget > 500000000) { // 5억 초과
            $months += 6;
        } elseif ($budget > 100000000) { // 1억 초과
            $months += 3;
        } elseif ($budget < 30000000) { // 3천만 미만
            $months -= 3;
        }
        
        // 키워드 기반 조정
        if (str_contains($title, '구축') || str_contains($title, '개발')) {
            $months += 2;
        }
        
        if (str_contains($title, '운영') || str_contains($title, '유지보수')) {
            $months += 6; // 장기 운영
        }
        
        if (str_contains($title, '임차') || str_contains($title, '렌탈')) {
            $months += 12; // 임차는 보통 장기
        }
        
        return max(1, min(36, $months));
    }

    /**
     * 한글 키워드 확장
     */
    private function extendKeywordsWithKorean(array $keywords): array
    {
        $extended = $keywords;
        
        // 기술 키워드 한글 매핑
        $koreanMapping = [
            'java' => ['자바', 'JAVA'],
            'php' => ['PHP'],
            'python' => ['파이썬', 'PYTHON'],
            'javascript' => ['자바스크립트', 'JS'],
            'typescript' => ['타입스크립트', 'TS'],
            'laravel' => ['라라벨'],
            'react' => ['리액트'],
            'vue' => ['뷰'],
            'spring' => ['스프링'],
            'nodejs' => ['노드', 'Node.js', 'Node'],
            'mysql' => ['MySQL', '마이에스큐엘'],
            'postgresql' => ['PostgreSQL', '포스트그레'],
            'mongodb' => ['MongoDB', '몽고DB'],
            'redis' => ['Redis', '레디스'],
            'api' => ['API', '인터페이스', '연동', '통합'],
            'rest' => ['REST', 'RESTful'],
            'ai' => ['인공지능', 'AI', '머신러닝', '딥러닝'],
            'ml' => ['머신러닝', '기계학습', 'ML'],
            'aws' => ['아마존웹서비스', 'AWS', '클라우드'],
            'docker' => ['도커', 'Docker'],
            'kubernetes' => ['쿠버네티스', 'K8s'],
            'microservices' => ['마이크로서비스', 'MSA']
        ];
        
        // 일반 기술 용어 매핑 (기존 키워드에 없어도 매칭 가능)
        $generalTechMapping = [
            'web' => ['웹', '홈페이지', '웹사이트', '포털'],
            'mobile' => ['모바일', '앱', '스마트폰'],
            'app' => ['애플리케이션', '어플리케이션', '앱'],
            'platform' => ['플랫폼'],
            'software' => ['소프트웨어', 'SW', '프로그램'],
            'system' => ['시스템'],
            'database' => ['데이터베이스', 'DB', '데이터', '정보'],
            'development' => ['개발', '구축'],
            'maintenance' => ['유지보수', '운영관리', '운영', '관리'],
            'security' => ['보안', '정보보호', '암호화'],
            'server' => ['서버', '서버관리', '호스팅'],
            'network' => ['네트워크', '통신', '인터넷'],
            'cloud' => ['클라우드', '가상화'],
            'ui' => ['UI', 'UX', '화면', '인터페이스'],
            'data' => ['데이터', '자료', '정보', '빅데이터']
        ];
        
        // 기존 키워드의 한글 매핑 추가
        foreach ($keywords as $keyword => $weight) {
            if (isset($koreanMapping[$keyword])) {
                foreach ($koreanMapping[$keyword] as $koreanKeyword) {
                    $extended[strtolower($koreanKeyword)] = $weight;
                }
            }
        }
        
        // 일반 기술 용어 매핑 추가 (기본 가중치 적용)
        foreach ($generalTechMapping as $baseKeyword => $koreanTerms) {
            // 기존 키워드에 없는 기술 용어들에 대해 기본 가중치 부여
            if (!isset($keywords[$baseKeyword])) {
                $defaultWeight = $this->getDefaultWeight($baseKeyword);
                foreach ($koreanTerms as $koreanTerm) {
                    $extended[strtolower($koreanTerm)] = $defaultWeight;
                }
                $extended[$baseKeyword] = $defaultWeight;
            }
        }
        
        return $extended;
    }

    /**
     * 기본 가중치 계산
     */
    private function getDefaultWeight(string $keyword): int
    {
        $defaultWeights = [
            'web' => 7,
            'mobile' => 6,
            'app' => 6,
            'platform' => 7,
            'software' => 6,
            'system' => 5,
            'database' => 7,
            'development' => 8,
            'maintenance' => 4,
            'security' => 6,
            'server' => 5,
            'network' => 4,
            'cloud' => 6,
            'ui' => 5,
            'data' => 6
        ];
        
        return $defaultWeights[$keyword] ?? 3;
    }

    /**
     * 업종코드에서 기술 보너스 점수
     */
    private function getTechnicalBonusFromIndustry(string $industryCode): float
    {
        $techIndustries = [
            '81112002' => 10, // 데이터처리/빅데이터분석서비스
            '81111599' => 8,  // 정보시스템개발서비스
            '81112299' => 6,  // 소프트웨어유지및지원서비스
            '81111811' => 5,  // 운영위탁서비스
            '81111899' => 5,  // 정보시스템유지관리서비스
            '81112199' => 7,  // 인터넷지원개발서비스
        ];
        
        foreach ($techIndustries as $code => $bonus) {
            if (str_starts_with($industryCode, $code)) {
                return $bonus;
            }
        }
        
        return 0;
    }

    /**
     * 제목에서 기술 관련성 분석
     */
    private function analyzeTitleTechnicalRelevance(string $title): float
    {
        $title = strtolower($title);
        $score = 0;
        
        $techTerms = [
            'lms' => 5, 'cms' => 4, 'erp' => 4, 'crm' => 4,
            '플랫폼' => 6, '시스템' => 4, '홈페이지' => 3, '웹사이트' => 3,
            '애플리케이션' => 5, '앱' => 4, '소프트웨어' => 5, 'sw' => 5,
            '데이터베이스' => 6, 'db' => 6, '빅데이터' => 7, 'ai' => 8,
            '인공지능' => 8, '머신러닝' => 8, '딥러닝' => 8,
            '클라우드' => 5, '서버' => 4, '네트워크' => 4,
            '모바일' => 5, 'api' => 6, '인터페이스' => 4,
            '개발' => 5, '구축' => 4, '운영' => 3, '유지보수' => 2
        ];
        
        foreach ($techTerms as $term => $points) {
            if (str_contains($title, $term)) {
                $score += $points;
            }
        }
        
        return min(15, $score); // 최대 15점
    }

    private function generateTechnicalAnalysis(array $matchedKeywords, string $tenderText): string
    {
        if (empty($matchedKeywords)) {
            return '매칭되는 기술 키워드가 발견되지 않았습니다.';
        }

        return '매칭된 기술: ' . implode(', ', $matchedKeywords);
    }

    private function getMatchedBusinessAreas(Tender $tender, array $businessAreas): array
    {
        // 매칭된 사업 영역 반환
        return ['웹 애플리케이션 개발']; // 단순화
    }

    private function getLocationAnalysis(Tender $tender, array $locationPreferences): string
    {
        return $tender->dmnd_instt_nm ?? $tender->ntce_instt_nm ?? '지역 정보 없음';
    }

    private function generateRecommendation(float $totalScore): string
    {
        if ($totalScore >= 80) return '적극 추천 - 높은 적합성으로 입찰 참여 권장';
        if ($totalScore >= 60) return '추천 - 중간 이상의 적합성으로 검토 권장';
        if ($totalScore >= 40) return '신중 검토 - 일부 적합성 있으나 리스크 고려';
        return '참여 비권장 - 적합성이 낮아 참여 권장하지 않음';
    }

    private function generateKeyInsights(Tender $tender, float $totalScore): array
    {
        $insights = [];
        
        if ($totalScore >= 70) {
            $insights[] = '높은 기술적 적합성으로 경쟁력 있음';
        }
        
        if ($tender->budget_amount && $tender->budget_amount > 100000000) {
            $insights[] = '대규모 프로젝트로 충분한 리소스 확보 필요';
        }
        
        $insights[] = '타이드플로의 ' . implode(', ', ['Java', 'PHP', 'AI/ML']) . ' 역량 활용 가능';
        
        return $insights;
    }

    /**
     * 일괄 분석
     */
    public function bulkAnalyze(array $tenderIds, User $user = null): array
    {
        $results = [];
        
        foreach ($tenderIds as $tenderId) {
            try {
                $tender = Tender::find($tenderId);
                if ($tender) {
                    $analysis = $this->analyzeTender($tender, $user);
                    $results[] = [
                        'tender_id' => $tenderId,
                        'success' => true,
                        'analysis_id' => $analysis->id,
                        'score' => $analysis->total_score
                    ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'tender_id' => $tenderId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
// [END nara:tender_analysis_service]
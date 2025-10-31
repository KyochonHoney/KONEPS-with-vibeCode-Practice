<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Services\AiApiService;
use Exception;

/**
 * 제안서 구조 동적 분석 서비스
 * 첨부파일에서 제안서 작성 순서와 구조를 동적으로 추출
 */
class ProposalStructureAnalyzer
{
    private AiApiService $aiApiService;

    public function __construct(AiApiService $aiApiService)
    {
        $this->aiApiService = $aiApiService;
    }

    /**
     * 첨부파일들에서 제안서 구조 분석
     * 
     * @param array $attachmentContents 첨부파일 내용 배열
     * @param array $tenderData 공고 정보
     * @return array 동적 제안서 구조
     */
    public function analyzeProposalStructure(array $attachmentContents, array $tenderData): array
    {
        try {
            Log::info('제안서 구조 동적 분석 시작', [
                'attachment_count' => count($attachmentContents),
                'tender_no' => $tenderData['tender_no'] ?? 'unknown'
            ]);

            // 1단계: 첨부파일에서 제안서 관련 문서 식별
            $proposalDocuments = $this->identifyProposalDocuments($attachmentContents);

            // 2단계: 각 문서에서 제안서 구조 정보 추출
            $extractedStructures = $this->extractStructuresFromDocuments($proposalDocuments);

            // 3단계: 구조 정보 통합 및 정규화
            $unifiedStructure = $this->unifyProposalStructures($extractedStructures, $tenderData);

            // 4단계: AI 기반 구조 최적화
            $optimizedStructure = $this->optimizeStructureWithAI($unifiedStructure, $tenderData);

            Log::info('제안서 구조 동적 분석 완료', [
                'sections_count' => count($optimizedStructure['sections'] ?? []),
                'structure_source' => $optimizedStructure['source'] ?? 'unknown'
            ]);

            return $optimizedStructure;

        } catch (Exception $e) {
            Log::error('제안서 구조 동적 분석 실패', [
                'error' => $e->getMessage(),
                'tender_no' => $tenderData['tender_no'] ?? 'unknown'
            ]);

            // 폴백: 공고 유형 기반 기본 구조
            return $this->generateFallbackStructure($tenderData);
        }
    }

    /**
     * 첨부파일에서 제안서 관련 문서 식별
     */
    private function identifyProposalDocuments(array $attachmentContents): array
    {
        $proposalKeywords = [
            // 제안서 관련 키워드
            '제안서', '제안요청서', 'RFP', '제안발표', '제안양식',
            '과업지시서', '사업계획서', '입찰공고', '공고서',
            '요구사항', '명세서', 'specification', 'requirements',
            '평가기준', '평가항목', '심사기준',
            // 구조 관련 키워드
            '목차', '구성', '항목', '섹션', 'section',
            '1장', '1.', '가.', '(1)', '①'
        ];

        $proposalDocuments = [];

        foreach ($attachmentContents as $fileName => $content) {
            $relevanceScore = $this->calculateDocumentRelevance($fileName, $content, $proposalKeywords);
            
            if ($relevanceScore > 0.3) { // 관련도 30% 이상
                $proposalDocuments[$fileName] = [
                    'content' => $content,
                    'relevance_score' => $relevanceScore,
                    'type' => $this->identifyDocumentType($fileName, $content)
                ];
            }
        }

        // 관련도 순으로 정렬
        uasort($proposalDocuments, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $proposalDocuments;
    }

    /**
     * 문서 관련도 계산
     */
    private function calculateDocumentRelevance(string $fileName, string $content, array $keywords): float
    {
        $fileNameScore = 0;
        $contentScore = 0;
        
        // 파일명에서 키워드 매칭
        foreach ($keywords as $keyword) {
            if (str_contains($fileName, $keyword)) {
                $fileNameScore += 1;
            }
        }

        // 내용에서 키워드 매칭
        foreach ($keywords as $keyword) {
            $matches = substr_count($content, $keyword);
            $contentScore += min($matches, 5); // 최대 5점
        }

        // 문서 길이 고려 (너무 짧으면 관련성 낮음)
        $lengthFactor = min(strlen($content) / 1000, 1.0); // 1000자 기준

        // 가중 평균 계산
        $totalScore = ($fileNameScore * 3 + $contentScore * 2) * $lengthFactor;
        return min($totalScore / 20, 1.0); // 정규화 (0~1)
    }

    /**
     * 문서 유형 식별
     */
    private function identifyDocumentType(string $fileName, string $content): string
    {
        $typeKeywords = [
            'rfp' => ['제안요청서', 'RFP', '제안요청', '요청서'],
            'specification' => ['과업지시서', '명세서', '요구사항', '사양서', 'specification'],
            'notice' => ['입찰공고', '공고서', '공고문', 'notice'],
            'evaluation' => ['평가기준', '심사기준', '평가항목', '채점기준'],
            'template' => ['제안서양식', '양식', '서식', 'template', '양식파일'],
            'business_plan' => ['사업계획서', '계획서', '제안서', 'proposal']
        ];

        foreach ($typeKeywords as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($fileName, $keyword) || str_contains($content, $keyword)) {
                    return $type;
                }
            }
        }

        return 'unknown';
    }

    /**
     * 문서들에서 구조 정보 추출
     */
    private function extractStructuresFromDocuments(array $proposalDocuments): array
    {
        $extractedStructures = [];

        foreach ($proposalDocuments as $fileName => $document) {
            $structure = $this->extractStructureFromDocument(
                $document['content'], 
                $document['type'], 
                $fileName
            );

            if (!empty($structure['sections'])) {
                $extractedStructures[] = [
                    'source_file' => $fileName,
                    'document_type' => $document['type'],
                    'relevance_score' => $document['relevance_score'],
                    'structure' => $structure
                ];
            }
        }

        return $extractedStructures;
    }

    /**
     * 개별 문서에서 구조 추출
     */
    private function extractStructureFromDocument(string $content, string $documentType, string $fileName): array
    {
        $sections = [];

        // 구조 패턴들 (우선순위 순)
        $patterns = [
            // 숫자 패턴: 1. 2. 3.
            '/^(\d+)\.\s*(.+?)$/m',
            // 장 패턴: 제1장, 제2장
            '/^제?(\d+)장\s*(.+?)$/m',
            // 로마 숫자: I. II. III.
            '/^([IVX]+)\.\s*(.+?)$/m',
            // 한글 숫자: 가. 나. 다.
            '/^([가-힣])\.\s*(.+?)$/m',
            // 괄호 숫자: (1) (2) (3)
            '/^\((\d+)\)\s*(.+?)$/m',
            // 원 숫자: ① ② ③
            '/^([①-⑳])\s*(.+?)$/m'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $orderIndicator = $match[1];
                    $title = trim($match[2]);

                    if (strlen($title) > 3 && strlen($title) < 100) { // 합리적인 제목 길이
                        $sections[] = [
                            'order_indicator' => $orderIndicator,
                            'title' => $title,
                            'pattern_type' => $this->identifyPatternType($pattern),
                            'confidence' => $this->calculateTitleConfidence($title, $documentType)
                        ];
                    }
                }

                if (count($sections) >= 3) break; // 충분한 섹션 발견시 중단
            }
        }

        // 신뢰도 순으로 정렬
        usort($sections, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return [
            'sections' => $sections,
            'extraction_method' => 'pattern_matching',
            'total_sections' => count($sections),
            'confidence_avg' => count($sections) > 0 ? 
                array_sum(array_column($sections, 'confidence')) / count($sections) : 0
        ];
    }

    /**
     * 패턴 유형 식별
     */
    private function identifyPatternType(string $pattern): string
    {
        if (str_contains($pattern, '\d+')) return 'numeric';
        if (str_contains($pattern, 'IVX')) return 'roman';
        if (str_contains($pattern, '가-힣')) return 'korean';
        if (str_contains($pattern, '①-⑳')) return 'circled';
        return 'parenthesis';
    }

    /**
     * 제목 신뢰도 계산
     */
    private function calculateTitleConfidence(string $title, string $documentType): float
    {
        $confidence = 0.5; // 기본 신뢰도

        // 제안서 관련 키워드 가중치
        $proposalKeywords = [
            '사업' => 0.1, '개요' => 0.1, '이해도' => 0.1, '수행' => 0.1,
            '기술' => 0.1, '방안' => 0.1, '관리' => 0.1, '인력' => 0.1,
            '계획' => 0.1, '추진' => 0.1, '체계' => 0.1, '조직' => 0.1,
            '일정' => 0.1, '품질' => 0.1, '보안' => 0.1, '성능' => 0.1
        ];

        foreach ($proposalKeywords as $keyword => $weight) {
            if (str_contains($title, $keyword)) {
                $confidence += $weight;
            }
        }

        // 문서 유형별 가중치
        if ($documentType === 'rfp' || $documentType === 'specification') {
            $confidence += 0.2;
        }

        // 제목 길이 고려
        $lengthFactor = min(strlen($title) / 20, 1.0);
        $confidence *= $lengthFactor;

        return min($confidence, 1.0);
    }

    /**
     * 추출된 구조들을 통합
     */
    private function unifyProposalStructures(array $extractedStructures, array $tenderData): array
    {
        if (empty($extractedStructures)) {
            return $this->generateFallbackStructure($tenderData);
        }

        // 가장 신뢰도 높은 구조 선택
        $bestStructure = $extractedStructures[0];
        
        $unifiedSections = [];
        $order = 1;

        foreach ($bestStructure['structure']['sections'] as $section) {
            $unifiedSections[] = [
                'order' => $order++,
                'title' => $section['title'],
                'required' => true,
                'weight' => $this->calculateSectionWeight($section['title']),
                'confidence' => $section['confidence'],
                'source' => 'document_analysis'
            ];
        }

        return [
            'sections' => $unifiedSections,
            'total_sections' => count($unifiedSections),
            'estimated_pages' => $this->estimatePageCount($unifiedSections),
            'structure_complexity' => $this->calculateStructureComplexity($unifiedSections),
            'source' => 'unified_from_documents',
            'source_files' => array_column($extractedStructures, 'source_file'),
            'confidence_score' => $bestStructure['structure']['confidence_avg'] ?? 0.5
        ];
    }

    /**
     * 섹션 가중치 계산
     */
    private function calculateSectionWeight(string $title): float
    {
        $weightMap = [
            // 핵심 섹션들
            '사업' => 0.20, '개요' => 0.20, '이해' => 0.25, '수행' => 0.25,
            '기술' => 0.20, '방안' => 0.15, '관리' => 0.10, '계획' => 0.15,
            // 일반 섹션들
            '인력' => 0.08, '조직' => 0.08, '일정' => 0.08, '예산' => 0.08,
            '품질' => 0.05, '보안' => 0.05, '성능' => 0.05, '유지보수' => 0.05
        ];

        foreach ($weightMap as $keyword => $weight) {
            if (str_contains($title, $keyword)) {
                return $weight;
            }
        }

        return 0.05; // 기본 가중치
    }

    /**
     * 페이지 수 추정
     */
    private function estimatePageCount(array $sections): int
    {
        $basePages = 10; // 기본 페이지
        $pagesPerSection = 2; // 섹션당 평균 페이지
        
        return $basePages + (count($sections) * $pagesPerSection);
    }

    /**
     * 구조 복잡도 계산
     */
    private function calculateStructureComplexity(array $sections): string
    {
        $count = count($sections);
        $avgConfidence = count($sections) > 0 ? 
            array_sum(array_column($sections, 'confidence')) / count($sections) : 0;

        if ($count <= 8 && $avgConfidence > 0.7) return '낮음';
        if ($count <= 12 && $avgConfidence > 0.5) return '중간';
        return '높음';
    }

    /**
     * AI 기반 구조 최적화
     */
    private function optimizeStructureWithAI(array $structure, array $tenderData): array
    {
        try {
            // AI에게 구조 최적화 요청
            $prompt = $this->buildStructureOptimizationPrompt($structure, $tenderData);
            
            // AiApiService의 private 메서드를 호출할 수 없으므로 analyzeProposalStructure 사용
            $tenderDataForAi = [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'title' => $tenderData['title'] ?? '',
                'ntce_instt_nm' => $tenderData['ntce_instt_nm'] ?? ''
            ];
            
            $optimizedResult = $this->aiApiService->analyzeProposalStructure($tenderDataForAi, []);

            if (isset($optimizedResult['sections']) && count($optimizedResult['sections']) > 0) {
                // AI 최적화 결과 적용
                $structure['sections'] = $optimizedResult['sections'];
                $structure['ai_optimized'] = true;
                $structure['optimization_notes'] = $optimizedResult['optimization_notes'] ?? [];
            }

        } catch (Exception $e) {
            Log::warning('AI 구조 최적화 실패, 원본 구조 유지', [
                'error' => $e->getMessage()
            ]);
        }

        return $structure;
    }

    /**
     * 구조 최적화 프롬프트 생성
     */
    private function buildStructureOptimizationPrompt(array $structure, array $tenderData): string
    {
        $sectionsText = '';
        foreach ($structure['sections'] as $section) {
            $sectionsText .= "- " . $section['title'] . " (가중치: " . $section['weight'] . ")\n";
        }

        return "다음은 첨부파일에서 추출한 제안서 구조입니다. 이를 최적화해주세요.

공고 정보:
- 공고명: " . ($tenderData['title'] ?? '') . "
- 발주기관: " . ($tenderData['ntce_instt_nm'] ?? '') . "

현재 구조:
{$sectionsText}

다음 JSON 형식으로 최적화된 구조를 제공해주세요:
{
    \"sections\": [
        {\"order\": 1, \"title\": \"섹션제목\", \"required\": true, \"weight\": 0.15}
    ],
    \"optimization_notes\": [\"개선사항1\", \"개선사항2\"]
}";
    }

    /**
     * 폴백 구조 생성 (공고 유형 기반)
     */
    private function generateFallbackStructure(array $tenderData): array
    {
        $title = $tenderData['title'] ?? '';
        $isSystemProject = str_contains($title, '시스템') || str_contains($title, '구축');
        $isDataProject = str_contains($title, '데이터') || str_contains($title, 'DB');

        $baseSections = [
            ['order' => 1, 'title' => '사업 개요', 'required' => true, 'weight' => 0.15],
            ['order' => 2, 'title' => '사업 이해도', 'required' => true, 'weight' => 0.20],
            ['order' => 3, 'title' => '사업 수행 방안', 'required' => true, 'weight' => 0.25],
            ['order' => 4, 'title' => '기술 제안', 'required' => true, 'weight' => 0.20],
            ['order' => 5, 'title' => '프로젝트 관리', 'required' => true, 'weight' => 0.10],
            ['order' => 6, 'title' => '투입 인력', 'required' => true, 'weight' => 0.10]
        ];

        // 프로젝트 유형별 추가 섹션
        if ($isSystemProject) {
            $baseSections[] = ['order' => 7, 'title' => '시스템 아키텍처', 'required' => true, 'weight' => 0.08];
        }

        if ($isDataProject) {
            $baseSections[] = ['order' => 8, 'title' => '데이터 관리 방안', 'required' => true, 'weight' => 0.08];
        }

        return [
            'sections' => $baseSections,
            'total_sections' => count($baseSections),
            'estimated_pages' => 15 + count($baseSections),
            'structure_complexity' => '중간',
            'source' => 'fallback_generated',
            'confidence_score' => 0.6
        ];
    }

    /**
     * 구조 분석 통계
     */
    public function getAnalysisStats(): array
    {
        return [
            'supported_document_types' => ['rfp', 'specification', 'notice', 'evaluation', 'template'],
            'pattern_types' => ['numeric', 'roman', 'korean', 'circled', 'parenthesis'],
            'confidence_threshold' => 0.3,
            'min_sections' => 3,
            'max_sections' => 15
        ];
    }
}
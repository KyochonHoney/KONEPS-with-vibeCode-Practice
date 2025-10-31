<?php

// [BEGIN nara:ai_api_service]
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * AI API 통합 서비스 (OpenAI GPT-4 / Claude API)
 * 
 * @package App\Services
 */
class AiApiService
{
    private string $provider;
    private int $cacheTime;
    private int $retryAttempts;
    private int $timeout;

    public function __construct()
    {
        $this->provider = $this->determineProvider();
        $this->cacheTime = config('ai.analysis.cache_ttl', 3600);
        $this->retryAttempts = config('ai.analysis.retry_attempts', 3);
        $this->timeout = config('ai.analysis.timeout', 60);
    }

    /**
     * 사용할 AI 프로바이더 결정 (API 키 존재 여부 기반)
     */
    private function determineProvider(): string
    {
        $configuredProvider = config('ai.analysis.provider', 'mock');
        
        // Mock이 명시적으로 설정된 경우
        if ($configuredProvider === 'mock') {
            return 'mock';
        }
        
        // OpenAI 설정 확인
        if ($configuredProvider === 'openai') {
            $openaiKey = config('ai.openai.api_key');
            if (!empty($openaiKey) && $openaiKey !== 'your_openai_api_key_here') {
                return 'openai';
            }
        }
        
        // Claude 설정 확인
        if ($configuredProvider === 'claude') {
            $claudeKey = config('ai.claude.api_key');
            if (!empty($claudeKey) && $claudeKey !== 'your_claude_api_key_here') {
                return 'claude';
            }
        }
        
        // API 키가 없으면 자동으로 Mock 사용
        Log::info('AI API 키가 설정되지 않아 Mock 모드 사용', [
            'configured_provider' => $configuredProvider
        ]);
        
        return 'mock';
    }

    /**
     * API 연결 테스트
     * 
     * @return bool 연결 가능 여부
     */
    public function testConnection(): bool
    {
        try {
            if ($this->provider === 'mock') {
                return true;
            }
            
            $testPrompt = "안녕하세요. API 연결 테스트입니다.";
            $result = $this->callAiApi($testPrompt, 'connection_test', 1); // 1 토큰으로 제한
            
            return !empty($result['response']);
            
        } catch (Exception $e) {
            Log::error('AI API 연결 테스트 실패', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 과업지시서 요구사항 추출 및 분석
     * 
     * @param string $taskInstructionContent 과업지시서 내용
     * @param array $tenderData 기본 공고 데이터
     * @return array 추출된 요구사항 및 분석 결과
     */
    public function analyzeTaskInstruction(string $taskInstructionContent, array $tenderData = []): array
    {
        try {
            Log::info('과업지시서 AI 분석 시작', [
                'tender_no' => $tenderData['tender_no'] ?? 'UNKNOWN',
                'content_length' => strlen($taskInstructionContent)
            ]);

            // 캐시 키 생성
            $cacheKey = $this->generateCacheKey('task_instruction', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'content_hash' => md5($taskInstructionContent)
            ]);

            // 캐시된 결과 확인
            if ($cached = Cache::get($cacheKey)) {
                Log::info('과업지시서 분석 캐시 사용', ['tender_no' => $tenderData['tender_no'] ?? '']);
                return $cached;
            }

            $prompt = $this->buildTaskInstructionAnalysisPrompt($taskInstructionContent, $tenderData);
            
            $result = $this->callAiApi($prompt, 'task_instruction_analysis');
            
            // 결과를 구조화된 형태로 파싱
            $parsedResult = $this->parseTaskInstructionAnalysis($result['response'] ?? '');
            
            // 결과 캐싱
            Cache::put($cacheKey, $parsedResult, $this->cacheTime);
            
            Log::info('과업지시서 AI 분석 완료', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'provider' => $this->provider,
                'extracted_requirements_count' => count($parsedResult['technical_requirements'] ?? [])
            ]);
            
            return $parsedResult;
            
        } catch (Exception $e) {
            Log::error('과업지시서 AI 분석 실패', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'error' => $e->getMessage()
            ]);
            
            // 실패 시 기본 분석 결과 반환
            return $this->generateFallbackTaskInstructionAnalysis($taskInstructionContent, $tenderData);
        }
    }

    /**
     * AI 기반 입찰공고 분석 수행
     * 
     * @param array $tenderData 입찰공고 데이터
     * @param array $companyProfile 회사 프로필 데이터
     * @param array $attachmentContent 첨부파일 내용 (선택사항)
     * @return array AI 분석 결과
     */
    public function analyzeTender(array $tenderData, array $companyProfile, array $attachmentContent = []): array
    {
        try {
            // 캐시 키 생성
            $cacheKey = $this->generateCacheKey('tender_analysis', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'company_id' => $companyProfile['id'] ?? 'default',
                'attachments_hash' => md5(json_encode($attachmentContent))
            ]);

            // 캐시된 결과 확인
            if ($cached = Cache::get($cacheKey)) {
                Log::info('AI 분석 캐시 사용', ['tender_no' => $tenderData['tender_no'] ?? '']);
                return $cached;
            }

            $prompt = $this->buildTenderAnalysisPrompt($tenderData, $companyProfile, $attachmentContent);
            
            $result = $this->callAiApi($prompt, 'tender_analysis');
            
            // 결과 캐싱
            Cache::put($cacheKey, $result, $this->cacheTime);
            
            Log::info('AI 분석 완료', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'provider' => $this->provider,
                'score' => $result['compatibility_score'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('AI 분석 실패', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'error' => $e->getMessage()
            ]);

            // 폴백: 기본 분석 결과 반환
            return $this->getFallbackAnalysis($tenderData, $companyProfile);
        }
    }

    /**
     * 첨부파일 AI 분석
     * 
     * @param string $content 파일 내용
     * @param string $fileName 파일명
     * @return array 분석 결과
     */
    public function analyzeAttachment(string $content, string $fileName): array
    {
        try {
            $cacheKey = $this->generateCacheKey('attachment_analysis', [
                'file' => $fileName,
                'content_hash' => md5($content)
            ]);

            if ($cached = Cache::get($cacheKey)) {
                return $cached;
            }

            $prompt = $this->buildAttachmentAnalysisPrompt($content, $fileName);
            $result = $this->callAiApi($prompt, 'attachment_analysis');
            
            Cache::put($cacheKey, $result, $this->cacheTime);
            return $result;

        } catch (Exception $e) {
            Log::error('첨부파일 AI 분석 실패', ['file' => $fileName, 'error' => $e->getMessage()]);
            return $this->getFallbackAttachmentAnalysis($fileName);
        }
    }

    /**
     * 기술스택 정보 AI 분석
     * 
     * @param array $techStackData 기술스택 데이터
     * @return array 정규화된 기술스택 정보
     */
    public function analyzeTechStack(array $techStackData): array
    {
        try {
            $prompt = $this->buildTechStackAnalysisPrompt($techStackData);
            return $this->callAiApi($prompt, 'tech_stack_analysis');

        } catch (Exception $e) {
            Log::error('기술스택 AI 분석 실패', ['error' => $e->getMessage()]);
            return ['technologies' => $techStackData, 'categories' => []];
        }
    }

    /**
     * AI API 호출 (OpenAI/Claude 자동 선택)
     * 
     * @param string $prompt 프롬프트
     * @param string $analysisType 분석 타입
     * @return array 응답 데이터
     */
    private function callAiApi(string $prompt, string $analysisType): array
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $attempt++;

                if ($this->provider === 'openai') {
                    return $this->callOpenAiApi($prompt, $analysisType);
                } elseif ($this->provider === 'claude') {
                    return $this->callClaudeApi($prompt, $analysisType);
                } elseif ($this->provider === 'mock') {
                    return $this->callMockAiApi($prompt, $analysisType);
                } else {
                    throw new Exception("지원하지 않는 AI 프로바이더: {$this->provider}");
                }

            } catch (Exception $e) {
                $lastError = $e;
                Log::warning("AI API 호출 실패 (시도 {$attempt}/{$this->retryAttempts})", [
                    'provider' => $this->provider,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $this->retryAttempts) {
                    sleep(pow(2, $attempt)); // 지수적 백오프
                }
            }
        }

        // 모든 시도 실패 시 Mock으로 폴백 (설정되어 있는 경우)
        if (config('ai.analysis.fallback_to_mock', true) && $this->provider !== 'mock') {
            Log::warning('실제 AI API 호출 실패, Mock으로 폴백', [
                'original_provider' => $this->provider,
                'error' => $lastError->getMessage()
            ]);
            
            try {
                return $this->callMockAiApi($prompt, $analysisType);
            } catch (Exception $mockError) {
                Log::error('Mock AI도 실패', ['error' => $mockError->getMessage()]);
            }
        }

        throw new Exception("AI API 호출 최종 실패: " . $lastError->getMessage());
    }

    /**
     * OpenAI API 호출
     * 
     * @param string $prompt 프롬프트
     * @param string $analysisType 분석 타입
     * @return array 응답 데이터
     */
    private function callOpenAiApi(string $prompt, string $analysisType): array
    {
        $apiKey = config('ai.openai.api_key');
        if (!$apiKey) {
            throw new Exception('OpenAI API 키가 설정되지 않았습니다');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Organization' => config('ai.openai.organization', '')
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('ai.openai.model', 'gpt-4-turbo-preview'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt($analysisType)
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => config('ai.openai.max_tokens', 4096),
                'temperature' => config('ai.openai.temperature', 0.3),
                'response_format' => ['type' => 'json_object']
            ]);

        if (!$response->successful()) {
            throw new Exception('OpenAI API 호출 실패: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new Exception('OpenAI API 응답이 비어있습니다');
        }

        return json_decode($content, true) ?: [];
    }

    /**
     * Claude API 호출
     * 
     * @param string $prompt 프롬프트
     * @param string $analysisType 분석 타입
     * @return array 응답 데이터
     */
    private function callClaudeApi(string $prompt, string $analysisType): array
    {
        $apiKey = config('ai.claude.api_key');
        if (!$apiKey) {
            throw new Exception('Claude API 키가 설정되지 않았습니다');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('ai.claude.model', 'claude-3-5-sonnet-20241022'),
                'max_tokens' => config('ai.claude.max_tokens', 4096),
                'system' => $this->getSystemPrompt($analysisType),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

        if (!$response->successful()) {
            throw new Exception('Claude API 호출 실패: ' . $response->body());
        }

        $data = $response->json();
        $content = $data['content'][0]['text'] ?? '';

        if (empty($content)) {
            throw new Exception('Claude API 응답이 비어있습니다');
        }

        // JSON 응답 추출 (Claude는 텍스트로 응답하므로 JSON 부분만 파싱)
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            return json_decode($matches[1], true) ?: [];
        }

        // JSON 블록이 없으면 전체 응답을 JSON으로 파싱 시도
        return json_decode($content, true) ?: ['raw_response' => $content];
    }

    /**
     * Mock AI API 호출 (테스트용)
     * 
     * @param string $prompt 프롬프트
     * @param string $analysisType 분석 타입
     * @return array 가상 응답 데이터
     */
    private function callMockAiApi(string $prompt, string $analysisType): array
    {
        // 실제 AI 호출 시뮬레이션을 위한 지연
        usleep(500000); // 0.5초 대기 (실제 API 호출처럼)

        Log::info('Mock AI API 호출', [
            'provider' => 'mock',
            'analysis_type' => $analysisType,
            'prompt_length' => strlen($prompt)
        ]);

        return match($analysisType) {
            'tender_analysis' => $this->generateMockTenderAnalysis($prompt),
            'attachment_analysis' => $this->generateMockAttachmentAnalysis($prompt),
            'tech_stack_analysis' => $this->generateMockTechStackAnalysis($prompt),
            'proposal_structure_analysis' => $this->generateMockProposalStructure($prompt),
            'proposal_generation' => $this->generateMockProposal($prompt),
            'connection_test' => [
                'status' => 'connected',
                'provider' => 'mock',
                'message' => 'Mock AI API connection successful'
            ],
            default => $this->generateMockGenericResponse($prompt)
        };
    }

    /**
     * Mock 입찰공고 분석 결과 생성
     */
    private function generateMockTenderAnalysis(string $prompt): array
    {
        // 프롬프트에서 공고 정보 추출하여 더 현실적인 분석 결과 생성
        $lowerPrompt = strtolower($prompt);
        $isWebProject = str_contains($lowerPrompt, '웹') || str_contains($lowerPrompt, 'web') || 
                       str_contains($lowerPrompt, '홈페이지') || str_contains($lowerPrompt, '시스템');
        $isDataProject = str_contains($lowerPrompt, '데이터') || str_contains($lowerPrompt, 'xml') || 
                        str_contains($lowerPrompt, 'json') || str_contains($lowerPrompt, '데이터베이스');
        $isAiProject = str_contains($lowerPrompt, 'ai') || str_contains($lowerPrompt, '인공지능') || 
                      str_contains($lowerPrompt, '머신러닝') || str_contains($lowerPrompt, 'ml');
        
        // 기본 점수 설정 (프로젝트 유형에 따라 조정)
        $baseCompatibility = 70;
        $baseTechnical = 75;
        $baseBusiness = 65;
        
        if ($isWebProject) {
            $baseCompatibility += 15;
            $baseTechnical += 20;
            $baseBusiness += 10;
        } elseif ($isDataProject) {
            $baseCompatibility += 10;
            $baseTechnical += 15;
            $baseBusiness += 15;
        } elseif ($isAiProject) {
            $baseCompatibility += 20;
            $baseTechnical += 25;
            $baseBusiness += 20;
        }
        
        // 랜덤 변동 (-5 ~ +5)
        $variance = rand(-5, 5);
        $compatibilityScore = min(100, max(30, $baseCompatibility + $variance));
        $technicalScore = min(100, max(30, $baseTechnical + $variance));
        $businessScore = min(100, max(30, $baseBusiness + $variance));
        
        // 성공 확률 계산
        $successProbability = min(95, max(30, ($compatibilityScore + $technicalScore + $businessScore) / 3));
        
        return [
            'compatibility_score' => $compatibilityScore,
            'technical_match_score' => $technicalScore,
            'business_match_score' => $businessScore,
            'risk_score' => 100 - $compatibilityScore,
            'opportunity_score' => $compatibilityScore + rand(-10, 10),
            'detailed_analysis' => [
                'strengths' => $this->getMockStrengths($isWebProject, $isDataProject, $isAiProject),
                'weaknesses' => $this->getMockWeaknesses($compatibilityScore),
                'opportunities' => $this->getMockOpportunities($isWebProject, $isDataProject, $isAiProject),
                'risks' => $this->getMockRisks($compatibilityScore)
            ],
            'required_technologies' => $this->getMockRequiredTechnologies($isWebProject, $isDataProject, $isAiProject),
            'matching_technologies' => $this->getMockMatchingTechnologies($isWebProject, $isDataProject, $isAiProject),
            'missing_technologies' => $this->getMockMissingTechnologies($compatibilityScore),
            'recommendation' => $this->generateMockRecommendation($compatibilityScore),
            'success_probability' => (int) $successProbability,
            'estimated_effort' => $this->getMockEstimatedEffort($compatibilityScore),
            'key_considerations' => $this->getMockKeyConsiderations($isWebProject, $isDataProject, $isAiProject),
            'is_mock' => true,
            'mock_analysis_basis' => $this->getMockAnalysisBasis($isWebProject, $isDataProject, $isAiProject)
        ];
    }

    /**
     * Mock 첨부파일 분석 결과 생성
     */
    private function generateMockAttachmentAnalysis(string $prompt): array
    {
        $hasJava = str_contains(strtolower($prompt), 'java');
        $hasPhp = str_contains(strtolower($prompt), 'php');
        $lowerPrompt = strtolower($prompt);
        $hasDatabase = str_contains($lowerPrompt, 'mysql') || str_contains($lowerPrompt, 'database') || str_contains($lowerPrompt, '데이터베이스');
        
        return [
            'required_technologies' => $hasJava ? ['Java', 'Spring Boot', 'MySQL'] : 
                                    ($hasPhp ? ['PHP', 'Laravel', 'MySQL'] : ['웹 개발', '데이터베이스']),
            'development_scope' => '웹 애플리케이션 개발 및 데이터베이스 구축',
            'project_duration' => rand(3, 12) . '개월',
            'complexity_level' => ['낮음', '중간', '높음'][rand(0, 2)],
            'key_requirements' => [
                '사용자 관리 시스템',
                '데이터 처리 및 분석',
                '관리자 대시보드',
                '보고서 생성 기능'
            ],
            'special_notes' => [
                'Mock AI 분석 결과입니다',
                '실제 과업지시서 분석을 위해서는 실제 API 키가 필요합니다',
                '현재는 테스트용 가상 데이터를 표시하고 있습니다'
            ],
            'is_mock' => true
        ];
    }

    /**
     * Mock 기술스택 분석 결과 생성
     */
    private function generateMockTechStackAnalysis(string $prompt): array
    {
        return [
            'frontend' => ['React', 'Vue.js', 'JavaScript', 'HTML5', 'CSS3'],
            'backend' => ['PHP', 'Laravel', 'Node.js', 'Python'],
            'database' => ['MySQL', 'PostgreSQL', 'MongoDB'],
            'cloud' => ['AWS', 'Azure', 'Google Cloud'],
            'tools' => ['Git', 'Docker', 'Jenkins'],
            'mobile' => ['React Native', 'Flutter'],
            'other' => ['API 개발', '시스템 통합'],
            'is_mock' => true
        ];
    }

    /**
     * Mock 일반 응답 생성
     */
    private function generateMockGenericResponse(string $prompt): array
    {
        return [
            'response' => 'Mock AI 응답입니다. 실제 AI 분석을 위해서는 OpenAI 또는 Claude API 키가 필요합니다.',
            'prompt_received' => substr($prompt, 0, 100) . '...',
            'is_mock' => true,
            'provider' => 'mock'
        ];
    }

    // Mock 데이터 생성 헬퍼 메서드들
    private function getMockStrengths(bool $isWeb, bool $isData, bool $isAi): array
    {
        $baseStrengths = ['Java 전문 개발회사 (15년 경력)', '정부기관 SI 프로젝트 전문', '시스템 유지보수 최고 수준', '안정적인 기술 스택'];
        
        if ($isWeb) $baseStrengths[] = 'Java/Spring 기반 웹 시스템 개발 전문성';
        if ($isData) $baseStrengths[] = 'Oracle/MySQL 데이터베이스 전문 관리';
        if ($isAi) $baseStrengths[] = '시스템 통합 및 API 개발 경험'; // AI 대신 현실적인 강점
        
        return array_slice($baseStrengths, 0, rand(3, 5));
    }
    
    private function getMockWeaknesses(int $score): array
    {
        $weaknesses = [
            '새로운 기술 스택 학습 필요',
            '프로젝트 규모 대비 팀 규모 검토 필요',
            '특화된 도메인 지식 부족'
        ];
        
        return $score < 70 ? array_slice($weaknesses, 0, 2) : [array_rand($weaknesses, 1)];
    }
    
    private function getMockOpportunities(bool $isWeb, bool $isData, bool $isAi): array
    {
        $opportunities = ['시장 확장 기회', '기술 역량 강화', '고객사와의 장기 파트너십'];
        
        if ($isAi) $opportunities[] = 'AI 전문성 확보 기회';
        if ($isData) $opportunities[] = '빅데이터 분석 역량 확보';
        
        return array_slice($opportunities, 0, rand(2, 4));
    }
    
    private function getMockRisks(int $score): array
    {
        $risks = ['기술적 복잡도', '일정 지연 위험', '요구사항 변경 위험'];
        
        if ($score < 60) {
            $risks[] = '기술 역량 부족 위험';
            $risks[] = '경쟁사 대비 차별화 부족';
        }
        
        return array_slice($risks, 0, $score > 70 ? 2 : 4);
    }
    
    private function getMockRequiredTechnologies(bool $isWeb, bool $isData, bool $isAi): array
    {
        $base = ['시스템 개발'];
        
        if ($isWeb) $base = array_merge($base, ['Java', 'Spring', 'MySQL', 'Tomcat']);
        if ($isData) $base = array_merge($base, ['데이터베이스 설계', 'SQL', 'Oracle', 'XML/JSON']);
        if ($isAi) $base = array_merge($base, ['Java', '시스템 통합', 'API 개발']);
        
        return array_unique($base);
    }
    
    private function getMockMatchingTechnologies(bool $isWeb, bool $isData, bool $isAi): array
    {
        $matching = ['Java', 'Spring', 'MySQL', 'Oracle'];
        
        if ($isWeb) $matching[] = 'Tomcat';
        if ($isData) $matching[] = 'SQL';
        
        return $matching;
    }
    
    private function getMockMissingTechnologies(int $score): array
    {
        if ($score > 80) return [];
        if ($score > 60) return ['Python', 'Node.js'];
        
        return ['Python', 'React', 'Docker', 'Kubernetes'];
    }
    
    private function generateMockRecommendation(int $score): string
    {
        if ($score >= 80) {
            return '매우 적합한 프로젝트입니다. 적극적으로 참여를 검토해보세요. 기존 기술 스택으로 충분히 수행 가능합니다.';
        } elseif ($score >= 60) {
            return '적합한 프로젝트입니다. 일부 기술 보완을 통해 성공적으로 수행할 수 있을 것으로 판단됩니다.';
        } else {
            return '신중한 검토가 필요한 프로젝트입니다. 기술적 위험도와 투입 리소스를 면밀히 검토해보세요.';
        }
    }
    
    private function getMockEstimatedEffort(): string
    {
        return ['낮음', '중간', '높음'][rand(0, 2)];
    }
    
    private function getMockKeyConsiderations(bool $isWeb, bool $isData, bool $isAi): array
    {
        $considerations = ['프로젝트 일정 검토', '팀 역량 강화 방안'];
        
        if ($isAi) $considerations[] = '시스템 통합 및 API 연동 계획';
        if ($isData) $considerations[] = '데이터 보안 및 개인정보 보호';
        
        return $considerations;
    }
    
    private function getMockAnalysisBasis(bool $isWeb, bool $isData, bool $isAi): string
    {
        $basis = '프롬프트 키워드 분석 기반: ';
        $types = [];
        
        if ($isWeb) $types[] = 'Java 웹 개발';
        if ($isData) $types[] = 'DB/데이터 처리';
        if ($isAi) $types[] = '시스템 통합';
        
        return $basis . (empty($types) ? '일반 프로젝트' : implode(', ', $types));
    }

    /**
     * Mock 제안서 구조 분석 생성
     */
    private function generateMockProposalStructure(string $prompt): array
    {
        $lowerPrompt = strtolower($prompt);
        $isSystemProject = str_contains($lowerPrompt, '시스템') || str_contains($lowerPrompt, 'system');
        $isWebProject = str_contains($lowerPrompt, '웹') || str_contains($lowerPrompt, 'web') || str_contains($lowerPrompt, '홈페이지');
        $isDataProject = str_contains($lowerPrompt, '데이터') || str_contains($lowerPrompt, 'xml') || str_contains($lowerPrompt, 'database');
        
        // 기본 제안서 구조
        $baseSections = [
            ['order' => 1, 'title' => '사업 개요', 'required' => true, 'weight' => 0.15],
            ['order' => 2, 'title' => '사업 이해도', 'required' => true, 'weight' => 0.20],
            ['order' => 3, 'title' => '사업 수행 방안', 'required' => true, 'weight' => 0.25],
            ['order' => 4, 'title' => '기술 제안', 'required' => true, 'weight' => 0.20],
            ['order' => 5, 'title' => '프로젝트 관리', 'required' => true, 'weight' => 0.10],
            ['order' => 6, 'title' => '투입 인력', 'required' => true, 'weight' => 0.10]
        ];
        
        // 프로젝트 유형별 추가 섹션
        if ($isSystemProject || $isWebProject) {
            $baseSections[] = ['order' => 7, 'title' => '유지보수 방안', 'required' => false, 'weight' => 0.08];
            $baseSections[] = ['order' => 8, 'title' => '교육 및 지원', 'required' => false, 'weight' => 0.07];
        }
        
        if ($isDataProject) {
            $baseSections[] = ['order' => 7, 'title' => '데이터 관리 방안', 'required' => true, 'weight' => 0.12];
            $baseSections[] = ['order' => 8, 'title' => '보안 및 백업', 'required' => true, 'weight' => 0.10];
        }
        
        // 공통 마지막 섹션들
        $baseSections[] = ['order' => 9, 'title' => '기대 효과', 'required' => false, 'weight' => 0.05];
        $baseSections[] = ['order' => 10, 'title' => '회사 소개', 'required' => true, 'weight' => 0.05];
        
        return [
            'sections' => $baseSections,
            'total_sections' => count($baseSections),
            'estimated_pages' => rand(15, 25),
            'structure_complexity' => $this->calculateStructureComplexity($baseSections),
            'special_requirements' => $this->getMockSpecialRequirements($isSystemProject, $isWebProject, $isDataProject),
            'is_mock' => true
        ];
    }

    /**
     * Mock 제안서 생성
     */
    private function generateMockProposal(string $prompt): array
    {
        $lowerPrompt = strtolower($prompt);
        
        // 프로젝트 유형 분석
        $projectType = 'general';
        if (str_contains($lowerPrompt, '웹') || str_contains($lowerPrompt, 'web')) {
            $projectType = 'web';
        } elseif (str_contains($lowerPrompt, '데이터') || str_contains($lowerPrompt, 'xml')) {
            $projectType = 'data';
        } elseif (str_contains($lowerPrompt, '시스템') || str_contains($lowerPrompt, 'system')) {
            $projectType = 'system';
        }
        
        // Mock 제안서 내용 생성
        $content = $this->generateMockProposalContent($projectType, $prompt);
        
        return [
            'title' => $this->generateMockProposalTitle($projectType),
            'content' => $content,
            'sections_generated' => rand(8, 12),
            'estimated_pages' => rand(18, 28),
            'content_length' => strlen($content),
            'confidence_score' => rand(85, 95),
            'generation_quality' => ['높음', '매우 높음'][rand(0, 1)],
            'ai_improvements' => [
                '공고 요구사항에 맞춘 맞춤형 내용 생성',
                '타이드플로 강점 부각',
                'Java 전문 역량 강조',
                '정부기관 경험 활용'
            ],
            'processing_notes' => [
                'Mock AI 제안서 생성 완료',
                '실제 API 사용 시 더욱 정교한 내용 생성',
                '첨부파일 분석 결과 반영 예정'
            ],
            'is_mock' => true
        ];
    }

    /**
     * Mock 제안서 내용 생성
     */
    private function generateMockProposalContent(string $projectType, string $prompt): string
    {
        $baseContent = file_get_contents(base_path('../docs/templates/proposal-template.md'));
        
        // 프로젝트 유형별 내용 치환
        $replacements = match($projectType) {
            'web' => [
                '{PROJECT_NAME}' => 'Java/Spring 기반 웹 시스템 구축',
                '{PROJECT_PURPOSE}' => '효율적이고 안정적인 웹 기반 업무시스템 구축을 통한 업무 효율성 향상',
                '{PROJECT_SCOPE}' => 'Java Spring Framework를 활용한 웹 애플리케이션 개발, 데이터베이스 구축, 시스템 통합',
                '{TECHNICAL_APPROACH}' => 'Java/Spring Boot 기반 RESTful API 설계, MySQL/Oracle 데이터베이스 연동, 반응형 웹 인터페이스 구현',
                '{TECHNOLOGY_STACK}' => 'Java 8+, Spring Boot, Spring Security, MySQL/Oracle, Tomcat, JavaScript/jQuery',
                '{COMPANY_ACHIEVEMENTS}' => '15년간 Java 기반 웹 시스템 개발 전문, 정부기관 프로젝트 50여건 수행'
            ],
            'data' => [
                '{PROJECT_NAME}' => '데이터 처리 및 관리 시스템 구축',
                '{PROJECT_PURPOSE}' => '체계적인 데이터 관리 및 효율적인 정보 활용을 위한 시스템 구축',
                '{PROJECT_SCOPE}' => 'XML/JSON 데이터 처리, 데이터베이스 설계, 데이터 마이그레이션, 관리시스템 개발',
                '{TECHNICAL_APPROACH}' => 'Java 기반 데이터 처리 엔진, Oracle/MySQL 데이터베이스 최적화, ETL 프로세스 구현',
                '{TECHNOLOGY_STACK}' => 'Java, Spring Batch, Oracle/MySQL, XML/JSON Parser, Apache POI',
                '{COMPANY_ACHIEVEMENTS}' => '대용량 데이터 처리 프로젝트 30여건, Oracle/MySQL 전문 관리 경험 15년'
            ],
            'system' => [
                '{PROJECT_NAME}' => '통합 시스템 구축',
                '{PROJECT_PURPOSE}' => '기존 시스템 간 연동 및 통합을 통한 업무 효율성 극대화',
                '{PROJECT_SCOPE}' => '시스템 통합, API 연동, 레거시 시스템 현대화, 인터페이스 구축',
                '{TECHNICAL_APPROACH}' => 'Java 기반 시스템 통합, RESTful API 설계, 메시지 큐 활용, 마이크로서비스 아키텍처',
                '{TECHNOLOGY_STACK}' => 'Java, Spring Boot, Apache Kafka, Redis, Docker, Jenkins',
                '{COMPANY_ACHIEVEMENTS}' => 'SI 프로젝트 전문 15년, 대기업/정부기관 시스템 통합 40여건 수행'
            ],
            default => [
                '{PROJECT_NAME}' => 'Java 기반 시스템 개발',
                '{PROJECT_PURPOSE}' => '안정적이고 확장 가능한 시스템 구축',
                '{PROJECT_SCOPE}' => 'Java 기반 시스템 개발, 데이터베이스 구축, 시스템 유지보수',
                '{TECHNICAL_APPROACH}' => 'Java/Spring Framework 기반 개발, 객체지향 설계 원칙 적용',
                '{TECHNOLOGY_STACK}' => 'Java, Spring Framework, MySQL/Oracle, Apache Tomcat',
                '{COMPANY_ACHIEVEMENTS}' => 'Java 전문 개발회사 15년 경력, 안정적인 시스템 구축 전문'
            ]
        };
        
        // 공통 치환값들
        $commonReplacements = [
            '{PROJECT_BACKGROUND}' => '정부기관의 디지털 전환 및 업무 효율성 향상 요구에 따른 시스템 현대화 필요성 대두',
            '{REQUIREMENTS_ANALYSIS}' => '발주기관의 핵심 요구사항을 정확히 파악하고, 현재 업무 프로세스 분석을 통한 최적 솔루션 제시',
            '{EXECUTION_STRATEGY}' => '애자일 방법론 기반 단계적 개발, 지속적인 소통을 통한 요구사항 반영, 품질 중심의 개발 프로세스',
            '{QUALITY_MANAGEMENT}' => '코드 리뷰, 단위 테스트, 통합 테스트를 통한 품질 보증, 형상관리 및 버전 관리 체계 구축',
            '{PROJECT_MANAGER}' => 'Java 개발 경력 10년 이상의 숙련된 프로젝트 매니저, PMP 자격증 보유',
            '{TECHNICAL_TEAM}' => 'Java/Spring 전문 개발자 5명, 데이터베이스 전문가 2명, 시스템 분석가 1명',
            '{MAINTENANCE_SYSTEM}' => '24시간 모니터링 체계, 단계별 유지보수 프로세스, 신속한 장애 대응',
            '{USER_TRAINING}' => '사용자 맞춤형 교육 프로그램, 매뉴얼 제공, 실습 위주의 교육 과정',
            '{QUANTITATIVE_BENEFITS}' => '업무 처리 시간 30% 단축, 데이터 정확도 95% 이상, 시스템 응답속도 2초 이내',
            '{QUALITATIVE_BENEFITS}' => '업무 효율성 향상, 사용자 만족도 증대, 데이터 신뢰성 확보',
            '{COMPANY_CERTIFICATIONS}' => 'SW개발업체 신고확인증, 정보보호 관리체계 인증(ISMS), ISO 27001 인증'
        ];
        
        $allReplacements = array_merge($replacements, $commonReplacements);
        
        return str_replace(array_keys($allReplacements), array_values($allReplacements), $baseContent);
    }

    /**
     * Mock 제안서 제목 생성
     */
    private function generateMockProposalTitle(string $projectType): string
    {
        return match($projectType) {
            'web' => '웹 시스템 구축 제안서 - 타이드플로',
            'data' => '데이터 관리 시스템 구축 제안서 - 타이드플로',
            'system' => '시스템 통합 구축 제안서 - 타이드플로',
            default => '시스템 개발 제안서 - 타이드플로'
        };
    }

    /**
     * 구조 복잡도 계산
     */
    private function calculateStructureComplexity(array $sections): string
    {
        $count = count($sections);
        if ($count <= 8) return '낮음';
        if ($count <= 12) return '중간';
        return '높음';
    }

    /**
     * Mock 특별 요구사항
     */
    private function getMockSpecialRequirements(bool $isSystem, bool $isWeb, bool $isData): array
    {
        $requirements = ['정부기관 보안 요구사항 준수'];
        
        if ($isSystem) $requirements[] = '시스템 통합 방안';
        if ($isWeb) $requirements[] = '웹 접근성 준수';
        if ($isData) $requirements[] = '개인정보보호법 준수';
        
        return $requirements;
    }

    /**
     * 입찰공고 분석 프롬프트 생성
     */
    private function buildTenderAnalysisPrompt(array $tenderData, array $companyProfile, array $attachmentContent): string
    {
        $prompt = "다음 나라장터 용역공고를 타이드플로 회사의 역량과 비교하여 정밀 분석해주세요.\n\n";
        
        $prompt .= "## 입찰공고 정보\n";
        $prompt .= "- 공고번호: " . ($tenderData['tender_no'] ?? 'N/A') . "\n";
        $prompt .= "- 공고명: " . ($tenderData['title'] ?? 'N/A') . "\n";
        $prompt .= "- 발주기관: " . ($tenderData['ntce_instt_nm'] ?? 'N/A') . "\n";
        $prompt .= "- 예정가격: " . ($tenderData['budget'] ?? 'N/A') . "\n";
        $prompt .= "- 공고내용: " . ($tenderData['ntce_cont'] ?? 'N/A') . "\n";

        if (!empty($attachmentContent)) {
            $prompt .= "\n## 첨부파일 분석 내용\n";
            foreach ($attachmentContent as $fileName => $content) {
                $prompt .= "### {$fileName}\n";
                $prompt .= substr($content, 0, 2000) . (strlen($content) > 2000 ? '...' : '') . "\n\n";
            }
        }

        $prompt .= "\n## 타이드플로 회사 프로필\n";
        $prompt .= "- 회사명: " . ($companyProfile['company_name'] ?? '타이드플로') . "\n";
        $prompt .= "- 주요 기술스택: " . implode(', ', $companyProfile['tech_stack'] ?? ['PHP', 'Laravel', 'Vue.js', 'MySQL']) . "\n";
        $prompt .= "- 전문 분야: " . implode(', ', $companyProfile['specialties'] ?? ['웹 개발', '시스템 구축', 'API 개발']) . "\n";
        $prompt .= "- 프로젝트 경험: " . ($companyProfile['project_experience'] ?? '정부기관 및 대기업 프로젝트 다수') . "\n";

        $prompt .= "\n## 분석 요청\n";
        $prompt .= "위 정보를 바탕으로 다음 JSON 형태로 분석 결과를 제공해주세요:\n\n";
        
        return $prompt;
    }

    /**
     * 첨부파일 분석 프롬프트 생성
     */
    private function buildAttachmentAnalysisPrompt(string $content, string $fileName): string
    {
        $prompt = "다음 나라장터 첨부파일을 분석하여 기술 요구사항을 추출해주세요.\n\n";
        $prompt .= "## 파일 정보\n";
        $prompt .= "- 파일명: {$fileName}\n\n";
        $prompt .= "## 파일 내용\n";
        $prompt .= substr($content, 0, 3000) . (strlen($content) > 3000 ? '...' : '') . "\n\n";
        $prompt .= "위 내용에서 기술 요구사항, 개발 범위, 필요한 기술스택 등을 JSON 형태로 추출해주세요.\n";
        
        return $prompt;
    }

    /**
     * 기술스택 분석 프롬프트 생성
     */
    private function buildTechStackAnalysisPrompt(array $techStackData): string
    {
        $prompt = "다음 기술스택 정보를 분석하여 카테고리별로 정리해주세요.\n\n";
        $prompt .= "## 기술스택 데이터\n";
        $prompt .= json_encode($techStackData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        $prompt .= "위 기술들을 프론트엔드, 백엔드, 데이터베이스, 클라우드, 도구 등으로 분류하여 JSON 형태로 정리해주세요.\n";
        
        return $prompt;
    }

    /**
     * 제안서 구조 분석 프롬프트 생성
     */
    private function buildProposalStructurePrompt(array $tenderData, array $attachmentContent): string
    {
        $prompt = "다음 나라장터 용역공고의 제안서 구조를 분석하여 최적의 제안서 목차를 제시해주세요.\n\n";
        
        $prompt .= "## 입찰공고 정보\n";
        $prompt .= "- 공고번호: " . ($tenderData['tender_no'] ?? 'N/A') . "\n";
        $prompt .= "- 공고명: " . ($tenderData['title'] ?? 'N/A') . "\n";
        $prompt .= "- 발주기관: " . ($tenderData['ntce_instt_nm'] ?? 'N/A') . "\n";
        $prompt .= "- 공고내용: " . ($tenderData['ntce_cont'] ?? 'N/A') . "\n";

        if (!empty($attachmentContent)) {
            $prompt .= "\n## 첨부파일 분석 내용\n";
            foreach ($attachmentContent as $fileName => $content) {
                $prompt .= "### {$fileName}\n";
                $prompt .= substr($content, 0, 2000) . (strlen($content) > 2000 ? '...' : '') . "\n\n";
            }
        }

        $prompt .= "\n## 분석 요청\n";
        $prompt .= "위 공고 정보를 바탕으로 제안서 구조를 다음 JSON 형태로 분석해주세요:\n\n";
        
        return $prompt;
    }

    /**
     * 제안서 생성 프롬프트 생성
     */
    private function buildProposalGenerationPrompt(array $tenderData, array $companyProfile, array $proposalStructure, array $analysisResult): string
    {
        $prompt = "다음 나라장터 용역공고에 대한 제안서를 작성해주세요.\n\n";
        
        $prompt .= "## 입찰공고 정보\n";
        $prompt .= "- 공고번호: " . ($tenderData['tender_no'] ?? 'N/A') . "\n";
        $prompt .= "- 공고명: " . ($tenderData['title'] ?? 'N/A') . "\n";
        $prompt .= "- 발주기관: " . ($tenderData['ntce_instt_nm'] ?? 'N/A') . "\n";
        $prompt .= "- 예정가격: " . ($tenderData['budget'] ?? 'N/A') . "\n";
        $prompt .= "- 공고내용: " . ($tenderData['ntce_cont'] ?? 'N/A') . "\n";

        $prompt .= "\n## 회사 정보 (타이드플로)\n";
        $prompt .= "- 회사명: " . ($companyProfile['company_name'] ?? '타이드플로') . "\n";
        $prompt .= "- 주요 기술: " . implode(', ', $companyProfile['tech_stack'] ?? ['Java', 'Spring', 'MySQL', 'Oracle']) . "\n";
        $prompt .= "- 전문 분야: " . implode(', ', $companyProfile['specialties'] ?? ['Java 시스템 개발', 'SI 프로젝트', '유지보수']) . "\n";
        $prompt .= "- 프로젝트 경험: " . ($companyProfile['project_experience'] ?? 'Java 전문 개발회사 15년, 정부기관 SI 프로젝트 전문') . "\n";

        $prompt .= "\n## 제안서 구조\n";
        if (isset($proposalStructure['sections'])) {
            foreach ($proposalStructure['sections'] as $section) {
                $prompt .= "- " . $section['title'] . " (가중치: " . ($section['weight'] ?? 0) . ")\n";
            }
        }

        if (!empty($analysisResult)) {
            $prompt .= "\n## 사전 분석 결과\n";
            $prompt .= "- 적합성 점수: " . ($analysisResult['compatibility_score'] ?? 'N/A') . "점\n";
            $prompt .= "- 추천 기술: " . implode(', ', $analysisResult['matching_technologies'] ?? []) . "\n";
        }

        $prompt .= "\n## 작성 요청\n";
        $prompt .= "위 정보를 바탕으로 전문적이고 설득력 있는 제안서를 작성해주세요. 타이드플로의 Java 전문성과 정부기관 경험을 강조하여 작성해주세요.\n";
        
        return $prompt;
    }

    /**
     * 폴백 제안서 구조 반환
     */
    private function getFallbackProposalStructure(array $tenderData): array
    {
        return [
            'sections' => [
                ['order' => 1, 'title' => '사업 개요', 'required' => true, 'weight' => 0.15],
                ['order' => 2, 'title' => '사업 이해도', 'required' => true, 'weight' => 0.20],
                ['order' => 3, 'title' => '사업 수행 방안', 'required' => true, 'weight' => 0.25],
                ['order' => 4, 'title' => '기술 제안', 'required' => true, 'weight' => 0.20],
                ['order' => 5, 'title' => '프로젝트 관리', 'required' => true, 'weight' => 0.10],
                ['order' => 6, 'title' => '투입 인력', 'required' => true, 'weight' => 0.10]
            ],
            'total_sections' => 6,
            'estimated_pages' => 15,
            'structure_complexity' => '낮음',
            'special_requirements' => ['AI 분석 실패로 기본 구조 사용'],
            'is_fallback' => true
        ];
    }

    /**
     * 폴백 제안서 반환
     */
    private function getFallbackProposal(array $tenderData, array $companyProfile): array
    {
        return [
            'title' => '시스템 개발 제안서 - 타이드플로',
            'content' => '# 시스템 개발 제안서\n\nAI 분석이 실패하여 기본 제안서 템플릿을 사용합니다.\n수동으로 내용을 보완해주세요.',
            'sections_generated' => 0,
            'estimated_pages' => 10,
            'content_length' => 200,
            'confidence_score' => 0,
            'generation_quality' => '낮음',
            'ai_improvements' => [],
            'processing_notes' => ['AI 제안서 생성 실패', '수동 보완 필요'],
            'is_fallback' => true
        ];
    }

    /**
     * 분석 타입별 시스템 프롬프트 반환
     */
    private function getSystemPrompt(string $analysisType): string
    {
        return match($analysisType) {
            'tender_analysis' => "당신은 나라장터 입찰공고 전문 분석가입니다. 입찰공고와 회사 역량을 정밀 비교하여 다음 JSON 형태로 분석 결과를 제공해주세요:

{
    \"compatibility_score\": 85,
    \"technical_match_score\": 90,
    \"business_match_score\": 80,
    \"risk_score\": 30,
    \"opportunity_score\": 85,
    \"detailed_analysis\": {
        \"strengths\": [\"강점 1\", \"강점 2\"],
        \"weaknesses\": [\"약점 1\", \"약점 2\"],
        \"opportunities\": [\"기회 요소 1\", \"기회 요소 2\"],
        \"risks\": [\"위험 요소 1\", \"위험 요소 2\"]
    },
    \"required_technologies\": [\"PHP\", \"Laravel\", \"MySQL\"],
    \"matching_technologies\": [\"PHP\", \"Laravel\"],
    \"missing_technologies\": [\"MySQL\"],
    \"recommendation\": \"종합적인 추천 의견\",
    \"success_probability\": 75,
    \"estimated_effort\": \"높음\",
    \"key_considerations\": [\"주요 고려사항 1\", \"주요 고려사항 2\"]
}",

            'attachment_analysis' => "당신은 입찰공고 첨부파일 분석 전문가입니다. 다음 JSON 형태로 기술 요구사항을 추출해주세요:

{
    \"required_technologies\": [\"기술1\", \"기술2\"],
    \"development_scope\": \"개발 범위 설명\",
    \"project_duration\": \"예상 기간\",
    \"complexity_level\": \"높음/중간/낮음\",
    \"key_requirements\": [\"주요 요구사항 1\", \"주요 요구사항 2\"],
    \"special_notes\": [\"특이사항 1\", \"특이사항 2\"]
}",

            'tech_stack_analysis' => "당신은 기술스택 분류 전문가입니다. 다음 JSON 형태로 기술들을 카테고리별로 정리해주세요:

{
    \"frontend\": [\"React\", \"Vue.js\"],
    \"backend\": [\"PHP\", \"Laravel\", \"Node.js\"],
    \"database\": [\"MySQL\", \"MongoDB\"],
    \"cloud\": [\"AWS\", \"Azure\"],
    \"tools\": [\"Git\", \"Docker\"],
    \"mobile\": [\"React Native\"],
    \"other\": [\"기타 기술들\"]
}",

            'proposal_structure_analysis' => "당신은 나라장터 제안서 구조 전문가입니다. 다음 JSON 형태로 제안서 구조를 분석해주세요:

{
    \"sections\": [
        {\"order\": 1, \"title\": \"사업 개요\", \"required\": true, \"weight\": 0.15},
        {\"order\": 2, \"title\": \"사업 이해도\", \"required\": true, \"weight\": 0.20}
    ],
    \"total_sections\": 10,
    \"estimated_pages\": 20,
    \"structure_complexity\": \"중간\",
    \"special_requirements\": [\"보안 요구사항\", \"접근성 준수\"]
}",

            'proposal_generation' => "당신은 전문 제안서 작성 전문가입니다. 주어진 공고와 회사 정보를 바탕으로 설득력 있는 제안서를 작성하고 다음 JSON 형태로 결과를 제공해주세요:

{
    \"title\": \"제안서 제목\",
    \"content\": \"마크다운 형식의 제안서 전체 내용\",
    \"sections_generated\": 10,
    \"estimated_pages\": 25,
    \"content_length\": 15000,
    \"confidence_score\": 90,
    \"generation_quality\": \"높음\",
    \"ai_improvements\": [\"개선사항1\", \"개선사항2\"],
    \"processing_notes\": [\"참고사항1\", \"참고사항2\"]
}",

            default => "당신은 IT 기술 전문 분석가입니다. 주어진 정보를 정확하고 구조화된 JSON 형태로 분석해주세요."
        };
    }

    /**
     * 캐시 키 생성
     */
    private function generateCacheKey(string $type, array $params): string
    {
        $keyData = array_merge(['type' => $type, 'provider' => $this->provider], $params);
        return 'ai_analysis_' . md5(json_encode($keyData));
    }

    /**
     * 폴백 분석 결과 반환 (AI API 실패 시)
     */
    private function getFallbackAnalysis(array $tenderData, array $companyProfile): array
    {
        return [
            'compatibility_score' => 50,
            'technical_match_score' => 50,
            'business_match_score' => 50,
            'risk_score' => 50,
            'opportunity_score' => 50,
            'detailed_analysis' => [
                'strengths' => ['기본 웹 개발 역량'],
                'weaknesses' => ['상세 분석 불가'],
                'opportunities' => ['프로젝트 참여 기회'],
                'risks' => ['AI 분석 실패로 정확도 제한']
            ],
            'required_technologies' => [],
            'matching_technologies' => [],
            'missing_technologies' => [],
            'recommendation' => 'AI 분석이 실패하여 기본 분석 결과입니다. 수동으로 검토해주세요.',
            'success_probability' => 50,
            'estimated_effort' => '알 수 없음',
            'key_considerations' => ['수동 검토 필요'],
            'is_fallback' => true
        ];
    }

    /**
     * 폴백 첨부파일 분석 결과 반환
     */
    private function getFallbackAttachmentAnalysis(string $fileName): array
    {
        return [
            'required_technologies' => [],
            'development_scope' => '분석 불가',
            'project_duration' => '알 수 없음',
            'complexity_level' => '알 수 없음',
            'key_requirements' => [],
            'special_notes' => ['AI 분석 실패로 수동 검토 필요'],
            'is_fallback' => true
        ];
    }

    /**
     * AI API 사용 통계 조회
     */
    public function getUsageStats(): array
    {
        // 실제 구현 시에는 데이터베이스에서 사용량 통계를 조회
        return [
            'daily_requests' => 0,
            'monthly_requests' => 0,
            'cache_hit_rate' => 0,
            'average_response_time' => 0,
            'error_rate' => 0
        ];
    }

    /**
     * 공고 제안서 구조 분석
     * 
     * @param array $tenderData 공고 데이터
     * @param array $attachmentContent 첨부파일 내용
     * @return array 제안서 구조 분석 결과
     */
    public function analyzeProposalStructure(array $tenderData, array $attachmentContent = []): array
    {
        try {
            $cacheKey = $this->generateCacheKey('proposal_structure', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'attachments_hash' => md5(json_encode($attachmentContent))
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('제안서 구조 분석 캐시 사용', ['tender_no' => $tenderData['tender_no'] ?? '']);
                return $cached;
            }

            $prompt = $this->buildProposalStructurePrompt($tenderData, $attachmentContent);
            $result = $this->callAiApi($prompt, 'proposal_structure_analysis');
            
            Cache::put($cacheKey, $result, $this->cacheTime);
            
            Log::info('제안서 구조 분석 완료', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'sections' => count($result['sections'] ?? [])
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('제안서 구조 분석 실패', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackProposalStructure($tenderData);
        }
    }

    /**
     * AI 기반 제안서 생성
     * 
     * @param array $tenderData 공고 데이터
     * @param array $companyProfile 회사 프로필
     * @param array $proposalStructure 제안서 구조
     * @param array $analysisResult 사전 분석 결과
     * @return array 생성된 제안서
     */
    public function generateProposal(array $tenderData, array $companyProfile, array $proposalStructure, array $analysisResult = []): array
    {
        try {
            $cacheKey = $this->generateCacheKey('proposal_generation', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'company_id' => $companyProfile['id'] ?? 'default',
                'structure_hash' => md5(json_encode($proposalStructure))
            ]);

            if ($cached = Cache::get($cacheKey)) {
                Log::info('제안서 생성 캐시 사용', ['tender_no' => $tenderData['tender_no'] ?? '']);
                return $cached;
            }

            $prompt = $this->buildProposalGenerationPrompt($tenderData, $companyProfile, $proposalStructure, $analysisResult);
            $result = $this->callAiApi($prompt, 'proposal_generation');
            
            Cache::put($cacheKey, $result, $this->cacheTime);
            
            Log::info('제안서 생성 완료', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'content_length' => strlen($result['content'] ?? '')
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('제안서 생성 실패', [
                'tender_no' => $tenderData['tender_no'] ?? '',
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackProposal($tenderData, $companyProfile);
        }
    }

    /**
     * API 연결 상태 확인
     */
    public function checkConnection(): array
    {
        try {
            $testPrompt = "Hello, this is a connection test. Please respond with JSON: {\"status\": \"connected\", \"provider\": \"" . $this->provider . "\"}";
            $result = $this->callAiApi($testPrompt, 'connection_test');
            
            return [
                'status' => 'connected',
                'provider' => $this->provider,
                'response_time' => 0, // 실제 구현 시 응답 시간 측정
                'test_result' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'provider' => $this->provider,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 과업지시서 분석 프롬프트 생성
     */
    private function buildTaskInstructionAnalysisPrompt(string $content, array $tenderData): string
    {
        $prompt = "다음은 나라장터 과업지시서 내용입니다. 이를 분석하여 실제 프로젝트 요구사항을 추출해주세요.\n\n";
        
        $prompt .= "## 공고 기본 정보\n";
        $prompt .= "- 공고번호: " . ($tenderData['tender_no'] ?? 'N/A') . "\n";
        $prompt .= "- 공고명: " . ($tenderData['title'] ?? 'N/A') . "\n";
        $prompt .= "- 발주기관: " . ($tenderData['ntce_instt_nm'] ?? 'N/A') . "\n\n";
        
        $prompt .= "## 과업지시서 내용\n";
        $prompt .= substr($content, 0, 4000) . (strlen($content) > 4000 ? '...' : '') . "\n\n";
        
        $prompt .= "## 분석 요청\n";
        $prompt .= "위 과업지시서를 분석하여 다음 JSON 형태로 결과를 제공해주세요:\n\n";
        
        $prompt .= "{\n";
        $prompt .= '  "project_overview": {' . "\n";
        $prompt .= '    "project_name": "사업명",' . "\n";
        $prompt .= '    "project_purpose": "사업 목적",' . "\n";
        $prompt .= '    "project_duration": "사업 기간",' . "\n";
        $prompt .= '    "budget": "예산 정보"' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "technical_requirements": {' . "\n";
        $prompt .= '    "programming_languages": ["Java", "Python", "JavaScript"],' . "\n";
        $prompt .= '    "frameworks": ["Spring", "Django", "React"],' . "\n";
        $prompt .= '    "databases": ["MySQL", "PostgreSQL", "Oracle"],' . "\n";
        $prompt .= '    "servers": ["Linux", "Apache", "Nginx"],' . "\n";
        $prompt .= '    "special_technologies": ["GIS", "PostGIS", "OpenLayers"]' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "functional_requirements": [' . "\n";
        $prompt .= '    "주요 기능 1",' . "\n";
        $prompt .= '    "주요 기능 2"' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "deliverables": [' . "\n";
        $prompt .= '    "납품물 1",' . "\n";
        $prompt .= '    "납품물 2"' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "evaluation_criteria": {' . "\n";
        $prompt .= '    "technical_score": 40,' . "\n";
        $prompt .= '    "business_score": 30,' . "\n";
        $prompt .= '    "price_score": 30' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "qualification_requirements": [' . "\n";
        $prompt .= '    "업체 자격요건 1",' . "\n";
        $prompt .= '    "업체 자격요건 2"' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "proposal_structure": [' . "\n";
        $prompt .= '    "제안서 구성 1",' . "\n";
        $prompt .= '    "제안서 구성 2"' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "key_considerations": [' . "\n";
        $prompt .= '    "중요 고려사항 1",' . "\n";
        $prompt .= '    "중요 고려사항 2"' . "\n";
        $prompt .= '  ]' . "\n";
        $prompt .= "}\n\n";
        
        $prompt .= "위 형식으로 과업지시서의 핵심 내용을 추출하여 JSON 형태로 응답해주세요.";
        
        return $prompt;
    }

    /**
     * 과업지시서 분석 결과 파싱
     */
    private function parseTaskInstructionAnalysis(string $aiResponse): array
    {
        try {
            // JSON 형태의 응답을 파싱
            if (preg_match('/\{[\s\S]*\}/', $aiResponse, $matches)) {
                $jsonString = $matches[0];
                $parsedData = json_decode($jsonString, true);
                
                if ($parsedData && json_last_error() === JSON_ERROR_NONE) {
                    Log::info('과업지시서 AI 분석 결과 파싱 성공');
                    return $this->normalizeTaskInstructionAnalysis($parsedData);
                }
            }
            
            // JSON 파싱 실패 시 텍스트에서 정보 추출
            return $this->extractRequirementsFromText($aiResponse);
            
        } catch (Exception $e) {
            Log::warning('과업지시서 분석 결과 파싱 실패', ['error' => $e->getMessage()]);
            return $this->generateBasicTaskInstructionAnalysis($aiResponse);
        }
    }

    /**
     * 과업지시서 분석 결과 정규화
     */
    private function normalizeTaskInstructionAnalysis(array $parsedData): array
    {
        return [
            'project_overview' => $parsedData['project_overview'] ?? [
                'project_name' => '프로젝트명 추출 실패',
                'project_purpose' => '목적 분석 필요',
                'project_duration' => '기간 확인 필요',
                'budget' => '예산 정보 없음'
            ],
            'technical_requirements' => $parsedData['technical_requirements'] ?? [
                'programming_languages' => [],
                'frameworks' => [],
                'databases' => [],
                'servers' => [],
                'special_technologies' => []
            ],
            'functional_requirements' => $parsedData['functional_requirements'] ?? [],
            'deliverables' => $parsedData['deliverables'] ?? [],
            'evaluation_criteria' => $parsedData['evaluation_criteria'] ?? [
                'technical_score' => 40,
                'business_score' => 30,
                'price_score' => 30
            ],
            'qualification_requirements' => $parsedData['qualification_requirements'] ?? [],
            'proposal_structure' => $parsedData['proposal_structure'] ?? [],
            'key_considerations' => $parsedData['key_considerations'] ?? [],
            'analysis_quality' => 'ai_parsed',
            'confidence_score' => 85
        ];
    }

    /**
     * 텍스트에서 요구사항 추출 (JSON 파싱 실패 시)
     */
    private function extractRequirementsFromText(string $text): array
    {
        $requirements = [
            'technical_requirements' => [
                'programming_languages' => [],
                'frameworks' => [],
                'databases' => [],
                'servers' => [],
                'special_technologies' => []
            ],
            'functional_requirements' => [],
            'analysis_quality' => 'text_extracted',
            'confidence_score' => 60
        ];

        // 기술스택 키워드 추출
        $techKeywords = [
            'programming_languages' => ['Java', 'Python', 'JavaScript', 'PHP', 'C#'],
            'frameworks' => ['Spring', 'Django', 'Laravel', 'React', 'Vue', 'Angular'],
            'databases' => ['MySQL', 'PostgreSQL', 'Oracle', 'MongoDB', 'Redis'],
            'servers' => ['Linux', 'Windows', 'Apache', 'Nginx', 'Tomcat'],
            'special_technologies' => ['GIS', 'PostGIS', 'OpenLayers', 'Leaflet', 'ArcGIS']
        ];

        foreach ($techKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $requirements['technical_requirements'][$category][] = $keyword;
                }
            }
        }

        return $requirements;
    }

    /**
     * 기본 과업지시서 분석 결과 생성 (AI 실패 시)
     */
    private function generateBasicTaskInstructionAnalysis(string $content): array
    {
        return [
            'project_overview' => [
                'project_name' => '시스템 구축 프로젝트',
                'project_purpose' => 'AI 분석 실패로 수동 확인 필요',
                'project_duration' => '일정 확인 필요',
                'budget' => '예산 정보 확인 필요'
            ],
            'technical_requirements' => [
                'programming_languages' => ['Java'], // 기본 추정
                'frameworks' => ['Spring'],
                'databases' => ['MySQL', 'Oracle'],
                'servers' => ['Linux'],
                'special_technologies' => []
            ],
            'functional_requirements' => ['기능 요구사항 수동 분석 필요'],
            'deliverables' => ['납품물 정보 확인 필요'],
            'evaluation_criteria' => [
                'technical_score' => 40,
                'business_score' => 30,
                'price_score' => 30
            ],
            'qualification_requirements' => ['업체 자격 수동 확인 필요'],
            'proposal_structure' => ['제안서 구조 수동 분석 필요'],
            'key_considerations' => ['AI 분석 실패로 수동 검토 필요'],
            'analysis_quality' => 'fallback',
            'confidence_score' => 30,
            'is_fallback' => true,
            'content_preview' => substr($content, 0, 200) . '...'
        ];
    }

    /**
     * Fallback 과업지시서 분석
     */
    private function generateFallbackTaskInstructionAnalysis(string $content, array $tenderData): array
    {
        // 제목에서 키워드 추출
        $title = $tenderData['title'] ?? '';
        $technicalRequirements = [
            'programming_languages' => [],
            'frameworks' => [],
            'databases' => [],
            'servers' => [],
            'special_technologies' => []
        ];

        // 제목 기반 기술 추정
        if (stripos($title, 'GIS') !== false || stripos($title, '공간') !== false) {
            $technicalRequirements['special_technologies'] = ['GIS', 'PostGIS', 'OpenLayers'];
            $technicalRequirements['databases'] = ['PostgreSQL'];
        }
        if (stripos($title, '웹') !== false || stripos($title, 'web') !== false) {
            $technicalRequirements['programming_languages'] = ['Java', 'JavaScript'];
            $technicalRequirements['frameworks'] = ['Spring', 'React'];
        }
        if (stripos($title, '데이터베이스') !== false || stripos($title, 'DB') !== false) {
            $technicalRequirements['databases'] = ['MySQL', 'Oracle', 'PostgreSQL'];
        }

        return [
            'project_overview' => [
                'project_name' => $tenderData['title'] ?? '프로젝트명 확인 필요',
                'project_purpose' => '업무 효율성 향상 및 시스템 현대화',
                'project_duration' => '120일 (추정)',
                'budget' => $tenderData['budget'] ?? '예산 정보 없음'
            ],
            'technical_requirements' => $technicalRequirements,
            'functional_requirements' => [
                '시스템 구축',
                '데이터 관리',
                '사용자 인터페이스 제공',
                '운영 및 유지보수'
            ],
            'deliverables' => [
                '시스템 소스코드',
                '시스템 설계서',
                '사용자 매뉴얼',
                '운영 매뉴얼'
            ],
            'evaluation_criteria' => [
                'technical_score' => 40,
                'business_score' => 30,
                'price_score' => 30
            ],
            'qualification_requirements' => [
                '소프트웨어사업자 신고',
                '관련 분야 경험',
                '전문 인력 보유'
            ],
            'proposal_structure' => [
                '사업 개요 및 이해도',
                '사업 수행 방안',
                '기술 제안서',
                '프로젝트 관리 계획',
                '투입 인력 및 조직'
            ],
            'key_considerations' => [
                '정부 시스템 보안 요구사항',
                '웹 접근성 준수',
                '표준 프레임워크 사용'
            ],
            'analysis_quality' => 'title_based_fallback',
            'confidence_score' => 50,
            'is_fallback' => true
        ];
    }
}

// [END nara:ai_api_service]
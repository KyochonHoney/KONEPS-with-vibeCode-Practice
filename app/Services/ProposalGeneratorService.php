<?php

// [BEGIN nara:proposal_generator_service]
namespace App\Services;

use App\Models\Proposal;
use App\Models\Tender;
use App\Models\User;
use App\Models\CompanyProfile;
use App\Services\AiApiService;
use App\Services\DynamicProposalGenerator;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AI 기반 제안서 자동생성 서비스
 * 
 * @package App\Services
 */
class ProposalGeneratorService
{
    private AiApiService $aiApiService;
    private DynamicProposalGenerator $dynamicGenerator;
    private AttachmentService $attachmentService;

    public function __construct(
        AiApiService $aiApiService,
        DynamicProposalGenerator $dynamicGenerator,
        AttachmentService $attachmentService
    ) {
        $this->aiApiService = $aiApiService;
        $this->dynamicGenerator = $dynamicGenerator;
        $this->attachmentService = $attachmentService;
    }

    /**
     * 제안서 생성 실행
     * 
     * @param Tender $tender 대상 공고
     * @param User $user 요청 사용자
     * @param array $options 생성 옵션
     * @return Proposal 생성된 제안서
     */
    public function generateProposal(Tender $tender, User $user, array $options = []): Proposal
    {
        $companyProfile = CompanyProfile::getTideFloProfile();
        
        // 제안서 생성 시작 기록
        $proposal = Proposal::create([
            'tender_id' => $tender->id,
            'user_id' => $user->id,
            'title' => $this->generateInitialTitle($tender),
            'content' => '',
            'template_version' => 'v1.0',
            'ai_analysis_data' => [],
            'status' => 'processing',
            'processing_time' => 0
        ]);

        try {
            $startTime = microtime(true);
            
            Log::info('제안서 생성 시작 (동적 생성)', [
                'proposal_id' => $proposal->id,
                'tender_id' => $tender->id,
                'tender_no' => $tender->tender_no,
                'user_id' => $user->id
            ]);

            // 1단계: 첨부파일 내용 수집
            $attachmentContents = $this->getAttachmentContents($tender);
            
            // 2단계: 완전 동적 제안서 생성 (하드코딩 없음)
            $proposalResult = $this->dynamicGenerator->generateDynamicProposal(
                $tender, 
                $companyProfile, 
                $attachmentContents
            );
            
            $endTime = microtime(true);
            $processingTime = (int) (($endTime - $startTime) * 1000); // ms 단위

            // 제안서 완료 업데이트
            $proposal->update([
                'title' => $proposalResult['title'],
                'content' => $proposalResult['content'],
                'ai_analysis_data' => [
                    'structure_source' => $proposalResult['structure_source'] ?? 'dynamic',
                    'proposal_generation' => $proposalResult,
                    'generation_quality' => $proposalResult['generation_quality'] ?? '보통',
                    'confidence_score' => $proposalResult['confidence_score'] ?? 70,
                    'sections_count' => $proposalResult['sections_generated'] ?? 0,
                    'estimated_pages' => $proposalResult['estimated_pages'] ?? 15,
                    'matching_technologies' => $proposalResult['matching_technologies'] ?? [],
                    'missing_technologies' => $proposalResult['missing_technologies'] ?? [],
                    'is_dynamic_generated' => $proposalResult['is_dynamic_generated'] ?? true
                ],
                'status' => 'completed',
                'processing_time' => $processingTime,
                'generated_at' => now()
            ]);

            Log::info('제안서 생성 완료', [
                'proposal_id' => $proposal->id,
                'tender_no' => $tender->tender_no,
                'processing_time_ms' => $processingTime,
                'content_length' => strlen($proposalResult['content'] ?? ''),
                'quality' => $proposalResult['generation_quality'] ?? 'N/A'
            ]);

            return $proposal->fresh();

        } catch (Exception $e) {
            // 제안서 생성 실패 처리
            $proposal->update([
                'status' => 'failed',
                'ai_analysis_data' => ['error' => $e->getMessage()],
                'generated_at' => now()
            ]);

            Log::error('제안서 생성 실패', [
                'proposal_id' => $proposal->id,
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 제안서 구조 분석
     * 
     * @param Tender $tender 공고
     * @param array $options 분석 옵션
     * @return array 구조 분석 결과
     */
    private function analyzeProposalStructure(Tender $tender, array $options = []): array
    {
        try {
            // 공고 데이터 준비
            $tenderData = [
                'tender_no' => $tender->tender_no,
                'title' => $tender->title,
                'ntce_instt_nm' => $tender->ntce_instt_nm,
                'ntce_cont' => $tender->content ?? $tender->summary,
                'industry_code' => $tender->pub_prcrmnt_clsfc_no,
                'budget' => $tender->budget_formatted
            ];

            // 첨부파일 내용 수집 (추후 구현)
            $attachmentContent = $options['attachment_content'] ?? [];

            // AI 기반 제안서 구조 분석
            return $this->aiApiService->analyzeProposalStructure($tenderData, $attachmentContent);

        } catch (Exception $e) {
            Log::warning('제안서 구조 분석 실패, 기본 구조 사용', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            // 기본 구조 반환
            return $this->getDefaultProposalStructure($tender);
        }
    }

    /**
     * 제안서 생성 수행
     * 
     * @param Tender $tender 공고
     * @param CompanyProfile $companyProfile 회사 프로필
     * @param array $structureAnalysis 구조 분석 결과
     * @param array $tenderAnalysis 공고 분석 결과
     * @return array 제안서 생성 결과
     */
    private function performProposalGeneration(Tender $tender, CompanyProfile $companyProfile, array $structureAnalysis, array $tenderAnalysis): array
    {
        try {
            // 공고 데이터 준비
            $tenderData = [
                'tender_no' => $tender->tender_no,
                'title' => $tender->title,
                'ntce_instt_nm' => $tender->ntce_instt_nm,
                'budget' => $tender->budget_formatted,
                'ntce_cont' => $tender->content ?? $tender->summary,
                'deadline' => $tender->deadline
            ];

            // 회사 프로필 데이터 준비
            $companyProfileData = [
                'id' => $companyProfile->id,
                'company_name' => $companyProfile->name,
                'tech_stack' => array_keys($companyProfile->technical_keywords),
                'specialties' => $companyProfile->business_areas,
                'project_experience' => implode(', ', $companyProfile->experiences)
            ];

            // AI 기반 제안서 생성
            return $this->aiApiService->generateProposal(
                $tenderData, 
                $companyProfileData, 
                $structureAnalysis, 
                $tenderAnalysis
            );

        } catch (Exception $e) {
            Log::warning('AI 제안서 생성 실패, 템플릿 기반 생성 실행', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            // 템플릿 기반 제안서 생성
            return $this->generateTemplateBasedProposal($tender, $companyProfile, $structureAnalysis);
        }
    }

    /**
     * 기존 공고 분석 결과 조회
     * 
     * @param Tender $tender 공고
     * @return array 분석 결과
     */
    private function getTenderAnalysis(Tender $tender): array
    {
        $analysis = $tender->analyses()->completed()->latest()->first();
        
        if (!$analysis) {
            return [];
        }

        $analysisData = is_string($analysis->analysis_data) ? 
            json_decode($analysis->analysis_data, true) : 
            $analysis->analysis_data;

        return [
            'compatibility_score' => $analysis->total_score,
            'technical_score' => $analysis->technical_score,
            'business_score' => $analysis->experience_score,
            'matching_technologies' => $analysisData['ai_analysis']['matching_technologies'] ?? [],
            'required_technologies' => $analysisData['ai_analysis']['required_technologies'] ?? [],
            'recommendation' => $analysisData['recommendation'] ?? ''
        ];
    }

    /**
     * 초기 제목 생성
     * 
     * @param Tender $tender 공고
     * @return string 제목
     */
    private function generateInitialTitle(Tender $tender): string
    {
        $tenderTitle = $tender->title;
        
        // 공고 제목에서 핵심 키워드 추출
        if (str_contains(strtolower($tenderTitle), '웹') || str_contains(strtolower($tenderTitle), 'web')) {
            return '웹 시스템 구축 제안서 - 타이드플로';
        } elseif (str_contains(strtolower($tenderTitle), '데이터') || str_contains(strtolower($tenderTitle), 'xml')) {
            return '데이터 관리 시스템 구축 제안서 - 타이드플로';
        } elseif (str_contains(strtolower($tenderTitle), '시스템')) {
            return '시스템 통합 구축 제안서 - 타이드플로';
        }
        
        return '시스템 개발 제안서 - 타이드플로';
    }

    /**
     * 기본 제안서 구조 반환
     * 
     * @param Tender $tender 공고
     * @return array 기본 구조
     */
    private function getDefaultProposalStructure(Tender $tender): array
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
            'special_requirements' => ['기본 제안서 구조'],
            'is_fallback' => true
        ];
    }

    /**
     * 템플릿 기반 제안서 생성
     * 
     * @param Tender $tender 공고
     * @param CompanyProfile $companyProfile 회사 프로필
     * @param array $structureAnalysis 구조 분석
     * @return array 생성 결과
     */
    private function generateTemplateBasedProposal(Tender $tender, CompanyProfile $companyProfile, array $structureAnalysis): array
    {
        try {
            $templatePath = base_path('../docs/templates/proposal-template.md');
            $templateContent = file_get_contents($templatePath);

            // 기본 치환값 준비
            $replacements = [
                '{PROJECT_NAME}' => $tender->title,
                '{PROJECT_PURPOSE}' => '효율적이고 안정적인 시스템 구축을 통한 업무 효율성 향상',
                '{PROJECT_SCOPE}' => 'Java 기반 시스템 개발, 데이터베이스 구축, 시스템 통합',
                '{TECHNICAL_APPROACH}' => 'Java/Spring Framework 기반 개발, 객체지향 설계 원칙 적용',
                '{TECHNOLOGY_STACK}' => 'Java, Spring Framework, MySQL/Oracle, Apache Tomcat',
                '{COMPANY_ACHIEVEMENTS}' => 'Java 전문 개발회사 15년 경력, 정부기관 SI 프로젝트 다수 수행'
            ];

            $content = str_replace(array_keys($replacements), array_values($replacements), $templateContent);

            return [
                'title' => $this->generateInitialTitle($tender),
                'content' => $content,
                'sections_generated' => count($structureAnalysis['sections'] ?? 6),
                'estimated_pages' => $structureAnalysis['estimated_pages'] ?? 15,
                'content_length' => strlen($content),
                'confidence_score' => 60,
                'generation_quality' => '보통',
                'ai_improvements' => ['템플릿 기반 생성'],
                'processing_notes' => ['AI 생성 실패로 템플릿 기반으로 생성됨'],
                'is_template_based' => true
            ];

        } catch (Exception $e) {
            Log::error('템플릿 기반 제안서 생성 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);

            // 최소한의 제안서 반환
            return [
                'title' => $this->generateInitialTitle($tender),
                'content' => "# {$tender->title}\n\n제안서 생성 중 오류가 발생했습니다.\n수동으로 내용을 보완해주세요.",
                'sections_generated' => 1,
                'estimated_pages' => 5,
                'content_length' => 100,
                'confidence_score' => 0,
                'generation_quality' => '낮음',
                'ai_improvements' => [],
                'processing_notes' => ['제안서 생성 실패'],
                'is_fallback' => true
            ];
        }
    }

    /**
     * 일괄 제안서 생성
     * 
     * @param array $tenderIds 공고 ID 배열
     * @param User $user 요청 사용자
     * @param array $options 생성 옵션
     * @return array 생성 결과
     */
    public function bulkGenerateProposals(array $tenderIds, User $user, array $options = []): array
    {
        $results = [];
        
        foreach ($tenderIds as $tenderId) {
            try {
                $tender = Tender::find($tenderId);
                if ($tender) {
                    $proposal = $this->generateProposal($tender, $user, $options);
                    $results[] = [
                        'tender_id' => $tenderId,
                        'success' => true,
                        'proposal_id' => $proposal->id,
                        'title' => $proposal->title,
                        'quality' => $proposal->ai_analysis_data['generation_quality'] ?? 'N/A'
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

    /**
     * 제안서 재생성
     * 
     * @param Proposal $proposal 기존 제안서
     * @param array $options 재생성 옵션
     * @return Proposal 재생성된 제안서
     */
    public function regenerateProposal(Proposal $proposal, array $options = []): Proposal
    {
        Log::info('제안서 재생성 시작', [
            'original_proposal_id' => $proposal->id,
            'tender_id' => $proposal->tender_id
        ]);

        // 기존 제안서 비활성화 (삭제하지 않고 보관)
        $proposal->update(['status' => 'replaced']);

        // 새 제안서 생성
        return $this->generateProposal($proposal->tender, $proposal->user, $options);
    }

    /**
     * 첨부파일 내용 수집
     * 
     * @param Tender $tender 공고
     * @return array 첨부파일 내용 배열
     */
    private function getAttachmentContents(Tender $tender): array
    {
        $attachmentContents = [];

        try {
            // 첨부파일이 있는지 확인
            $attachments = $tender->attachments;
            
            if ($attachments->isEmpty()) {
                Log::info('첨부파일 없음', ['tender_id' => $tender->id]);
                return [];
            }

            foreach ($attachments as $attachment) {
                try {
                    // 첨부파일 내용 분석
                    $analysisResult = $this->attachmentService->analyzeAttachment($attachment);
                    
                    if (!empty($analysisResult['extracted_content'])) {
                        $attachmentContents[$attachment->filename] = $analysisResult['extracted_content'];
                    }
                    
                } catch (Exception $e) {
                    Log::warning('첨부파일 내용 추출 실패', [
                        'attachment_id' => $attachment->id,
                        'filename' => $attachment->filename,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('첨부파일 내용 수집 완료', [
                'tender_id' => $tender->id,
                'total_attachments' => count($attachments),
                'extracted_contents' => count($attachmentContents)
            ]);

        } catch (Exception $e) {
            Log::error('첨부파일 내용 수집 실패', [
                'tender_id' => $tender->id,
                'error' => $e->getMessage()
            ]);
        }

        return $attachmentContents;
    }

    /**
     * 제안서 생성 통계
     * 
     * @return array 통계 정보
     */
    public function getGenerationStats(): array
    {
        return Proposal::getGenerationStats();
    }
}
// [END nara:proposal_generator_service]
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tender;
use App\Models\CompanyProfile;
use App\Services\DynamicProposalGenerator;
use App\Services\AiApiService;
use App\Services\ProposalStructureAnalyzer;
use Exception;

class TestDynamicProposal extends Command
{
    protected $signature = 'test:dynamic-proposal {tender_no?}';
    protected $description = '동적 제안서 생성 테스트';

    public function handle()
    {
        $this->info('=== 동적 제안서 생성 테스트 ===');
        $this->info('');

        // 테스트할 공고 선택
        $tenderNo = $this->argument('tender_no') ?? 'R25BK01034597';
        $tender = Tender::where('tender_no', $tenderNo)->first();
        
        if (!$tender) {
            $this->error('공고를 찾을 수 없습니다: ' . $tenderNo);
            return;
        }

        $this->info('공고: ' . $tender->title);
        $this->info('');

        // 회사 프로필
        $companyProfile = CompanyProfile::first();
        if (!$companyProfile) {
            $this->error('회사 프로필을 찾을 수 없습니다.');
            return;
        }

        $this->info('회사: ' . $companyProfile->company_name);
        $this->info('');

        // AI 프로바이더 확인
        $aiService = app(AiApiService::class);
        $this->info('AI 프로바이더: ' . config('ai.analysis.provider', 'unknown'));
        $this->info('');

        // 동적 생성기 생성
        $structureAnalyzer = app(ProposalStructureAnalyzer::class);
        $generator = new DynamicProposalGenerator($aiService, $structureAnalyzer);

        // 제안서 생성 시작
        $this->info('동적 제안서 생성 중...');
        $this->info('');

        try {
            $startTime = microtime(true);
            $result = $generator->generateDynamicProposal($tender, $companyProfile, []);
            $endTime = microtime(true);
            
            $this->info('=== 생성 성공! ===');
            $this->info('소요 시간: ' . round($endTime - $startTime, 2) . '초');
            $this->info('제목: ' . $result['title']);
            $this->info('섹션 수: ' . $result['sections_generated']);
            $this->info('내용 길이: ' . $result['content_length'] . ' 문자');
            $this->info('신뢰도: ' . $result['confidence_score'] . '%');
            $this->info('품질: ' . $result['generation_quality']);
            $this->info('동적 생성: ' . ($result['is_dynamic_generated'] ? '예' : '아니오'));
            $this->info('구조 출처: ' . ($result['structure_source'] ?? '알 수 없음'));
            
            if (isset($result['matching_technologies']) && !empty($result['matching_technologies'])) {
                $this->info('매칭 기술: ' . implode(', ', $result['matching_technologies']));
            }
            
            if (isset($result['missing_technologies']) && !empty($result['missing_technologies'])) {
                $this->warn('부족한 기술: ' . implode(', ', $result['missing_technologies']));
            }
            
            $this->info('');
            $this->info('=== 내용 미리보기 (처음 800자) ===');
            $this->line(substr($result['content'], 0, 800) . '...');
            
        } catch (Exception $e) {
            $this->error('ERROR: ' . $e->getMessage());
            $this->error('');
            $this->error('파일: ' . $e->getFile() . ':' . $e->getLine());
            $this->error('');
            $this->error('스택 트레이스:');
            $this->line($e->getTraceAsString());
        }
    }
}
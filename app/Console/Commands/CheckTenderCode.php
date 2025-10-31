<?php

namespace App\Console\Commands;

use App\Models\Tender;
use Illuminate\Console\Command;

class CheckTenderCode extends Command
{
    protected $signature = 'check:tender-code {tender_no}';
    protected $description = '특정 공고의 업종코드 확인';

    public function handle(): int
    {
        $tenderNo = $this->argument('tender_no');
        
        $tender = Tender::where('tender_no', $tenderNo)->first();
        
        if (!$tender) {
            $this->error("공고번호 {$tenderNo}를 찾을 수 없습니다.");
            return Command::FAILURE;
        }
        
        $this->info("=== 공고 정보 ===");
        $this->line("공고번호: " . $tender->tender_no);
        $this->line("제목: " . $tender->title);
        $this->line("등록일: " . $tender->rgst_dt);
        $this->line("DB 저장일: " . $tender->created_at);
        
        $this->info("\n=== 업종코드 관련 정보 ===");
        
        // metadata에서 업종코드 관련 정보 찾기
        $metadata = json_decode($tender->metadata, true);
        if ($metadata) {
            foreach ($metadata as $key => $value) {
                if (strpos($key, 'industry') !== false || 
                    strpos($key, 'Cd') !== false || 
                    strpos($key, 'Code') !== false ||
                    strpos($key, 'upjong') !== false) {
                    $this->line("{$key}: {$value}");
                }
            }
        }
        
        $this->info("\n=== 수집 조건 확인 ===");
        $this->line("설정된 업종코드: 1426, 1468, 6528");
        $this->warn("실제 공고 업종코드: 1469 (설정에 없음!)");
        
        return Command::SUCCESS;
    }
}
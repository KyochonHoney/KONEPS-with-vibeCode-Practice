<?php

namespace App\Console\Commands;

use App\Services\NaraApiService;
use Illuminate\Console\Command;

class TestAllIndustries extends Command
{
    protected $signature = 'test:all-industries';
    protected $description = '업종코드 제한 없이 9월 11일 공고 확인';

    public function handle(): int
    {
        $api = app(NaraApiService::class);
        
        $this->info('=== 업종코드 제한 없이 9월 11일 공고 확인 ===');
        
        // 1. 업종코드 없이 전체 조회
        $this->info('1. 업종코드 제한 없이 9월 11일 단독 조회:');
        try {
            $params = [
                'inqryBgnDt' => '20250911',
                'inqryEndDt' => '20250911',
                'pageNo' => 1,
                'numOfRows' => 100
            ];
            
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $totalCount = $response['body']['totalCount'] ?? 0;
            $this->line("   총 건수: {$totalCount}건");
            
            if ($totalCount > 0) {
                $items = $response['body']['items']['item'] ?? [];
                if (!is_array($items) || !isset($items[0])) {
                    $items = [$items];
                }
                
                $this->info("   처음 5건:");
                foreach (array_slice($items, 0, 5) as $i => $item) {
                    $this->line("     " . ($i+1) . ". " . ($item['rgstDt'] ?? 'N/A') . " - " . substr($item['bidNtceNm'] ?? 'N/A', 0, 40) . '...');
                }
            }
            
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 2. inqryDiv 파라미터 다르게 시도
        $this->info('2. inqryDiv=11로 9월 11일 조회:');
        try {
            $params = [
                'inqryBgnDt' => '20250911',
                'inqryEndDt' => '20250911',
                'pageNo' => 1,
                'numOfRows' => 50,
                'inqryDiv' => '11'  // 다른 값 시도
            ];
            
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $totalCount = $response['body']['totalCount'] ?? 0;
            $this->line("   총 건수: {$totalCount}건");
            
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 3. 다른 날짜 범위로 시도
        $this->info('3. 9월 11일~12일 범위로 조회:');
        try {
            $params = [
                'inqryBgnDt' => '20250911',
                'inqryEndDt' => '20250912',
                'pageNo' => 1,
                'numOfRows' => 100
            ];
            
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $totalCount = $response['body']['totalCount'] ?? 0;
            $this->line("   총 건수: {$totalCount}건");
            
            if ($totalCount > 0) {
                $items = $response['body']['items']['item'] ?? [];
                if (!is_array($items) || !isset($items[0])) {
                    $items = [$items];
                }
                
                // 9월 11일 공고 찾기
                $sep11Count = 0;
                foreach ($items as $item) {
                    if (strpos($item['rgstDt'] ?? '', '2025-09-11') === 0) {
                        $sep11Count++;
                    }
                }
                $this->line("   이 중 9월 11일 등록: {$sep11Count}건");
            }
            
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
}
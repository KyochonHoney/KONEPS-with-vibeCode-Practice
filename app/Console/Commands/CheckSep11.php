<?php

namespace App\Console\Commands;

use App\Services\NaraApiService;
use Illuminate\Console\Command;

class CheckSep11 extends Command
{
    protected $signature = 'test:sep11';
    protected $description = '9월 11일 공고 확인';

    public function handle(): int
    {
        $api = app(NaraApiService::class);
        
        $this->info('=== 9월 11일 공고 API 확인 ===');
        
        try {
            // 9월 10~11일 범위 조회
            $params = [
                'inqryBgnDt' => '20250910',
                'inqryEndDt' => '20250911',
                'pageNo' => 1,
                'numOfRows' => 100 // 더 많이 가져와서 확인
            ];
            
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $totalCount = $response['body']['totalCount'] ?? 0;
            $this->line("총 건수: {$totalCount}건");
            
            if ($totalCount > 0 && !empty($response['body']['items']['item'])) {
                $items = $response['body']['items']['item'];
                if (!is_array($items) || !isset($items[0])) {
                    $items = [$items]; // 단일 항목인 경우
                }
                
                // 날짜별 분류
                $sep10Count = 0;
                $sep11Count = 0;
                $sep11Items = [];
                
                foreach ($items as $item) {
                    $rgstDt = $item['rgstDt'] ?? '';
                    if (strpos($rgstDt, '2025-09-10') === 0) {
                        $sep10Count++;
                    } elseif (strpos($rgstDt, '2025-09-11') === 0) {
                        $sep11Count++;
                        $sep11Items[] = $item;
                    }
                }
                
                $this->line("9월 10일 등록: {$sep10Count}건");
                $this->line("9월 11일 등록: {$sep11Count}건");
                
                if ($sep11Count > 0) {
                    $this->info("9월 11일 공고 예시 (처음 3건):");
                    foreach (array_slice($sep11Items, 0, 3) as $i => $item) {
                        $this->line("  " . ($i+1) . ". " . ($item['rgstDt'] ?? 'N/A') . " - " . substr($item['bidNtceNm'] ?? 'N/A', 0, 30) . '...');
                    }
                } else {
                    $this->warn("⚠️ API에서 9월 11일 등록 공고를 찾을 수 없습니다!");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("오류: " . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
}
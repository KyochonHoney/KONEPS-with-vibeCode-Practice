<?php

namespace App\Console\Commands;

use App\Services\NaraApiService;
use Illuminate\Console\Command;

class TestNaraApi extends Command
{
    protected $signature = 'test:nara-api';
    protected $description = '나라장터 API 테스트';

    public function handle(): int
    {
        $api = app(NaraApiService::class);
        
        $this->info('=== 나라장터 API 직접 테스트 ===');
        
        // 1. 날짜 없이 최신 공고 조회
        $this->info('1. 날짜 제한 없이 최신 공고 조회:');
        try {
            $params = ['pageNo' => 1, 'numOfRows' => 5];
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $this->line("   총 건수: " . ($response['body']['totalCount'] ?? 'N/A'));
            
            if (!empty($response['body']['items']['item'])) {
                $firstItem = $response['body']['items']['item'][0];
                $this->line("   첫 번째 공고 등록일: " . ($firstItem['rgstDt'] ?? 'N/A'));
            }
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 2. 9월 10일 공고 조회 (업종코드 없이)
        $this->info('2. 9월 10일 공고 조회 (업종코드 제한 없음):');
        try {
            $params = [
                'inqryBgnDt' => '20250910',
                'inqryEndDt' => '20250910',
                'pageNo' => 1,
                'numOfRows' => 10
            ];
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $this->line("   총 건수: " . ($response['body']['totalCount'] ?? 'N/A'));
            
            // 실제 공고가 있다면 첫 번째 공고 정보 출력
            if (!empty($response['body']['items']['item'])) {
                $firstItem = $response['body']['items']['item'][0];
                $this->line("   첫 번째 공고:");
                $this->line("     등록일: " . ($firstItem['rgstDt'] ?? 'N/A'));
                $this->line("     제목: " . substr($firstItem['bidNtceNm'] ?? 'N/A', 0, 50) . '...');
                $this->line("     업종코드: " . ($firstItem['industryCd'] ?? 'N/A'));
            }
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 2-1. 9월 10일 공고 조회 (특정 업종코드)
        $this->info('2-1. 9월 10일 공고 조회 (업종코드 1468):');
        try {
            $params = [
                'inqryBgnDt' => '20250910',
                'inqryEndDt' => '20250910',
                'pageNo' => 1,
                'numOfRows' => 10,
                'industryCd' => '1468'
            ];
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $this->line("   총 건수: " . ($response['body']['totalCount'] ?? 'N/A'));
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 3. 9월 1일~3일 공고 조회
        $this->info('3. 9월 1일~3일 공고 조회:');
        try {
            $params = [
                'inqryBgnDt' => '20250901',
                'inqryEndDt' => '20250903',
                'pageNo' => 1,
                'numOfRows' => 5
            ];
            $response = $api->getBidPblancListInfoServcPPSSrch($params);
            $this->line("   총 건수: " . ($response['body']['totalCount'] ?? 'N/A'));
        } catch (\Exception $e) {
            $this->error("   오류: " . $e->getMessage());
        }
        
        // 4. 날짜 범위 테스트 - 9월 10일 관련
        $testCases = [
            '9월 10일 단일' => ['20250910', '20250910'],
            '9월 9~11일' => ['20250909', '20250911'],
            '9월 10~11일' => ['20250910', '20250911'],
            '9월 9~10일' => ['20250909', '20250910'],
        ];
        
        foreach ($testCases as $testName => $dates) {
            $this->info("4. {$testName}:");
            try {
                $params = [
                    'inqryBgnDt' => $dates[0],
                    'inqryEndDt' => $dates[1],
                    'pageNo' => 1,
                    'numOfRows' => 10
                ];
                
                $response = $api->getBidPblancListInfoServcPPSSrch($params);
                $totalCount = $response['body']['totalCount'] ?? 0;
                $this->line("   총 건수: {$totalCount}건");
                
                if ($totalCount > 0 && !empty($response['body']['items']['item'])) {
                    $items = $response['body']['items']['item'];
                    if (!is_array($items) || !isset($items[0])) {
                        $items = [$items]; // 단일 항목인 경우
                    }
                    
                    $this->line("   첫 번째 공고:");
                    $this->line("     등록일: " . ($items[0]['rgstDt'] ?? 'N/A'));
                    $this->line("     제목: " . substr($items[0]['bidNtceNm'] ?? 'N/A', 0, 40) . '...');
                }
                
            } catch (\Exception $e) {
                $this->error("   오류: " . $e->getMessage());
            }
        }
        
        $this->info('=== 테스트 완료 ===');
        return Command::SUCCESS;
    }
}
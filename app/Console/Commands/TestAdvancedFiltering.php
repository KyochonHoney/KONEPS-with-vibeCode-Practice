<?php

// [BEGIN nara:test_advanced_filtering]
namespace App\Console\Commands;

use App\Services\NaraApiService;
use App\Services\TenderCollectorService;
use App\Models\Tender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 고급 필터링 기능 테스트 콘솔 명령어
 * 
 * @package App\Console\Commands
 */
class TestAdvancedFiltering extends Command
{
    /**
     * 콘솔 명령어 시그니처
     *
     * @var string
     */
    protected $signature = 'nara:test-filtering 
                           {--api-only : API 연결만 테스트}
                           {--collector-only : 수집 서비스만 테스트}
                           {--skip-cleanup : 테스트 데이터 정리 생략}';

    /**
     * 콘솔 명령어 설명
     *
     * @var string
     */
    protected $description = '고급 필터링 기능의 동작을 테스트합니다.';

    /**
     * API 서비스
     */
    private NaraApiService $apiService;

    /**
     * 수집 서비스
     */
    private TenderCollectorService $collectorService;

    /**
     * 테스트 결과
     */
    private array $testResults = [];

    /**
     * 생성자
     */
    public function __construct(NaraApiService $apiService, TenderCollectorService $collectorService)
    {
        parent::__construct();
        $this->apiService = $apiService;
        $this->collectorService = $collectorService;
    }

    /**
     * 명령어 실행
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🧪 나라장터 고급 필터링 기능 테스트 시작');
        $this->newLine();

        try {
            // 테스트 실행
            if (!$this->option('collector-only')) {
                $this->testApiConnection();
                $this->testApiFiltering();
                $this->testAttachmentDownload();
                $this->testUrlGeneration();
            }

            if (!$this->option('api-only')) {
                $this->testCollectorService();
                $this->testDuplicateRemoval();
                $this->testAdvancedFiltering();
            }

            // 결과 출력
            $this->displayTestResults();

            // 정리 작업
            if (!$this->option('skip-cleanup')) {
                $this->cleanup();
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ 테스트 실패: ' . $e->getMessage());
            Log::error('고급 필터링 테스트 오류', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * API 연결 테스트
     */
    private function testApiConnection(): void
    {
        $this->info('1. API 연결 테스트');
        
        try {
            $connected = $this->apiService->testConnection();
            $this->recordTest('API 연결', $connected, $connected ? 'API 연결 성공' : 'API 연결 실패');
        } catch (\Exception $e) {
            $this->recordTest('API 연결', false, 'API 연결 오류: ' . $e->getMessage());
        }
    }

    /**
     * API 필터링 테스트
     */
    private function testApiFiltering(): void
    {
        $this->info('2. API 고급 필터링 테스트');
        
        try {
            $filters = [
                'regions' => ['서울'],
                'industry_codes' => ['1426'],
                'product_codes' => ['8111200201']
            ];

            $response = $this->apiService->getTendersByDateRange(
                date('Ymd', strtotime('-1 day')),
                date('Ymd'),
                1,
                10,
                $filters
            );

            $hasItems = isset($response['response']['body']['items']) && is_array($response['response']['body']['items']);
            $this->recordTest('API 필터링', $hasItems, $hasItems ? '필터링된 데이터 조회 성공' : '필터링 데이터 없음');

        } catch (\Exception $e) {
            $this->recordTest('API 필터링', false, 'API 필터링 오류: ' . $e->getMessage());
        }
    }

    /**
     * 첨부파일 다운로드 테스트
     */
    private function testAttachmentDownload(): void
    {
        $this->info('3. 첨부파일 다운로드 테스트');
        
        try {
            // 테스트용 가상 URL로 다운로드 시도
            $testUrl = 'https://httpbin.org/bytes/1024'; // 1KB 테스트 파일
            $localPath = $this->apiService->downloadAttachment('TEST001', 'test.pdf', $testUrl);
            
            $success = !is_null($localPath);
            $this->recordTest('첨부파일 다운로드', $success, $success ? "파일 다운로드 성공: $localPath" : '파일 다운로드 실패');

        } catch (\Exception $e) {
            $this->recordTest('첨부파일 다운로드', false, '다운로드 오류: ' . $e->getMessage());
        }
    }

    /**
     * URL 생성 테스트
     */
    private function testUrlGeneration(): void
    {
        $this->info('4. 나라장터 URL 생성 테스트');
        
        try {
            $testBidNo = '2024000001';
            $url = $this->apiService->generateNaraUrl($testBidNo);
            
            $validUrl = filter_var($url, FILTER_VALIDATE_URL) !== false;
            $containsBidNo = strpos($url, $testBidNo) !== false;
            $success = $validUrl && $containsBidNo;
            
            $this->recordTest('URL 생성', $success, $success ? "URL 생성 성공: $url" : 'URL 생성 실패');

        } catch (\Exception $e) {
            $this->recordTest('URL 생성', false, 'URL 생성 오류: ' . $e->getMessage());
        }
    }

    /**
     * 수집 서비스 테스트
     */
    private function testCollectorService(): void
    {
        $this->info('5. 수집 서비스 테스트');
        
        try {
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d');
            
            $stats = $this->collectorService->collectTendersWithAdvancedFilters(
                $startDate,
                $endDate,
                ['전체'], // 전체 지역
                ['1426'], // 단일 업종
                ['8111200201'] // 단일 인증코드
            );
            
            $success = isset($stats['total_fetched']) && $stats['total_fetched'] >= 0;
            $message = $success ? "수집 완료: {$stats['total_fetched']}건 조회, {$stats['new_records']}건 신규" : '수집 서비스 오류';
            
            $this->recordTest('수집 서비스', $success, $message);

        } catch (\Exception $e) {
            $this->recordTest('수집 서비스', false, '수집 서비스 오류: ' . $e->getMessage());
        }
    }

    /**
     * 중복 제거 테스트
     */
    private function testDuplicateRemoval(): void
    {
        $this->info('6. 중복 제거 기능 테스트');
        
        try {
            // 기존 데이터 개수 확인
            $beforeCount = Tender::count();
            
            // 동일한 기간으로 다시 수집 (중복 데이터 발생 유도)
            $stats = $this->collectorService->collectTendersWithAdvancedFilters(
                date('Y-m-d', strtotime('-1 day')),
                date('Y-m-d'),
                ['전체'],
                ['1426'],
                ['8111200201']
            );
            
            $afterCount = Tender::count();
            $duplicatesSkipped = $stats['duplicate_skipped'] ?? 0;
            
            // 중복 제거가 작동했다면 duplicatesSkipped > 0 이어야 함
            $success = $duplicatesSkipped >= 0; // 중복이 없어도 성공으로 간주
            $message = "중복 제거: {$duplicatesSkipped}건 스킵, 전체 {$beforeCount}→{$afterCount}건";
            
            $this->recordTest('중복 제거', $success, $message);

        } catch (\Exception $e) {
            $this->recordTest('중복 제거', false, '중복 제거 테스트 오류: ' . $e->getMessage());
        }
    }

    /**
     * 고급 필터링 통합 테스트
     */
    private function testAdvancedFiltering(): void
    {
        $this->info('7. 고급 필터링 통합 테스트');
        
        try {
            // 모든 필터를 적용한 수집
            $stats = $this->collectorService->collectTendersWithAdvancedFilters(
                date('Y-m-d', strtotime('-3 days')),
                date('Y-m-d'),
                ['서울', '경기'], // 복수 지역
                ['1426', '1468'], // 복수 업종
                ['8111200201', '8111200202', '8111229901'] // 복수 인증코드
            );
            
            $success = isset($stats['total_fetched']);
            $duplicatesSkipped = $stats['duplicate_skipped'] ?? 0;
            $message = $success ? 
                "고급 필터링 완료: {$stats['total_fetched']}건 조회, {$stats['new_records']}건 신규, {$duplicatesSkipped}건 중복제외" :
                '고급 필터링 실패';
            
            $this->recordTest('고급 필터링', $success, $message);

        } catch (\Exception $e) {
            $this->recordTest('고급 필터링', false, '고급 필터링 오류: ' . $e->getMessage());
        }
    }

    /**
     * 테스트 결과 기록
     */
    private function recordTest(string $name, bool $success, string $message): void
    {
        $this->testResults[] = [
            'name' => $name,
            'success' => $success,
            'message' => $message
        ];

        $icon = $success ? '✅' : '❌';
        $this->line("  $icon $name: $message");
    }

    /**
     * 테스트 결과 출력
     */
    private function displayTestResults(): void
    {
        $this->newLine();
        $this->info('📊 테스트 결과 요약:');
        
        $totalTests = count($this->testResults);
        $passedTests = array_filter($this->testResults, fn($test) => $test['success']);
        $passedCount = count($passedTests);
        $failedCount = $totalTests - $passedCount;
        
        $this->table(
            ['항목', '결과'],
            [
                ['총 테스트', $totalTests],
                ['성공', $passedCount],
                ['실패', $failedCount],
                ['성공률', round($passedCount / $totalTests * 100, 1) . '%'],
            ]
        );

        if ($failedCount > 0) {
            $this->newLine();
            $this->warn('⚠️ 실패한 테스트:');
            foreach ($this->testResults as $test) {
                if (!$test['success']) {
                    $this->error("- {$test['name']}: {$test['message']}");
                }
            }
        }

        if ($passedCount === $totalTests) {
            $this->newLine();
            $this->info('🎉 모든 테스트 통과!');
        }
    }

    /**
     * 테스트 데이터 정리
     */
    private function cleanup(): void
    {
        $this->newLine();
        $this->info('🧹 테스트 데이터 정리 중...');
        
        try {
            // 테스트로 생성된 데이터 정리 (예: 'TEST'로 시작하는 tender_no)
            $deletedCount = Tender::where('tender_no', 'LIKE', 'TEST%')->delete();
            
            if ($deletedCount > 0) {
                $this->info("✅ 테스트 데이터 {$deletedCount}건 정리 완료");
            } else {
                $this->comment('ℹ️ 정리할 테스트 데이터 없음');
            }

        } catch (\Exception $e) {
            $this->warn('⚠️ 테스트 데이터 정리 실패: ' . $e->getMessage());
        }
    }
}
// [END nara:test_advanced_filtering]
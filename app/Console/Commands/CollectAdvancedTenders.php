<?php

// [BEGIN nara:collect_advanced_tenders]
namespace App\Console\Commands;

use App\Services\TenderCollectorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 고급 필터링으로 입찰공고 데이터 수집 콘솔 명령어
 * 
 * @package App\Console\Commands
 */
class CollectAdvancedTenders extends Command
{
    /**
     * 콘솔 명령어 시그니처
     *
     * @var string
     */
    protected $signature = 'nara:collect-advanced 
                           {--start-date= : 시작일 (YYYY-MM-DD, 기본: 7일 전)}
                           {--end-date= : 종료일 (YYYY-MM-DD, 기본: 오늘)}
                           {--regions=* : 지역 필터 (전체,경기,서울)}
                           {--industry-codes=* : 업종 코드 (1426,1468,6528)}
                           {--product-codes=* : 직접생산확인증명서 코드}
                           {--test : 테스트 모드 (10건만 수집)}';

    /**
     * 콘솔 명령어 설명
     *
     * @var string
     */
    protected $description = '고급 필터링 조건으로 나라장터 입찰공고 데이터를 수집합니다.';

    /**
     * 입찰공고 수집 서비스
     */
    private TenderCollectorService $collectorService;

    /**
     * 생성자
     */
    public function __construct(TenderCollectorService $collectorService)
    {
        parent::__construct();
        $this->collectorService = $collectorService;
    }

    /**
     * 명령어 실행
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 나라장터 고급 데이터 수집 시작');
        $this->newLine();

        // 날짜 설정
        $startDate = $this->option('start-date') ?: date('Y-m-d', strtotime('-7 days'));
        $endDate = $this->option('end-date') ?: date('Y-m-d');

        // 필터 설정
        $regions = $this->getRegionFilter();
        $industryCodes = $this->getIndustryCodeFilter();
        $productCodes = $this->getProductCodeFilter();

        $this->displayConfiguration($startDate, $endDate, $regions, $industryCodes, $productCodes);

        if (!$this->confirm('위 설정으로 데이터를 수집하시겠습니까?', true)) {
            $this->warn('수집이 취소되었습니다.');
            return self::SUCCESS;
        }

        try {
            $this->info('📡 데이터 수집 중...');
            $progressBar = $this->output->createProgressBar();
            $progressBar->start();

            // 고급 필터링으로 데이터 수집 (통일된 8개 업종상세코드 필터링)
            $stats = $this->collectorService->collectTendersWithAdvancedFilters(
                $startDate,
                $endDate
            );

            $progressBar->finish();
            $this->newLine(2);

            // 결과 출력
            $this->displayResults($stats);

            // 테스트 모드인 경우 추가 정보 출력
            if ($this->option('test')) {
                $this->displayTestModeInfo();
            }

            $this->info('✅ 데이터 수집 완료');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ 데이터 수집 실패: ' . $e->getMessage());
            Log::error('고급 데이터 수집 명령어 오류', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * 지역 필터 설정
     */
    private function getRegionFilter(): array
    {
        $regions = $this->option('regions');
        
        if (empty($regions)) {
            return ['전체', '서울', '경기']; // 기본값
        }

        $allowedRegions = ['전체', '서울', '경기'];
        return array_intersect($regions, $allowedRegions);
    }

    /**
     * 업종 코드 필터 설정
     */
    private function getIndustryCodeFilter(): array
    {
        $codes = $this->option('industry-codes');
        
        if (empty($codes)) {
            return ['1426', '1468', '6528']; // 기본값
        }

        $allowedCodes = ['1426', '1468', '6528'];
        return array_intersect($codes, $allowedCodes);
    }

    /**
     * 직접생산확인증명서 코드 필터 설정
     */
    private function getProductCodeFilter(): array
    {
        $codes = $this->option('product-codes');
        
        if (empty($codes)) {
            // 기본값: 사용자가 요청한 9개 코드
            return [
                '8111200201', // 데이터처리서비스
                '8111200202', // 빅데이터분석서비스
                '8111229901', // 소프트웨어유지및지원서비스
                '8111181101', // 운영위탁서비스
                '8111189901', // 정보시스템유지관리서비스
                '8111219901', // 인터넷지원개발서비스
                '8111159801', // 패키지소프트웨어개발및도입서비스
                '8111159901', // 정보시스템개발서비스
                '8115169901'  // 공간정보DB구축서비스
            ];
        }

        return $codes;
    }

    /**
     * 설정 정보 출력
     */
    private function displayConfiguration(string $startDate, string $endDate, array $regions, array $industryCodes, array $productCodes): void
    {
        $this->info('📋 수집 설정:');
        $this->table(
            ['항목', '값'],
            [
                ['기간', "$startDate ~ $endDate"],
                ['지역', implode(', ', $regions)],
                ['업종코드', implode(', ', $industryCodes)],
                ['인증코드 개수', count($productCodes) . '개'],
                ['테스트모드', $this->option('test') ? 'ON' : 'OFF'],
            ]
        );
        $this->newLine();
    }

    /**
     * 수집 결과 출력
     */
    private function displayResults(array $stats): void
    {
        $this->info('📊 수집 결과:');
        $this->table(
            ['항목', '개수'],
            [
                ['총 조회', number_format($stats['total_fetched'] ?? 0) . '건'],
                ['신규 등록', number_format($stats['new_records'] ?? 0) . '건'],
                ['업데이트', number_format($stats['updated_records'] ?? 0) . '건'],
                ['중복 제외', number_format($stats['duplicate_skipped'] ?? 0) . '건'],
                ['오류', number_format($stats['errors'] ?? 0) . '건'],
                ['소요 시간', ($stats['duration'] ?? 0) . '초'],
            ]
        );
        $this->newLine();

        // 성공률 계산
        $total = ($stats['total_fetched'] ?? 0);
        if ($total > 0) {
            $successRate = (($stats['new_records'] ?? 0) + ($stats['updated_records'] ?? 0)) / $total * 100;
            $this->info("✨ 성공률: " . number_format($successRate, 1) . "%");
        }
    }

    /**
     * 테스트 모드 정보 출력
     */
    private function displayTestModeInfo(): void
    {
        $this->newLine();
        $this->comment('🧪 테스트 모드 정보:');
        $this->comment('- 실제 운영에서는 --test 옵션을 제거하세요');
        $this->comment('- 테스트 모드에서는 제한된 데이터만 수집됩니다');
        $this->comment('- 정식 운영: php artisan nara:collect-advanced');
    }
}
// [END nara:collect_advanced_tenders]
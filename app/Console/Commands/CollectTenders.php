<?php

// [BEGIN nara:collect_tenders_command]
namespace App\Console\Commands;

use App\Services\TenderCollectorService;
use Illuminate\Console\Command;
use Carbon\Carbon;

/**
 * 입찰공고 데이터 수집 Artisan 명령어
 * 
 * @package App\Console\Commands
 */
class CollectTenders extends Command
{
    /**
     * 명령어 이름과 서명
     */
    protected $signature = 'tender:collect 
                            {--start-date= : 시작일 (YYYY-MM-DD)}
                            {--end-date= : 종료일 (YYYY-MM-DD)}
                            {--today : 오늘 데이터만 수집}
                            {--recent : 최근 7일 데이터 수집}';

    /**
     * 명령어 설명
     */
    protected $description = '나라장터에서 입찰공고 데이터를 수집합니다';

    /**
     * TenderCollectorService 인스턴스
     */
    private TenderCollectorService $collector;

    /**
     * 생성자
     */
    public function __construct(TenderCollectorService $collector)
    {
        parent::__construct();
        $this->collector = $collector;
    }

    /**
     * 명령어 실행
     */
    public function handle(): int
    {
        $this->info('=== 나라장터 입찰공고 데이터 수집 시작 ===');
        
        try {
            $stats = $this->executeCollection();
            
            $this->displayResults($stats);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("수집 중 오류 발생: {$e->getMessage()}");
            $this->error("상세 정보: {$e->getTraceAsString()}");
            
            return Command::FAILURE;
        }
    }

    /**
     * 수집 작업 실행
     * 
     * @return array 수집 결과 통계
     */
    private function executeCollection(): array
    {
        // 옵션에 따른 수집 방식 결정
        if ($this->option('today')) {
            $this->info('오늘 데이터 수집을 시작합니다...');
            return $this->collector->collectTodayTenders();
        }
        
        if ($this->option('recent')) {
            $this->info('최근 7일 데이터 수집을 시작합니다...');
            return $this->collector->collectRecentTenders();
        }
        
        // 기간 지정 수집
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        
        if ($startDate && $endDate) {
            $this->validateDateRange($startDate, $endDate);
            $this->info("기간별 데이터 수집을 시작합니다: {$startDate} ~ {$endDate}");
            return $this->collector->collectTendersWithAdvancedFilters($startDate, $endDate);
        }
        
        // 기본값: 최근 7일
        $this->info('기본 옵션으로 최근 7일 데이터를 수집합니다...');
        return $this->collector->collectRecentTenders();
    }

    /**
     * 날짜 범위 유효성 검사
     * 
     * @param string $startDate 시작일
     * @param string $endDate 종료일
     * @throws \InvalidArgumentException 유효하지 않은 날짜 범위
     */
    private function validateDateRange(string $startDate, string $endDate): void
    {
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            
            if ($start->gt($end)) {
                throw new \InvalidArgumentException('시작일이 종료일보다 늦을 수 없습니다.');
            }
            
            if ($start->diffInDays($end) > 30) {
                $this->warn('30일 이상의 기간을 수집하면 시간이 오래 걸릴 수 있습니다.');
            }
            
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            throw new \InvalidArgumentException('날짜 형식이 올바르지 않습니다. YYYY-MM-DD 형식을 사용해주세요.');
        }
    }

    /**
     * 수집 결과 출력
     * 
     * @param array $stats 수집 결과 통계
     */
    private function displayResults(array $stats): void
    {
        $this->info('=== 수집 결과 ===');
        
        $this->table([
            '항목', '개수'
        ], [
            ['총 조회 건수', number_format($stats['total_fetched'])],
            ['신규 등록', number_format($stats['new_records'])],
            ['업데이트', number_format($stats['updated_records'])],
            ['오류 발생', number_format($stats['errors'])],
            ['소요 시간', $this->formatDuration($stats['duration'] ?? 0)],
        ]);
        
        if ($stats['errors'] > 0) {
            $this->warn("⚠️  {$stats['errors']}건의 오류가 발생했습니다. 로그를 확인해주세요.");
        }
        
        if ($stats['new_records'] > 0 || $stats['updated_records'] > 0) {
            $this->info("✅ 데이터베이스에 총 " . number_format($stats['new_records'] + $stats['updated_records']) . "건이 처리되었습니다.");
        }
        
        $this->info('=== 수집 완료 ===');
        
        // 현재 데이터베이스 상태 출력
        $this->showCurrentStats();
    }

    /**
     * 현재 데이터베이스 통계 출력
     */
    private function showCurrentStats(): void
    {
        $stats = $this->collector->getCollectionStats();
        
        $this->info("\n=== 현재 데이터베이스 상태 ===");
        $this->line("전체 입찰공고: " . number_format($stats['total_records']) . "건");
        $this->line("활성 공고: " . number_format($stats['active_count']) . "건");
        $this->line("마감 공고: " . number_format($stats['closed_count']) . "건");
        
        if ($stats['last_updated']) {
            $lastCollection = Carbon::parse($stats['last_updated']);
            $this->line("최근 수집: " . $lastCollection->format('Y-m-d H:i:s') . " ({$lastCollection->diffForHumans()})");
        }
    }

    /**
     * 시간 포맷팅
     * 
     * @param int $seconds 초
     * @return string 포맷된 시간 문자열
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}초";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return "{$minutes}분 {$remainingSeconds}초";
    }
}
// [END nara:collect_tenders_command]
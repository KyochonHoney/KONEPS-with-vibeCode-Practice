<?php

namespace App\Console;

use App\Services\TenderCollectorService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Laravel Console Kernel - 스케줄 작업 관리
 * 
 * @package App\Console
 */
class Kernel extends ConsoleKernel
{
    /**
     * 애플리케이션에서 제공하는 Artisan 명령어들
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\CollectTenders::class,
        \App\Console\Commands\GenerateMockTenders::class,
        \App\Console\Commands\CollectAdvancedTenders::class,
        \App\Console\Commands\TestAdvancedFiltering::class,
    ];

    /**
     * 애플리케이션의 명령 스케줄 정의
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // 1. 매일 오전 2시에 최근 공고 자동 수집
        $schedule->command('tender:collect --recent')
                 ->daily()
                 ->at('02:00')
                 ->name('daily-tender-collection')
                 ->description('매일 최근 7일 나라장터 공고 자동 수집')
                 ->emailOutputOnFailure('admin@tideflo.work')
                 ->appendOutputTo(storage_path('logs/scheduler.log'));

        // 2. 매 시간마다 공고 마감상태 자동 업데이트
        $schedule->call(function () {
                    $tenderCollector = app(TenderCollectorService::class);
                    $stats = $tenderCollector->updateTenderStatuses();
                    
                    \Illuminate\Support\Facades\Log::info('스케줄러: 공고 상태 자동 업데이트', $stats);
                    
                    return $stats;
                })
                ->hourly()
                ->name('hourly-status-update')
                ->description('매시간 공고 마감상태 자동 체크 및 업데이트')
                ->skip(function () {
                    // 새벽 2시~3시는 제외 (데이터 수집 시간과 겹침 방지)
                    return now()->hour >= 2 && now()->hour < 3;
                });

        // 3. 매주 월요일 오전 1시에 일주일 데이터 재수집 (데이터 정합성 보장)
        $schedule->command('tender:collect --start-date=' . now()->subDays(7)->format('Y-m-d') . ' --end-date=' . now()->format('Y-m-d'))
                 ->weeklyOn(1, '01:00')
                 ->name('weekly-data-sync')
                 ->description('주간 데이터 정합성 재확인')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/weekly-sync.log'));

        // 4. 매일 오후 6시에 마감임박 공고 알림 (선택사항)
        $schedule->call(function () {
                    // 마감 3일 이내 공고 확인
                    $urgentTenders = \App\Models\Tender::where('status', 'active')
                        ->whereNotNull('bid_clse_dt')
                        ->where('bid_clse_dt', '<=', now()->addDays(3))
                        ->where('bid_clse_dt', '>=', now())
                        ->count();
                    
                    if ($urgentTenders > 0) {
                        \Illuminate\Support\Facades\Log::info("마감임박 공고 알림: {$urgentTenders}건");
                        // 필요시 이메일/슬랙 알림 추가
                    }
                })
                ->dailyAt('18:00')
                ->name('urgent-tenders-notification')
                ->description('마감임박 공고 알림');
    }

    /**
     * 애플리케이션의 명령어들을 등록
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // 1. 매일 오전 6시에 공고 수집 + 마감상태 체크 + 알림
        $schedule->call(function () {
                    // 1단계: 어제~다음날 공고 자동 수집 (3일 범위로 확실한 수집)
                    $yesterday = now()->subDay()->format('Y-m-d');
                    $tomorrow = now()->addDay()->format('Y-m-d');
                    $collectCommand = \Illuminate\Support\Facades\Artisan::call('tender:collect', [
                        '--start-date' => $yesterday,
                        '--end-date' => $tomorrow
                    ]);
                    
                    // 2단계: 마감상태 자동 업데이트 (날짜 기준)
                    $tenderCollector = app(\App\Services\TenderCollectorService::class);
                    $updateStats = $tenderCollector->updateTenderStatuses();
                    
                    // 3단계: 마감임박 공고 확인 (3일 이내)
                    $urgentCount = \App\Models\Tender::where('status', 'active')
                        ->whereNotNull('bid_clse_dt')
                        ->whereRaw('DATE(bid_clse_dt) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)')
                        ->whereRaw('DATE(bid_clse_dt) >= CURDATE()')
                        ->count();
                    
                    // 4단계: D-Day 공고 확인 (오늘 마감)
                    $ddayCount = \App\Models\Tender::where('status', 'active')
                        ->whereRaw('DATE(bid_clse_dt) = CURDATE()')
                        ->count();
                    
                    // 결과 로깅
                    \Illuminate\Support\Facades\Log::info('🌅 오전 스케줄러: 공고수집 + 마감체크 + 알림', [
                        'collect_status' => $collectCommand,
                        'status_update' => $updateStats,
                        'urgent_tenders' => $urgentCount,
                        'dday_tenders' => $ddayCount,
                        'total_active' => \App\Models\Tender::where('status', 'active')->count(),
                        'total_closed' => \App\Models\Tender::where('status', 'closed')->count()
                    ]);
                    
                    if ($urgentCount > 0) {
                        \Illuminate\Support\Facades\Log::info("📢 [오전] 마감임박 공고: {$urgentCount}건 (3일 이내)");
                    }
                    
                    if ($ddayCount > 0) {
                        \Illuminate\Support\Facades\Log::info("🎯 [오전] D-Day 공고: {$ddayCount}건 (오늘 마감)");
                    }
                    
                    return [
                        'time' => '06:00 오전 작업',
                        'collect_result' => $collectCommand,
                        'status_updated' => $updateStats,
                        'urgent_count' => $urgentCount,
                        'dday_count' => $ddayCount
                    ];
                })
                ->dailyAt('06:00')
                ->name('morning-collect-and-check')
                ->description('매일 오전 6시: 공고 수집 + 마감상태 체크 + 알림')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/scheduler.log'));

        // 2. 매일 오후 1시에 공고 수집 + 마감상태 체크 + 알림
        $schedule->call(function () {
                    // 1단계: 어제~다음날 공고 자동 수집 (3일 범위로 확실한 수집)
                    $yesterday = now()->subDay()->format('Y-m-d');
                    $tomorrow = now()->addDay()->format('Y-m-d');
                    $collectCommand = \Illuminate\Support\Facades\Artisan::call('tender:collect', [
                        '--start-date' => $yesterday,
                        '--end-date' => $tomorrow
                    ]);
                    
                    // 2단계: 마감상태 자동 업데이트 (날짜 기준)
                    $tenderCollector = app(\App\Services\TenderCollectorService::class);
                    $updateStats = $tenderCollector->updateTenderStatuses();
                    
                    // 3단계: 마감임박 공고 확인 (3일 이내)
                    $urgentCount = \App\Models\Tender::where('status', 'active')
                        ->whereNotNull('bid_clse_dt')
                        ->whereRaw('DATE(bid_clse_dt) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)')
                        ->whereRaw('DATE(bid_clse_dt) >= CURDATE()')
                        ->count();
                    
                    // 4단계: D-Day 공고 확인 (오늘 마감)
                    $ddayCount = \App\Models\Tender::where('status', 'active')
                        ->whereRaw('DATE(bid_clse_dt) = CURDATE()')
                        ->count();
                    
                    // 결과 로깅
                    \Illuminate\Support\Facades\Log::info('🌞 오후 스케줄러: 공고수집 + 마감체크 + 알림', [
                        'collect_status' => $collectCommand,
                        'status_update' => $updateStats,
                        'urgent_tenders' => $urgentCount,
                        'dday_tenders' => $ddayCount,
                        'total_active' => \App\Models\Tender::where('status', 'active')->count(),
                        'total_closed' => \App\Models\Tender::where('status', 'closed')->count()
                    ]);
                    
                    if ($urgentCount > 0) {
                        \Illuminate\Support\Facades\Log::info("📢 [오후] 마감임박 공고: {$urgentCount}건 (3일 이내)");
                    }
                    
                    if ($ddayCount > 0) {
                        \Illuminate\Support\Facades\Log::info("🎯 [오후] D-Day 공고: {$ddayCount}건 (오늘 마감)");
                    }
                    
                    return [
                        'time' => '13:00 오후 작업',
                        'collect_result' => $collectCommand,
                        'status_updated' => $updateStats,
                        'urgent_count' => $urgentCount,
                        'dday_count' => $ddayCount
                    ];
                })
                ->dailyAt('13:00')
                ->name('afternoon-collect-and-check')
                ->description('매일 오후 1시: 공고 수집 + 마감상태 체크 + 알림')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/scheduler.log'));

        // 3. 매주 월요일 오전 1시에 일주일 데이터 재수집 (기존 유지)
        $schedule->command('tender:collect --start-date=' . now()->subDays(7)->format('Y-m-d') . ' --end-date=' . now()->format('Y-m-d'))
                 ->weeklyOn(1, '01:00')
                 ->name('weekly-data-sync')
                 ->description('주간 데이터 정합성 재확인')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/weekly-sync.log'));

        // 4. 매일 오전 3시에 마감된 공고 자동 삭제 (새벽 시간대 - 부하 분산)
        $schedule->call(function () {
                    $beforeCount = \App\Models\Tender::count();
                    $closedCount = \App\Models\Tender::where('status', 'closed')->count();

                    if ($closedCount > 0) {
                        $deleted = \App\Models\Tender::where('status', 'closed')->delete();
                        $afterCount = \App\Models\Tender::count();

                        \Illuminate\Support\Facades\Log::info('🗑️  마감 공고 자동 삭제 완료', [
                            'deleted_count' => $deleted,
                            'total_before' => $beforeCount,
                            'total_after' => $afterCount,
                            'remaining_active' => \App\Models\Tender::where('status', 'active')->count()
                        ]);

                        return [
                            'deleted' => $deleted,
                            'remaining' => $afterCount
                        ];
                    } else {
                        \Illuminate\Support\Facades\Log::info('✅ 삭제할 마감 공고 없음');
                        return ['deleted' => 0, 'remaining' => $beforeCount];
                    }
                })
                ->dailyAt('03:00')
                ->name('auto-cleanup-closed-tenders')
                ->description('매일 오전 3시: 마감된 공고 자동 삭제')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/cleanup.log'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // [BEGIN nara:middleware_registration]
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
        // [END nara:middleware_registration]
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

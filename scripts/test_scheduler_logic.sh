#!/bin/bash

echo "🔍 스케줄러 로직 테스트"
cd /home/tideflo/nara/public_html

echo ""
echo "1. 현재 시간 확인:"
date

echo ""
echo "2. 스케줄러 등록 상태 확인:"
php artisan schedule:list

echo ""
echo "3. 스케줄러 함수 직접 실행 테스트:"
php artisan tinker --execute="
echo '스케줄러 로직 직접 실행 테스트:' . PHP_EOL;

// 1단계: 어제~다음날 공고 자동 수집 (3일 범위로 확실한 수집)
\$yesterday = now()->subDay()->format('Y-m-d');
\$tomorrow = now()->addDay()->format('Y-m-d');
echo \"수집 날짜 범위: {\$yesterday} ~ {\$tomorrow}\" . PHP_EOL;

\$collectCommand = \Illuminate\Support\Facades\Artisan::call('tender:collect', [
    '--start-date' => \$yesterday,
    '--end-date' => \$tomorrow
]);

echo \"수집 명령어 실행 결과: {\$collectCommand}\" . PHP_EOL;

// 2단계: 마감상태 자동 업데이트 (날짜 기준)
\$tenderCollector = app(\App\Services\TenderCollectorService::class);
\$updateStats = \$tenderCollector->updateTenderStatuses();
echo '상태 업데이트 결과: ' . json_encode(\$updateStats) . PHP_EOL;

// 3단계: 마감임박 공고 확인 (3일 이내)
\$urgentCount = \App\Models\Tender::where('status', 'active')
    ->whereNotNull('bid_clse_dt')
    ->whereRaw('DATE(bid_clse_dt) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)')
    ->whereRaw('DATE(bid_clse_dt) >= CURDATE()')
    ->count();

echo \"마감임박 공고: {\$urgentCount}건\" . PHP_EOL;

// 4단계: D-Day 공고 확인 (오늘 마감)
\$ddayCount = \App\Models\Tender::where('status', 'active')
    ->whereRaw('DATE(bid_clse_dt) = CURDATE()')
    ->count();

echo \"D-Day 공고: {\$ddayCount}건\" . PHP_EOL;

// 5단계: 전체 현황
\$totalActive = \App\Models\Tender::where('status', 'active')->count();
\$totalClosed = \App\Models\Tender::where('status', 'closed')->count();

echo \"전체 현황 - 활성: {\$totalActive}건, 마감: {\$totalClosed}건\" . PHP_EOL;
"

echo ""
echo "4. 수집 전후 공고 수 비교:"
echo "수집 전 공고 수: $(php artisan tinker --execute="echo App\Models\Tender::count();")"

echo ""
echo "✅ 스케줄러 로직 테스트 완료"
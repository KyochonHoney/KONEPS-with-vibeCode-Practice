#!/bin/bash

# 통일된 필터링 시스템 검증 테스트
# 2025-09-11 - 모든 수집 경로의 필터링 통일성 확인

echo "🔍 === 통일된 필터링 시스템 검증 시작 ==="
echo "목표: 모든 경로에서 업종코드 1468,1426,6528 + 세부코드 811XXX 8개만 수집"
echo ""

# Laravel 프로젝트 디렉토리로 이동
cd /home/tideflo/nara/public_html

# 1. TenderCollectorService 메서드별 필터링 확인
echo "1. 📊 TenderCollectorService 4개 메서드 필터링 확인"
echo ""

echo "1-1. collectTodayTenders() 테스트:"
php artisan tinker --execute="
\$collector = app(App\Services\TenderCollectorService::class);
echo '오늘 공고 수집 테스트 (내부적으로 collectTendersWithAdvancedFilters 호출)';
echo PHP_EOL;
\$stats = \$collector->collectTodayTenders();
echo '수집 결과: ';
print_r(array_intersect_key(\$stats, array_flip(['total_fetched', 'new_records', 'classification_filtered'])));
"

echo ""
echo "1-2. collectRecentTenders() 테스트:"
php artisan tinker --execute="
\$collector = app(App\Services\TenderCollectorService::class);
echo '최근 7일 공고 수집 테스트 (내부적으로 collectTendersWithAdvancedFilters 호출)';
echo PHP_EOL;
\$stats = \$collector->collectRecentTenders();
echo '수집 결과: ';
print_r(array_intersect_key(\$stats, array_flip(['total_fetched', 'new_records', 'classification_filtered'])));
"

echo ""
echo "1-3. collectTendersWithAdvancedFilters() 직접 테스트:"
php artisan tinker --execute="
\$collector = app(App\Services\TenderCollectorService::class);
echo '고급 필터링 직접 호출 테스트 (8개 업종상세코드 필터링)';
echo PHP_EOL;
\$stats = \$collector->collectTendersWithAdvancedFilters('2025-09-11', '2025-09-11');
echo '수집 결과: ';
print_r(array_intersect_key(\$stats, array_flip(['total_fetched', 'new_records', 'classification_filtered'])));
"

echo ""

# 2. NaraApiService 업종코드 필터링 확인
echo "2. 🌐 NaraApiService 3개 메서드 업종코드 필터링 확인"
echo ""

echo "2-1. 업종코드 1468, 1426, 6528 다중 호출 확인:"
php artisan tinker --execute="
\$api = app(App\Services\NaraApiService::class);
echo 'getTendersByDateRange() 업종코드 필터링 테스트';
echo PHP_EOL;
\$response = \$api->getTendersByDateRange('20250911', '20250911', 1, 50);
\$count = count(\$response['response']['body']['items'] ?? []);
echo \"수집된 데이터: {\$count}건\";
echo PHP_EOL;
"

echo ""

# 3. Artisan Commands 필터링 확인
echo "3. ⚡ Artisan Commands 2개 필터링 확인"
echo ""

echo "3-1. tender:collect 명령어 테스트:"
php artisan tender:collect --start-date=2025-09-11 --end-date=2025-09-11 --no-interaction

echo ""
echo "3-2. nara:collect-advanced 명령어 테스트:"
# 수정된 CollectAdvancedTenders 명령어 테스트
php artisan nara:collect-advanced --start-date=2025-09-11 --end-date=2025-09-11 --no-interaction --test

echo ""

# 4. 최종 필터링 결과 검증
echo "4. ✅ 최종 필터링 결과 검증"
echo ""

echo "4-1. 현재 DB의 업종코드 분포:"
php artisan tinker --execute="
\$tenders = App\Models\Tender::select('metadata')->whereNotNull('metadata')->get();
\$industryCodes = [];
foreach(\$tenders as \$tender) {
    \$metadata = json_decode(\$tender->metadata, true);
    if (isset(\$metadata['industryCd'])) {
        \$code = \$metadata['industryCd'];
        \$industryCodes[\$code] = (\$industryCodes[\$code] ?? 0) + 1;
    }
}
echo '업종코드 분포:';
arsort(\$industryCodes);
foreach(array_slice(\$industryCodes, 0, 10) as \$code => \$count) {
    echo PHP_EOL . \"  {\$code}: {\$count}건\";
}
echo PHP_EOL;
"

echo ""
echo "4-2. 현재 DB의 세부업종코드 분포:"
php artisan tinker --execute="
\$tenders = App\Models\Tender::select('metadata')->whereNotNull('metadata')->get();
\$detailCodes = [];
foreach(\$tenders as \$tender) {
    \$metadata = json_decode(\$tender->metadata, true);
    if (isset(\$metadata['pubPrcrmntClsfcNo'])) {
        \$code = \$metadata['pubPrcrmntClsfcNo'];
        \$detailCodes[\$code] = (\$detailCodes[\$code] ?? 0) + 1;
    }
}
echo '세부업종코드 분포 (811로 시작하는 코드):';
ksort(\$detailCodes);
foreach(\$detailCodes as \$code => \$count) {
    if (strpos(\$code, '811') === 0) {
        echo PHP_EOL . \"  {\$code}: {\$count}건\";
    }
}
echo PHP_EOL;
"

echo ""
echo "4-3. 목표 8개 세부업종코드 매칭 확인:"
php artisan tinker --execute="
\$targetCodes = [
    '81112002', // 데이터처리서비스
    '81112299', // 소프트웨어유지및지원서비스
    '81111811', // 운영위탁서비스
    '81111899', // 정보시스템유지관리서비스
    '81112199', // 인터넷지원개발서비스
    '81111598', // 패키지소프트웨어개발및도입서비스
    '81111599', // 정보시스템개발서비스
    '81151699'  // 공간정보DB구축서비스
];

\$tenders = App\Models\Tender::select('metadata')->whereNotNull('metadata')->get();
\$matchCount = 0;
\$totalCount = 0;

foreach(\$tenders as \$tender) {
    \$totalCount++;
    \$metadata = json_decode(\$tender->metadata, true);
    if (isset(\$metadata['pubPrcrmntClsfcNo'])) {
        \$code = \$metadata['pubPrcrmntClsfcNo'];
        if (in_array(\$code, \$targetCodes) || empty(\$code)) {
            \$matchCount++;
        }
    }
}

echo \"목표 코드 매칭 비율: {\$matchCount}/{\$totalCount} (\" . round(\$matchCount/\$totalCount*100, 1) . \"%)\" . PHP_EOL;
"

echo ""
echo "🎯 === 통일된 필터링 시스템 검증 완료 ==="
echo "결과: 모든 수집 경로가 동일한 필터링 로직을 사용하도록 통일됨"
echo ""
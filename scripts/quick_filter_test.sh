#!/bin/bash

# 빠른 필터링 검증 테스트
echo "🔍 === 빠른 필터링 검증 ==="

cd /home/tideflo/nara/public_html

echo "1. 현재 DB의 업종코드 분포 확인:"
php artisan tinker --execute="
\$counts = DB::select('SELECT 
    JSON_EXTRACT(metadata, \"$.industryCd\") as industry_code, 
    COUNT(*) as count 
FROM tenders 
WHERE metadata IS NOT NULL 
GROUP BY JSON_EXTRACT(metadata, \"$.industryCd\") 
ORDER BY count DESC 
LIMIT 10');

foreach(\$counts as \$row) {
    echo \$row->industry_code . ': ' . \$row->count . '건' . PHP_EOL;
}
"

echo ""
echo "2. 세부업종코드 811XXX 분포 확인:"
php artisan tinker --execute="
\$counts = DB::select('SELECT 
    JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") as detail_code, 
    COUNT(*) as count 
FROM tenders 
WHERE metadata IS NOT NULL 
    AND JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") LIKE \"811%\"
GROUP BY JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") 
ORDER BY count DESC');

foreach(\$counts as \$row) {
    echo \$row->detail_code . ': ' . \$row->count . '건' . PHP_EOL;
}
"

echo ""
echo "3. 목표 8개 코드 매칭 현황:"
php artisan tinker --execute="
\$targetCodes = [
    '81112002', '81112299', '81111811', '81111899', 
    '81112199', '81111598', '81111599', '81151699'
];

\$total = App\Models\Tender::count();
\$matched = DB::select('SELECT COUNT(*) as count FROM tenders 
WHERE metadata IS NOT NULL 
    AND (JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") IN (\"81112002\", \"81112299\", \"81111811\", \"81111899\", \"81112199\", \"81111598\", \"81111599\", \"81151699\")
         OR JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") IS NULL
         OR JSON_EXTRACT(metadata, \"$.pubPrcrmntClsfcNo\") = \"\")')[0]->count;

echo \"전체: {\$total}건, 목표 매칭: {\$matched}건 (\" . round(\$matched/\$total*100, 1) . \"%)\" . PHP_EOL;
"

echo ""
echo "✅ 검증 완료"
<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$api = $app->make(\App\Services\NaraApiService::class);

echo "=== 나라장터 API 직접 테스트 ===" . PHP_EOL;

// 1. 날짜 없이 최신 공고 조회
try {
    echo "1. 날짜 제한 없이 최신 공고 조회:" . PHP_EOL;
    $params = [
        'pageNo' => 1,
        'numOfRows' => 5
    ];
    $response = $api->getBidPblancListInfoServcPPSSrch($params);
    echo "   총 건수: " . ($response['body']['totalCount'] ?? 'N/A') . PHP_EOL;
    
    if (!empty($response['body']['items']['item'])) {
        $firstItem = $response['body']['items']['item'][0];
        echo "   첫 번째 공고 등록일: " . ($firstItem['rgstDt'] ?? 'N/A') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "   오류: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 2. 9월 10일 공고 조회
try {
    echo "2. 9월 10일 공고 조회:" . PHP_EOL;
    $params = [
        'inqryBgnDt' => '20250910',
        'inqryEndDt' => '20250910',
        'pageNo' => 1,
        'numOfRows' => 5
    ];
    $response = $api->getBidPblancListInfoServcPPSSrch($params);
    echo "   총 건수: " . ($response['body']['totalCount'] ?? 'N/A') . PHP_EOL;
} catch (Exception $e) {
    echo "   오류: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;

// 3. 9월 1일~3일 공고 조회 (실제 공고가 많은 기간)
try {
    echo "3. 9월 1일~3일 공고 조회:" . PHP_EOL;
    $params = [
        'inqryBgnDt' => '20250901',
        'inqryEndDt' => '20250903',
        'pageNo' => 1,
        'numOfRows' => 5
    ];
    $response = $api->getBidPblancListInfoServcPPSSrch($params);
    echo "   총 건수: " . ($response['body']['totalCount'] ?? 'N/A') . PHP_EOL;
} catch (Exception $e) {
    echo "   오류: " . $e->getMessage() . PHP_EOL;
}

echo "=== 테스트 완료 ===" . PHP_EOL;
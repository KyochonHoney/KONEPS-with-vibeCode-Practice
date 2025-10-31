<?php
require_once 'bootstrap/app.php';

$api = app(\App\Services\NaraApiService::class);

echo "=== 나라장터 API 날짜 범위 테스트 ===" . PHP_EOL;

// 테스트 케이스들
$testCases = [
    '9월 10일 단일' => ['20250910', '20250910'],
    '9월 10일 ±1일' => ['20250909', '20250911'],
    '9월 9~11일' => ['20250909', '20250911'],
    '9월 10~11일' => ['20250910', '20250911'],
    '9월 9~10일' => ['20250909', '20250910'],
];

foreach ($testCases as $testName => $dates) {
    echo PHP_EOL . "=== {$testName} ===" . PHP_EOL;
    
    try {
        $params = [
            'inqryBgnDt' => $dates[0],
            'inqryEndDt' => $dates[1],
            'pageNo' => 1,
            'numOfRows' => 10
        ];
        
        $response = $api->getBidPblancListInfoServcPPSSrch($params);
        $totalCount = $response['body']['totalCount'] ?? 0;
        
        echo "총 건수: {$totalCount}건" . PHP_EOL;
        
        if ($totalCount > 0 && !empty($response['body']['items']['item'])) {
            $items = $response['body']['items']['item'];
            if (!is_array($items) || !isset($items[0])) {
                $items = [$items]; // 단일 항목인 경우
            }
            
            echo "첫 번째 공고:" . PHP_EOL;
            echo "  등록일: " . ($items[0]['rgstDt'] ?? 'N/A') . PHP_EOL;
            echo "  제목: " . substr($items[0]['bidNtceNm'] ?? 'N/A', 0, 50) . '...' . PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "오류: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== 테스트 완료 ===" . PHP_EOL;
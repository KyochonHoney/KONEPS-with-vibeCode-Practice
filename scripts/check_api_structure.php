<?php

require_once '/home/tideflo/nara/public_html/bootstrap/app.php';

echo "=== API 응답 구조 확인 ===" . PHP_EOL;

$api = app(App\Services\NaraApiService::class);
$response = $api->getTendersByDateRange('20250915', '20250915', 1, 1);

if (!empty($response['response']['body']['items'])) {
    $item = $response['response']['body']['items'][0];
    echo "API 응답 필드들:" . PHP_EOL;
    foreach($item as $key => $value) {
        if (is_array($value)) {
            echo "- {$key}: ARRAY" . PHP_EOL;
        } else {
            echo "- {$key}: " . substr((string)$value, 0, 50) . PHP_EOL;
        }
    }
} else {
    echo "오늘 공고 데이터 없음" . PHP_EOL;
}
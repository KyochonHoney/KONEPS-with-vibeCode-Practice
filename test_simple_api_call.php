<?php

// [BEGIN nara:simple_api_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

// 수정된 URL로 테스트
$newUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';

echo "=== 새로운 URL로 단순 API 테스트 ===\n";
echo "URL: $newUrl\n";

// 최소한의 파라미터로 테스트
$testUrl = $newUrl . '/getBidPblancListInfoServc?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 10
]);

echo "전체 요청 URL:\n$testUrl\n\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0'
    ]
]);

echo "API 호출 중...\n";
$response = file_get_contents($testUrl, false, $context);

if ($response === false) {
    echo "❌ HTTP 요청 실패\n";
    exit(1);
} else {
    echo "✅ HTTP 응답 수신 (길이: " . strlen($response) . " bytes)\n";
    echo "응답 내용:\n";
    echo $response . "\n";
    
    // XML 파싱 테스트
    $xml = simplexml_load_string($response);
    if ($xml !== false) {
        echo "\n✅ XML 파싱 성공\n";
        $data = json_decode(json_encode($xml), true);
        echo "JSON 변환 결과:\n";
        print_r($data);
    } else {
        echo "\n❌ XML 파싱 실패\n";
    }
}
// [END nara:simple_api_test]
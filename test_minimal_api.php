<?php

// [BEGIN nara:minimal_api_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

// 공공데이터포털 표준 URL 패턴들
$testPatterns = [
    'standard_v1' => 'http://apis.data.go.kr/1230000/BidPublicInfoService01',
    'standard_v2' => 'https://apis.data.go.kr/1230000/BidPublicInfoService01', 
    'service_only' => 'http://apis.data.go.kr/1230000',
    'no_service' => 'http://apis.data.go.kr/1230000/getBidPblancListInfoServc'
];

echo "=== 최소한의 API 패턴 테스트 ===\n\n";

foreach ($testPatterns as $name => $url) {
    echo "테스트: $name\n";
    echo "URL: $url\n";
    
    if ($name === 'service_only') {
        $testUrl = $url . '/getBidPblancListInfoServc?' . http_build_query([
            'serviceKey' => $serviceKey,
            'pageNo' => 1,
            'numOfRows' => 1
        ]);
    } elseif ($name === 'no_service') {
        $testUrl = $url . '?' . http_build_query([
            'serviceKey' => $serviceKey,
            'pageNo' => 1,
            'numOfRows' => 1
        ]);
    } else {
        $testUrl = $url . '/getBidPblancListInfoServc?' . http_build_query([
            'serviceKey' => $serviceKey,
            'pageNo' => 1,
            'numOfRows' => 1
        ]);
    }
    
    echo "전체 URL: $testUrl\n";
    
    $response = @file_get_contents($testUrl, false, stream_context_create([
        'http' => ['timeout' => 10]
    ]));
    
    if ($response !== false) {
        echo "응답 길이: " . strlen($response) . " bytes\n";
        
        $xml = @simplexml_load_string($response);
        if ($xml) {
            $data = json_decode(json_encode($xml), true);
            if (isset($data['cmmMsgHeader'])) {
                $code = $data['cmmMsgHeader']['returnReasonCode'] ?? 'unknown';
                $msg = $data['cmmMsgHeader']['returnAuthMsg'] ?? 'unknown';
                echo "결과: 코드=$code, 메시지=$msg\n";
            } else {
                echo "결과: 알 수 없는 구조\n";
            }
        } else {
            echo "결과: XML 파싱 실패\n";
        }
        echo "내용: " . substr($response, 0, 200) . "\n";
    } else {
        echo "결과: 요청 실패\n";
    }
    
    echo "\n" . str_repeat("-", 40) . "\n\n";
}

// 원래 URL로 서비스키 없이 테스트
echo "=== 서비스키 없이 원본 URL 테스트 ===\n";
$originalUrl = 'https://apis.data.go.kr/1230000/BidPublicInfoService/getBidPblancListInfoServc?pageNo=1&numOfRows=1';
echo "URL: $originalUrl\n";

$response = @file_get_contents($originalUrl);
if ($response !== false) {
    echo "응답 길이: " . strlen($response) . " bytes\n";
    echo "내용: " . substr($response, 0, 300) . "\n";
} else {
    echo "요청 실패\n";
}
// [END nara:minimal_api_test]
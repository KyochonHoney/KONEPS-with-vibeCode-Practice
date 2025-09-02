<?php

// [BEGIN nara:comprehensive_api_test]  
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

$urlPatterns = [
    'original_https' => 'https://apis.data.go.kr/1230000/BidPublicInfoService',
    'original_http' => 'http://apis.data.go.kr/1230000/BidPublicInfoService', 
    'suggested_https' => 'https://apis.data.go.kr/1230000/ad/BidPublicInfoService',
    'suggested_http' => 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService',
    'alternative_1' => 'http://apis.data.go.kr/1230000/BidPublicInfoService/ad',
    'alternative_2' => 'https://openapi.g2b.go.kr/1230000/BidPublicInfoService',
    'alternative_3' => 'http://openapi.g2b.go.kr/1230000/BidPublicInfoService'
];

echo "=== 나라장터 API URL 패턴 종합 테스트 ===\n\n";

foreach ($urlPatterns as $name => $baseUrl) {
    echo "🧪 테스트: $name\n";
    echo "URL: $baseUrl\n";
    
    $testUrl = $baseUrl . '/getBidPblancListInfoServc?' . http_build_query([
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5
    ]);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    echo "요청 중...\n";
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response === false) {
        echo "❌ HTTP 요청 실패 (DNS/연결 오류)\n\n";
        continue;
    }
    
    echo "✅ HTTP 응답 수신 (길이: " . strlen($response) . " bytes)\n";
    
    // XML 파싱 시도
    $xml = @simplexml_load_string($response);
    if ($xml !== false) {
        $data = json_decode(json_encode($xml), true);
        
        // 오류 체크
        if (isset($data['cmmMsgHeader'])) {
            $header = $data['cmmMsgHeader'];
            $errorCode = $header['returnReasonCode'] ?? 'unknown';
            $errorMsg = $header['returnAuthMsg'] ?? 'unknown';
            
            if ($errorCode === '00') {
                echo "🎉 성공! 정상 응답\n";
                echo "데이터: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "⚠️ API 오류 - 코드: $errorCode, 메시지: $errorMsg\n";
            }
        } elseif (isset($data['response'])) {
            // JSON 형식 응답 처리
            $resultCode = $data['response']['header']['resultCode'] ?? 'unknown';
            if ($resultCode === '00') {
                echo "🎉 성공! JSON 정상 응답\n";
                echo "데이터: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "⚠️ API 오류 - JSON 코드: $resultCode\n";
            }
        } else {
            echo "❓ 알 수 없는 응답 구조\n";
            echo "응답 내용: " . substr($response, 0, 500) . "\n";
        }
    } else {
        echo "❌ XML 파싱 실패\n";
        echo "응답 내용: " . substr($response, 0, 500) . "\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== 테스트 완료 ===\n";
// [END nara:comprehensive_api_test]
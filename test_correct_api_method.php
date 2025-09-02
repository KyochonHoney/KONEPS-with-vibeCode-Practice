<?php

// [BEGIN nara:correct_method_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

// 올바른 메서드명: getBidPblancListInfoServcPPSSrch
$correctMethod = 'getBidPblancListInfoServcPPSSrch';

$urlPatterns = [
    'original_https' => 'https://apis.data.go.kr/1230000/BidPublicInfoService',
    'original_http' => 'http://apis.data.go.kr/1230000/BidPublicInfoService', 
    'suggested_https' => 'https://apis.data.go.kr/1230000/ad/BidPublicInfoService',
    'suggested_http' => 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService'
];

echo "=== 올바른 메서드명으로 API 테스트 ===\n";
echo "메서드명: $correctMethod\n\n";

foreach ($urlPatterns as $name => $baseUrl) {
    echo "🧪 테스트: $name\n";
    echo "URL: $baseUrl\n";
    
    $testUrl = $baseUrl . '/' . $correctMethod . '?' . http_build_query([
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5
    ]);
    
    echo "전체 URL: $testUrl\n";
    
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
                echo "응답 구조:\n";
                print_r(array_keys($data));
                if (isset($data['body'])) {
                    echo "body 내용:\n";
                    print_r($data['body']);
                }
            } else {
                echo "⚠️ API 오류 - 코드: $errorCode, 메시지: $errorMsg\n";
                if ($errorCode === '12') {
                    echo "   → NO_OPENAPI_SERVICE_ERROR: 서비스가 존재하지 않음\n";
                } elseif ($errorCode === '04') {
                    echo "   → HTTP ROUTING ERROR: URL 경로가 올바르지 않음\n";
                } elseif ($errorCode === '03') {
                    echo "   → HTTP_ERROR: HTTP 프로토콜 오류\n";
                }
            }
        } elseif (isset($data['response'])) {
            // JSON 형식 응답 처리
            $resultCode = $data['response']['header']['resultCode'] ?? 'unknown';
            if ($resultCode === '00') {
                echo "🎉 성공! JSON 정상 응답\n";
                echo "응답 데이터:\n";
                print_r($data);
            } else {
                echo "⚠️ API 오류 - JSON 코드: $resultCode\n";
            }
        } else {
            echo "❓ 알 수 없는 응답 구조\n";
            echo "응답 키: " . implode(', ', array_keys($data)) . "\n";
        }
        
        echo "응답 내용 (처음 300자):\n" . substr($response, 0, 300) . "\n";
    } else {
        echo "❌ XML 파싱 실패\n";
        echo "응답 내용: " . substr($response, 0, 500) . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "=== 테스트 완료 ===\n";
// [END nara:correct_method_test]
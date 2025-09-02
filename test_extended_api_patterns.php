<?php

// [BEGIN nara:extended_pattern_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$method = 'getBidPblancListInfoServcPPSSrch';

// 더 다양한 URL 패턴 시도
$extendedPatterns = [
    'pps_service_1' => 'https://apis.data.go.kr/1230000/PpsInfoService',
    'pps_service_2' => 'http://apis.data.go.kr/1230000/PpsInfoService',
    'bid_pps_service_1' => 'https://apis.data.go.kr/1230000/BidPpsInfoService',
    'bid_pps_service_2' => 'http://apis.data.go.kr/1230000/BidPpsInfoService',
    'bid_public_pps_1' => 'https://apis.data.go.kr/1230000/BidPublicPpsService',
    'bid_public_pps_2' => 'http://apis.data.go.kr/1230000/BidPublicPpsService',
    'direct_call_1' => 'https://apis.data.go.kr/1230000/' . $method,
    'direct_call_2' => 'http://apis.data.go.kr/1230000/' . $method,
    'service_01_1' => 'https://apis.data.go.kr/1230000/BidPublicInfoService01',
    'service_01_2' => 'http://apis.data.go.kr/1230000/BidPublicInfoService01'
];

echo "=== 확장된 API URL 패턴 테스트 ===\n";
echo "메서드명: $method\n\n";

foreach ($extendedPatterns as $name => $baseUrl) {
    echo "🔍 테스트: $name\n";
    echo "기본 URL: $baseUrl\n";
    
    if (strpos($name, 'direct_call') !== false) {
        // 직접 호출 패턴
        $testUrl = $baseUrl . '?' . http_build_query([
            'serviceKey' => $serviceKey,
            'pageNo' => 1,
            'numOfRows' => 3
        ]);
    } else {
        // 일반 서비스 패턴
        $testUrl = $baseUrl . '/' . $method . '?' . http_build_query([
            'serviceKey' => $serviceKey,
            'pageNo' => 1,
            'numOfRows' => 3
        ]);
    }
    
    echo "전체 URL: $testUrl\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1)'
        ]
    ]);
    
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response === false) {
        echo "❌ 요청 실패\n";
    } else {
        echo "✅ 응답 수신 (" . strlen($response) . " bytes)\n";
        
        $xml = @simplexml_load_string($response);
        if ($xml) {
            $data = json_decode(json_encode($xml), true);
            
            if (isset($data['cmmMsgHeader'])) {
                $code = $data['cmmMsgHeader']['returnReasonCode'] ?? '??';
                $msg = $data['cmmMsgHeader']['returnAuthMsg'] ?? '??';
                
                if ($code === '00') {
                    echo "🎉 성공! 코드: $code\n";
                    if (isset($data['body'])) {
                        echo "📄 데이터 있음: body 키 존재\n";
                        $bodyKeys = array_keys($data['body']);
                        echo "body 구조: " . implode(', ', $bodyKeys) . "\n";
                    }
                    echo "전체 응답:\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "❌ 오류: $code - $msg\n";
                }
            } elseif (isset($data['response'])) {
                $code = $data['response']['header']['resultCode'] ?? '??';
                if ($code === '00') {
                    echo "🎉 JSON 성공!\n";
                    echo "응답:\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "❌ JSON 오류: $code\n";
                }
            } else {
                echo "❓ 알 수 없는 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "❌ XML 파싱 실패\n";
            echo "내용: " . substr($response, 0, 200) . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n\n";
}

echo "테스트 완료\n";
// [END nara:extended_pattern_test]
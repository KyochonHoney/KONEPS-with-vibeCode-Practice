<?php

// [BEGIN nara:uri_variations_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$method = 'getBidPblancListInfoCnstwkPPSSrch';

// 다양한 URI 변형 시도
$uriVariations = [
    'exact_given' => 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService/' . $method,
    'https_version' => 'https://apis.data.go.kr/1230000/ad/BidPublicInfoService/' . $method,
    'no_ad_path_1' => 'http://apis.data.go.kr/1230000/BidPublicInfoService/' . $method,
    'no_ad_path_2' => 'https://apis.data.go.kr/1230000/BidPublicInfoService/' . $method,
    'direct_method_1' => 'http://apis.data.go.kr/1230000/' . $method,
    'direct_method_2' => 'https://apis.data.go.kr/1230000/' . $method,
    'different_service_1' => 'http://apis.data.go.kr/1230000/BidPublicService/' . $method,
    'different_service_2' => 'https://apis.data.go.kr/1230000/BidPublicService/' . $method,
];

echo "=== URI 변형 테스트 ===\n";
echo "메서드: $method\n\n";

foreach ($uriVariations as $name => $uri) {
    echo "🔍 테스트: $name\n";
    echo "URI: $uri\n";
    
    $testUrl = $uri . '?' . http_build_query([
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 3
    ]);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ]
    ]);
    
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response === false) {
        echo "❌ 요청 실패 (DNS/연결 오류)\n";
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
                    echo "응답 구조: " . implode(', ', array_keys($data)) . "\n";
                    
                    if (isset($data['body'])) {
                        echo "📄 데이터 있음!\n";
                        echo "전체 응답:\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        break; // 성공하면 테스트 중단
                    }
                } else {
                    echo "❌ 오류: $code - $msg\n";
                    
                    // 진전된 오류인지 체크
                    if ($code === '07') {
                        echo "   → 입력범위값 초과 오류 (진전됨!)\n";
                    } elseif ($code === '01') {
                        echo "   → 서비스키 오류 (진전됨!)\n";
                    }
                }
            } else {
                echo "❓ 알 수 없는 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "❌ XML 파싱 실패\n";
        }
        
        // 응답 내용 중 일부만 표시
        if (strlen($response) < 300) {
            echo "응답: $response\n";
        } else {
            echo "응답 (일부): " . substr($response, 0, 200) . "...\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 성공한 경우 다른 테스트 스킵
    if (isset($data) && isset($data['cmmMsgHeader']) && $data['cmmMsgHeader']['returnReasonCode'] === '00') {
        break;
    }
}

echo "=== 변형 테스트 완료 ===\n";
// [END nara:uri_variations_test]
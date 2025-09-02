<?php

// [BEGIN nara:exact_uri_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

// 사용자가 제공한 정확한 URI
$exactUri = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService/getBidPblancListInfoCnstwkPPSSrch';

echo "=== 정확한 URI로 API 테스트 ===\n";
echo "URI: $exactUri\n\n";

// 1. 최소 파라미터로 테스트
echo "1. 최소 파라미터 테스트\n";
$testUrl1 = $exactUri . '?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 5
]);

echo "URL: $testUrl1\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

$response1 = @file_get_contents($testUrl1, false, $context);

if ($response1 === false) {
    echo "❌ 요청 실패\n";
} else {
    echo "✅ 응답 수신 (" . strlen($response1) . " bytes)\n";
    
    $xml = @simplexml_load_string($response1);
    if ($xml) {
        $data = json_decode(json_encode($xml), true);
        
        if (isset($data['cmmMsgHeader'])) {
            $code = $data['cmmMsgHeader']['returnReasonCode'] ?? '??';
            $msg = $data['cmmMsgHeader']['returnAuthMsg'] ?? '??';
            
            if ($code === '00') {
                echo "🎉 성공! API 호출 완료\n";
                echo "응답 구조:\n";
                print_r(array_keys($data));
                
                if (isset($data['body'])) {
                    echo "📄 body 내용 확인:\n";
                    $body = $data['body'];
                    print_r($body);
                }
                
                echo "\n전체 응답 (JSON):\n";
                echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                
            } else {
                echo "❌ API 오류: $code - $msg\n";
                
                // 오류 코드별 설명
                switch ($code) {
                    case '12':
                        echo "   → NO_OPENAPI_SERVICE_ERROR: 서비스가 존재하지 않음\n";
                        break;
                    case '04':
                        echo "   → HTTP ROUTING ERROR: URL 경로 오류\n";
                        break;
                    case '03':
                        echo "   → HTTP_ERROR: HTTP 프로토콜 오류\n";
                        break;
                    case '01':
                        echo "   → SERVICE_KEY_IS_NOT_REGISTERED_ERROR: 서비스키 미등록\n";
                        break;
                    case '02':
                        echo "   → REQUEST_MESSAGE_PARSING_ERROR: 요청 메시지 파싱 오류\n";
                        break;
                }
            }
        } else {
            echo "❓ 예상과 다른 응답 구조\n";
            echo "키: " . implode(', ', array_keys($data)) . "\n";
        }
        
        echo "\n원본 응답 (처음 500자):\n" . substr($response1, 0, 500) . "\n";
        
    } else {
        echo "❌ XML 파싱 실패\n";
        echo "응답 내용: " . substr($response1, 0, 300) . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// 2. 추가 파라미터 테스트 (일반적인 공고 검색용)
echo "2. 추가 파라미터 테스트\n";
$testUrl2 = $exactUri . '?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 10,
    'inqryBgnDt' => date('Ymd', strtotime('-7 days')), // 7일 전
    'inqryEndDt' => date('Ymd'), // 오늘
    'type' => 'xml'
]);

echo "URL: $testUrl2\n";

$response2 = @file_get_contents($testUrl2, false, $context);

if ($response2 === false) {
    echo "❌ 요청 실패\n";
} else {
    echo "✅ 응답 수신 (" . strlen($response2) . " bytes)\n";
    
    $xml2 = @simplexml_load_string($response2);
    if ($xml2) {
        $data2 = json_decode(json_encode($xml2), true);
        
        if (isset($data2['cmmMsgHeader'])) {
            $code2 = $data2['cmmMsgHeader']['returnReasonCode'] ?? '??';
            $msg2 = $data2['cmmMsgHeader']['returnAuthMsg'] ?? '??';
            
            if ($code2 === '00') {
                echo "🎉 성공! 파라미터 추가 호출 완료\n";
                
                if (isset($data2['body'])) {
                    $body2 = $data2['body'];
                    if (isset($body2['items'])) {
                        $itemCount = is_array($body2['items']) ? count($body2['items']) : 1;
                        echo "공고 개수: $itemCount개\n";
                        
                        // 첫 번째 공고 정보 표시
                        if (is_array($body2['items']) && count($body2['items']) > 0) {
                            $firstItem = is_array($body2['items'][0]) ? $body2['items'][0] : $body2['items'];
                            echo "첫 번째 공고:\n";
                            foreach (['bidNtceNm', 'ntceKndNm', 'bidNtceNo', 'ntceDt'] as $field) {
                                if (isset($firstItem[$field])) {
                                    echo "  $field: {$firstItem[$field]}\n";
                                }
                            }
                        }
                    }
                }
                
            } else {
                echo "❌ API 오류: $code2 - $msg2\n";
            }
        }
        
        echo "원본 응답 (처음 500자):\n" . substr($response2, 0, 500) . "\n";
    }
}

echo "\n=== 테스트 완료 ===\n";
// [END nara:exact_uri_test]
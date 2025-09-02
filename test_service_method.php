<?php

// [BEGIN nara:service_method_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';

// 용역 조회 메서드 (올바른 메서드)
$serviceMethod = 'getBidPblancListInfoServcPPSSrch';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';

echo "=== 용역 조회 메서드 테스트 ===\n";
echo "메서드: $serviceMethod (용역 조회)\n";
echo "URL: $baseUrl\n\n";

// 1. 기본 테스트
echo "1. 기본 용역 조회 테스트\n";
$testUrl1 = $baseUrl . '/' . $serviceMethod . '?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 5
]);

echo "URL: $testUrl1\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0'
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
                echo "🎉 성공! 용역 조회 API 호출 완료\n";
                
                if (isset($data['body'])) {
                    echo "📄 용역 공고 데이터 있음!\n";
                    $body = $data['body'];
                    
                    if (isset($body['totalCount'])) {
                        echo "총 용역 공고 수: {$body['totalCount']}개\n";
                    }
                    
                    if (isset($body['items'])) {
                        $items = $body['items'];
                        if (is_array($items)) {
                            echo "조회된 용역 공고: " . count($items) . "개\n";
                            
                            // 첫 번째 용역 공고 정보
                            if (count($items) > 0) {
                                $first = $items[0];
                                echo "첫 번째 용역 공고:\n";
                                foreach (['bidNtceNm', 'bidNtceNo', 'ntceDt', 'ntceKndNm', 'demndOrgNm'] as $field) {
                                    if (isset($first[$field])) {
                                        echo "  $field: {$first[$field]}\n";
                                    }
                                }
                            }
                        } else {
                            echo "단일 용역 공고 조회\n";
                        }
                    }
                    
                    echo "\n전체 응답 JSON:\n";
                    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                    
                } else {
                    echo "⚠️ 성공했지만 body가 없음\n";
                    echo "전체 응답:\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
                
            } else {
                echo "❌ API 오류: $code - $msg\n";
                
                // 상세 오류 분석
                if ($code === '04') {
                    echo "   → HTTP ROUTING ERROR: URL 경로 문제\n";
                } elseif ($code === '07') {
                    echo "   → 입력범위값 초과: 파라미터 조정 필요\n";
                } elseif ($code === '12') {
                    echo "   → NO_OPENAPI_SERVICE_ERROR: 서비스 없음\n";
                } elseif ($code === '01') {
                    echo "   → 서비스키 오류\n";
                }
            }
        }
        
        echo "원본 XML 응답:\n" . $response1 . "\n";
        
    } else {
        echo "❌ XML 파싱 실패\n";
        echo "응답 내용:\n$response1\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// 2. 날짜 포함 테스트
echo "2. 날짜 조건 포함 용역 조회\n";
$testUrl2 = $baseUrl . '/' . $serviceMethod . '?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 10,
    'inqryBgnDt' => date('Ymd', strtotime('-14 days')),
    'inqryEndDt' => date('Ymd')
]);

echo "URL: $testUrl2\n";

$response2 = @file_get_contents($testUrl2, false, $context);

if ($response2 !== false) {
    echo "✅ 응답 수신 (" . strlen($response2) . " bytes)\n";
    
    $xml2 = @simplexml_load_string($response2);
    if ($xml2) {
        $data2 = json_decode(json_encode($xml2), true);
        
        if (isset($data2['cmmMsgHeader'])) {
            $code2 = $data2['cmmMsgHeader']['returnReasonCode'] ?? '??';
            
            if ($code2 === '00') {
                echo "🎉 날짜 조건 포함 성공!\n";
                
                if (isset($data2['body']['totalCount'])) {
                    echo "14일간 용역 공고 총 개수: {$data2['body']['totalCount']}개\n";
                }
                
            } else {
                echo "❌ 오류: $code2 - {$data2['cmmMsgHeader']['returnAuthMsg']}\n";
            }
        }
    }
}

echo "\n=== 용역 조회 테스트 완료 ===\n";
// [END nara:service_method_test]
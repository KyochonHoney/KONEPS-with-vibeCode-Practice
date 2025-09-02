<?php

// [BEGIN nara:parameter_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$uri = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService/getBidPblancListInfoCnstwkPPSSrch';

echo "=== 파라미터 조정 테스트 ===\n";
echo "URI: $uri\n\n";

// 테스트할 파라미터 조합들
$parameterSets = [
    '최소_파라미터' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10
    ],
    
    '기본_날짜' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10,
        'inqryBgnDt' => date('Ymd', strtotime('-30 days')),
        'inqryEndDt' => date('Ymd')
    ],
    
    '작은_페이지' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5
    ],
    
    '매우_작은_페이지' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 1
    ],
    
    '7일_전부터' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-7 days')),
        'inqryEndDt' => date('Ymd')
    ],
    
    '하루만' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-1 day')),
        'inqryEndDt' => date('Ymd', strtotime('-1 day'))
    ],
    
    '타입_추가' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'type' => 'xml'
    ],
    
    '지역_추가' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'area' => '11'  // 서울
    ]
];

foreach ($parameterSets as $testName => $params) {
    echo "🧪 테스트: $testName\n";
    echo "파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    
    $testUrl = $uri . '?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'user_agent' => 'Mozilla/5.0'
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
                    echo "🎉 성공! API 호출 완료\n";
                    
                    if (isset($data['body'])) {
                        $body = $data['body'];
                        echo "📄 데이터 있음!\n";
                        
                        // totalCount 확인
                        if (isset($body['totalCount'])) {
                            echo "총 개수: {$body['totalCount']}개\n";
                        }
                        
                        // items 확인
                        if (isset($body['items'])) {
                            $items = $body['items'];
                            if (is_array($items)) {
                                echo "공고 수: " . count($items) . "개\n";
                                
                                // 첫 번째 공고 정보 표시
                                if (count($items) > 0) {
                                    $first = $items[0];
                                    echo "첫 번째 공고:\n";
                                    foreach (['bidNtceNm', 'bidNtceNo', 'ntceDt', 'ntceKndNm'] as $field) {
                                        if (isset($first[$field])) {
                                            echo "  $field: {$first[$field]}\n";
                                        }
                                    }
                                }
                            } else {
                                echo "단일 공고:\n";
                                foreach (['bidNtceNm', 'bidNtceNo', 'ntceDt'] as $field) {
                                    if (isset($items[$field])) {
                                        echo "  $field: {$items[$field]}\n";
                                    }
                                }
                            }
                        }
                        
                        echo "✨ 성공한 파라미터 조합입니다!\n";
                        break; // 성공하면 테스트 중단
                        
                    } else {
                        echo "⚠️ 성공했지만 body 없음\n";
                    }
                    
                } else {
                    echo "❌ 오류: $code - $msg\n";
                    
                    // 오류 해석
                    switch ($code) {
                        case '07':
                            echo "   → 입력범위값 초과 (파라미터 조정 필요)\n";
                            break;
                        case '01':
                            echo "   → 서비스키 오류\n";
                            break;
                        case '02':
                            echo "   → 요청 메시지 파싱 오류\n";
                            break;
                        case '03':
                            echo "   → HTTP 오류\n";
                            break;
                        case '04':
                            echo "   → HTTP 라우팅 오류\n";
                            break;
                        case '12':
                            echo "   → 서비스 없음\n";
                            break;
                    }
                }
            } else {
                echo "❓ 예상과 다른 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "❌ XML 파싱 실패\n";
            echo "응답: " . substr($response, 0, 200) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 성공하면 중단
    if (isset($data) && isset($data['cmmMsgHeader']) && $data['cmmMsgHeader']['returnReasonCode'] === '00') {
        echo "🏁 성공한 조합을 찾았습니다!\n";
        break;
    }
}

echo "=== 파라미터 테스트 완료 ===\n";
// [END nara:parameter_test]
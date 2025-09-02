<?php

// [BEGIN nara:simple_params_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== 단순 파라미터 테스트 ===\n";
echo "용역 조회 API: $baseUrl/$method\n\n";

// 다양한 단순 파라미터 조합 테스트
$simpleTests = [
    '최소한_파라미터' => [
        'serviceKey' => $serviceKey
    ],
    
    '페이징_추가' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5
    ],
    
    '용역_분류만' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryDiv' => '11'  // 용역
    ],
    
    '최근_7일' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-7 days')),
        'inqryEndDt' => date('Ymd')
    ],
    
    '어제_하루' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10,
        'inqryBgnDt' => date('Ymd', strtotime('-1 day')),
        'inqryEndDt' => date('Ymd', strtotime('-1 day'))
    ],
    
    '용역_+ 날짜' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10,
        'inqryDiv' => '11',
        'inqryBgnDt' => date('Ymd', strtotime('-14 days')),
        'inqryEndDt' => date('Ymd')
    ]
];

foreach ($simpleTests as $testName => $params) {
    echo "🔍 테스트: $testName\n";
    echo "파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    
    $testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
    echo "URL: $testUrl\n";
    
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
                        echo "📄 용역 공고 데이터 수신!\n";
                        
                        if (isset($body['totalCount'])) {
                            echo "총 용역 공고: {$body['totalCount']}개\n";
                        }
                        
                        if (isset($body['items'])) {
                            $items = $body['items'];
                            
                            if (is_array($items) && isset($items[0])) {
                                echo "조회된 공고: " . count($items) . "개\n";
                                
                                // 첫 번째 공고 정보
                                $first = $items[0];
                                echo "첫 번째 용역 공고:\n";
                                foreach (['bidNtceNm', 'bidNtceNo', 'ntceDt', 'ntceKndNm', 'demndOrgNm'] as $field) {
                                    if (isset($first[$field])) {
                                        echo "  $field: {$first[$field]}\n";
                                    }
                                }
                            } else {
                                echo "단일 공고 또는 빈 결과\n";
                                if (isset($items['bidNtceNm'])) {
                                    echo "공고명: {$items['bidNtceNm']}\n";
                                }
                            }
                        }
                        
                        echo "\n✨ 성공한 파라미터 조합입니다!\n";
                        
                        // 전체 응답 표시 (성공시만)
                        echo "전체 JSON 응답:\n";
                        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        
                        break; // 성공하면 테스트 중단
                        
                    } else {
                        echo "⚠️ 성공했지만 body가 없음\n";
                    }
                    
                } else {
                    echo "❌ API 오류: $code - $msg\n";
                    
                    // 오류별 해석
                    switch ($code) {
                        case '07':
                            echo "   → 입력범위값 초과 (파라미터 조정 필요)\n";
                            break;
                        case '04':
                            echo "   → HTTP 라우팅 오류 (URL 문제)\n";
                            break;
                        case '01':
                            echo "   → 서비스키 오류\n";
                            break;
                        case '12':
                            echo "   → 서비스 없음\n";
                            break;
                    }
                    
                    // 응답 내용 표시
                    if (strlen($response) < 400) {
                        echo "응답 내용:\n$response\n";
                    }
                }
            } else {
                echo "❓ 예상과 다른 응답 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
            }
        } else {
            echo "❌ XML 파싱 실패\n";
            echo "응답: " . substr($response, 0, 200) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 성공시 중단
    if (isset($data) && isset($data['cmmMsgHeader']) && $data['cmmMsgHeader']['returnReasonCode'] === '00') {
        echo "🏁 성공한 조합을 찾았습니다!\n";
        break;
    }
}

echo "=== 단순 파라미터 테스트 완료 ===\n";
// [END nara:simple_params_test]
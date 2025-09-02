<?php

// [BEGIN nara:required_params_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== 필수 파라미터 조합 테스트 ===\n";
echo "가설: 특정 파라미터 조합이 필수일 수 있음\n\n";

// 공공데이터포털 API에서 흔히 요구되는 파라미터 조합들
$requiredParamTests = [
    '날짜_필수_가설' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'inqryBgnDt' => date('Ymd', strtotime('-1 day')),
        'inqryEndDt' => date('Ymd', strtotime('-1 day'))
    ],
    
    '페이징_필수_가설' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 1
    ],
    
    '날짜+페이징_조합' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 1,
        'inqryBgnDt' => date('Ymd'),
        'inqryEndDt' => date('Ymd')
    ],
    
    '모든_기본값' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 10,
        'type' => 'xml',
        'inqryBgnDt' => date('Ymd'),
        'inqryEndDt' => date('Ymd')
    ]
];

// 실제 데이터가 있는 날짜로 테스트 (과거 데이터)
$pastDates = [
    '1개월_전' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-30 days')),
        'inqryEndDt' => date('Ymd', strtotime('-25 days'))
    ],
    
    '2주_전' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 3,
        'inqryBgnDt' => date('Ymd', strtotime('-14 days')),
        'inqryEndDt' => date('Ymd', strtotime('-10 days'))
    ]
];

// 모든 테스트 조합
$allTests = array_merge($requiredParamTests, $pastDates);

foreach ($allTests as $testName => $params) {
    echo "🧪 테스트: $testName\n";
    echo "파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    
    $testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
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
            
            if (isset($data['header']['resultCode'])) {
                $code = $data['header']['resultCode'];
                $msg = $data['header']['resultMsg'];
                
                echo "용역 API 응답: $code - $msg\n";
                
                if ($code === '00') {
                    echo "🎉 성공! 드디어 올바른 응답!\n";
                    
                    if (isset($data['body'])) {
                        $body = $data['body'];
                        
                        if (isset($body['totalCount'])) {
                            echo "총 용역 공고: {$body['totalCount']}개\n";
                        }
                        
                        if (isset($body['items'])) {
                            echo "공고 항목 존재\n";
                            $items = $body['items'];
                            
                            if (is_array($items) && count($items) > 0) {
                                echo "공고 수: " . count($items) . "개\n";
                                $first = $items[0];
                                echo "첫 번째 공고: " . ($first['bidNtceNm'] ?? 'N/A') . "\n";
                            }
                        }
                        
                        echo "\n✅ 성공 파라미터 조합!\n";
                        echo json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        
                        echo "\n📋 성공 응답 전체:\n";
                        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        
                        echo "\n🏆 성공! 이 조합을 사용하세요.\n";
                        break; // 성공하면 중단
                    }
                    
                } elseif ($code === '07') {
                    echo "❌ 여전히 입력범위값 초과\n";
                } else {
                    echo "❌ 기타 오류: $code\n";
                }
                
            } elseif (isset($data['cmmMsgHeader'])) {
                $code = $data['cmmMsgHeader']['returnReasonCode'];
                echo "기본 API 응답: $code\n";
                
                if ($code === '04') {
                    echo "❌ HTTP 라우팅 오류 (잘못된 조합)\n";
                }
            }
            
            // 실패시에도 원본 응답 표시 (짧게)
            if ($code !== '00') {
                echo "응답: " . substr($response, 0, 200) . "\n";
            }
            
        } else {
            echo "❌ XML 파싱 실패\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 테스트 간 간격
    sleep(1);
}

echo "=== 필수 파라미터 테스트 완료 ===\n";
// [END nara:required_params_test]
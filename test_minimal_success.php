<?php

// [BEGIN nara:minimal_success_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== 최소 성공 조합 탐색 ===\n";
echo "목표: 입력범위값 초과 오류 해결하여 성공 응답 획득\n\n";

// 매우 단순한 파라미터부터 점진적 추가
$progressiveTests = [
    '기본만' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11'
    ],
    
    '페이지_추가' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1
    ],
    
    '행수_최소' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 1
    ],
    
    '행수_작음' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 5
    ],
    
    '행수_보통' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 10
    ],
    
    '최근_3일' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-3 days')),
        'inqryEndDt' => date('Ymd')
    ],
    
    '어제만' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 10,
        'inqryBgnDt' => date('Ymd', strtotime('-1 day')),
        'inqryEndDt' => date('Ymd', strtotime('-1 day'))
    ],
    
    '타입_명시' => [
        'serviceKey' => $serviceKey,
        'inqryDiv' => '11',
        'pageNo' => 1,
        'numOfRows' => 5,
        'type' => 'xml'
    ]
];

$successfulParams = null;

foreach ($progressiveTests as $testName => $params) {
    echo "🧪 테스트: $testName\n";
    
    $testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
    echo "파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (compatible; NaraBot/1.0)'
        ]
    ]);
    
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response === false) {
        echo "❌ 요청 실패 (네트워크 오류)\n";
    } else {
        echo "✅ 응답 수신 (" . strlen($response) . " bytes)\n";
        
        $xml = @simplexml_load_string($response);
        if ($xml) {
            $data = json_decode(json_encode($xml), true);
            
            // 용역 API 응답 구조 확인
            if (isset($data['header']['resultCode'])) {
                $resultCode = $data['header']['resultCode'];
                $resultMsg = $data['header']['resultMsg'] ?? 'Unknown';
                
                echo "응답 코드: $resultCode - $resultMsg\n";
                
                if ($resultCode === '00') {
                    echo "🎉 성공! 용역 공고 데이터 획득!\n";
                    
                    // 성공 응답 분석
                    if (isset($data['body'])) {
                        $body = $data['body'];
                        echo "📄 body 데이터 존재\n";
                        
                        if (isset($body['totalCount'])) {
                            echo "총 용역 공고: {$body['totalCount']}개\n";
                        }
                        
                        if (isset($body['items'])) {
                            $items = $body['items'];
                            echo "조회된 공고 항목 존재\n";
                            
                            if (is_array($items) && count($items) > 0) {
                                echo "공고 목록: " . count($items) . "개\n";
                                
                                $first = $items[0];
                                echo "첫 번째 용역 공고:\n";
                                foreach (['bidNtceNm', 'bidNtceNo', 'ntceDt', 'demndOrgNm'] as $field) {
                                    if (isset($first[$field])) {
                                        echo "  $field: {$first[$field]}\n";
                                    }
                                }
                            } elseif (isset($items['bidNtceNm'])) {
                                echo "단일 공고: {$items['bidNtceNm']}\n";
                            }
                        }
                        
                        $successfulParams = $params;
                        echo "\n✨ 성공한 최소 파라미터 조합을 찾았습니다!\n";
                        echo "성공 조합: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
                        
                        // 전체 성공 응답 표시
                        echo "\n📋 전체 성공 응답:\n";
                        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        
                        break; // 성공하면 테스트 중단
                        
                    } else {
                        echo "⚠️ 성공했지만 body 없음\n";
                    }
                    
                } else {
                    echo "❌ 오류 응답: $resultCode - $resultMsg\n";
                    
                    // 오류 유형별 분석
                    if ($resultCode === '07') {
                        echo "   → 여전히 입력범위값 초과, 더 단순한 파라미터 필요\n";
                    }
                }
                
            } else {
                echo "❓ 예상과 다른 응답 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
                echo "응답 내용: " . substr($response, 0, 300) . "\n";
            }
        } else {
            echo "❌ XML 파싱 실패\n";
            echo "응답: " . substr($response, 0, 200) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 성공시 중단
    if ($successfulParams) {
        break;
    }
    
    // 각 테스트 사이 잠시 대기 (API 부하 방지)
    sleep(1);
}

if ($successfulParams) {
    echo "🏆 최종 성공 파라미터:\n";
    echo json_encode($successfulParams, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\n이 파라미터 조합을 NaraApiService.php에 적용하세요.\n";
} else {
    echo "😞 모든 테스트 실패. API 설정 재검토 필요.\n";
}

echo "\n=== 최소 성공 조합 탐색 완료 ===\n";
// [END nara:minimal_success_test]
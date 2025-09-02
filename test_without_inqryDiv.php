<?php

// [BEGIN nara:without_inqryDiv_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== inqryDiv 파라미터 없이 테스트 ===\n";
echo "가설: inqryDiv=11이 입력범위값 초과 오류의 원인\n\n";

$testsWithoutInqryDiv = [
    '기본_최소' => [
        'serviceKey' => $serviceKey
    ],
    
    '페이징만' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10
    ],
    
    '최근_하루' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryBgnDt' => date('Ymd', strtotime('-1 day')),
        'inqryEndDt' => date('Ymd', strtotime('-1 day'))
    ],
    
    '오늘만' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 10,
        'inqryBgnDt' => date('Ymd'),
        'inqryEndDt' => date('Ymd')
    ]
];

foreach ($testsWithoutInqryDiv as $testName => $params) {
    echo "🧪 테스트: $testName\n";
    echo "파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
    
    $testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
    
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
            
            // 두 가지 응답 구조 모두 체크
            if (isset($data['cmmMsgHeader'])) {
                $code = $data['cmmMsgHeader']['returnReasonCode'];
                $msg = $data['cmmMsgHeader']['returnAuthMsg'];
                
                echo "응답 구조: cmmMsgHeader\n";
                echo "코드: $code - $msg\n";
                
                if ($code === '00') {
                    echo "🎉 성공! 기본 구조로 성공 응답 획득!\n";
                    
                    if (isset($data['body'])) {
                        $body = $data['body'];
                        echo "📄 body 데이터 있음\n";
                        
                        if (isset($body['totalCount'])) {
                            echo "총 공고: {$body['totalCount']}개\n";
                        }
                        
                        if (isset($body['items'])) {
                            echo "공고 항목 데이터 존재\n";
                        }
                        
                        echo "\n✨ 성공 파라미터: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
                        
                        // 전체 응답 표시
                        echo "\n전체 응답:\n";
                        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        
                        break; // 성공하면 중단
                    }
                }
                
            } elseif (isset($data['header'])) {
                $code = $data['header']['resultCode'];
                $msg = $data['header']['resultMsg'];
                
                echo "응답 구조: header\n";
                echo "코드: $code - $msg\n";
                
                if ($code === '00') {
                    echo "🎉 성공! 용역 구조로 성공 응답!\n";
                    break;
                }
                
            } else {
                echo "❓ 알 수 없는 구조\n";
                echo "키: " . implode(', ', array_keys($data)) . "\n";
            }
            
            echo "원본 응답: " . substr($response, 0, 300) . "\n";
            
        } else {
            echo "❌ XML 파싱 실패\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== inqryDiv 없는 테스트 완료 ===\n";
// [END nara:without_inqryDiv_test]
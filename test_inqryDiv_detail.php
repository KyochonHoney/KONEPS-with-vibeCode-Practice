<?php

// [BEGIN nara:inqryDiv_detail_test]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== inqryDiv 파라미터 상세 테스트 ===\n";

// inqryDiv=11이 포함된 경우의 응답을 자세히 분석
$params = [
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 5,
    'inqryDiv' => '11'  // 용역
];

$testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
echo "테스트 URL: $testUrl\n\n";

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
    echo "원본 XML 응답:\n";
    echo $response . "\n\n";
    
    $xml = @simplexml_load_string($response);
    if ($xml) {
        $data = json_decode(json_encode($xml), true);
        
        echo "JSON 변환 결과:\n";
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
        
        // 다양한 구조 체크
        if (isset($data['cmmMsgHeader'])) {
            echo "🔍 cmmMsgHeader 구조 발견\n";
            $header = $data['cmmMsgHeader'];
            $code = $header['returnReasonCode'] ?? '??';
            $msg = $header['returnAuthMsg'] ?? '??';
            echo "코드: $code, 메시지: $msg\n";
            
        } elseif (isset($data['header'])) {
            echo "🔍 header 구조 발견\n";
            $header = $data['header'];
            print_r($header);
            
            // 일반적인 공공데이터 포털 응답 구조 체크
            if (isset($header['resultCode'])) {
                $resultCode = $header['resultCode'];
                $resultMsg = $header['resultMsg'] ?? 'Unknown';
                
                echo "resultCode: $resultCode\n";
                echo "resultMsg: $resultMsg\n";
                
                if ($resultCode === '00') {
                    echo "🎉 성공! 정상 응답\n";
                    
                    if (isset($data['body'])) {
                        echo "📄 body 데이터 있음!\n";
                        $body = $data['body'];
                        print_r($body);
                        
                        if (isset($body['totalCount'])) {
                            echo "총 용역 공고 개수: {$body['totalCount']}\n";
                        }
                        
                        if (isset($body['items'])) {
                            $items = $body['items'];
                            if (is_array($items)) {
                                echo "조회된 용역 공고: " . count($items) . "개\n";
                            } else {
                                echo "단일 용역 공고 또는 다른 구조\n";
                            }
                        }
                    }
                } else {
                    echo "❌ 오류: $resultCode - $resultMsg\n";
                    
                    // 오류 코드 해석
                    if ($resultCode === '07') {
                        echo "   → 입력범위값 초과 오류\n";
                    }
                }
            }
            
        } else {
            echo "❓ 알 수 없는 응답 구조\n";
            echo "최상위 키들: " . implode(', ', array_keys($data)) . "\n";
            print_r($data);
        }
    } else {
        echo "❌ XML 파싱 실패\n";
        echo "응답 내용:\n$response\n";
    }
}

echo "\n=== 상세 테스트 완료 ===\n";
// [END nara:inqryDiv_detail_test]
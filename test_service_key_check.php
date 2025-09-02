<?php

// [BEGIN nara:service_key_check]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';

echo "=== 서비스키 및 API 권한 체크 ===\n";
echo "서비스키 길이: " . strlen($serviceKey) . " 문자\n";
echo "서비스키 첫 8자: " . substr($serviceKey, 0, 8) . "...\n\n";

// 1. 공사 API로 테스트 (비교용)
echo "1. 공사 API 테스트 (비교 목적)\n";
$constructionMethod = 'getBidPblancListInfoCnstwkPPSSrch';

$testUrl1 = $baseUrl . '/' . $constructionMethod . '?' . http_build_query([
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 5
]);

echo "공사 API URL: $testUrl1\n";

$response1 = @file_get_contents($testUrl1, false, stream_context_create([
    'http' => ['timeout' => 12, 'user_agent' => 'Mozilla/5.0']
]));

if ($response1) {
    echo "✅ 공사 API 응답 수신 (" . strlen($response1) . " bytes)\n";
    
    $xml1 = @simplexml_load_string($response1);
    if ($xml1) {
        $data1 = json_decode(json_encode($xml1), true);
        
        if (isset($data1['cmmMsgHeader'])) {
            $code1 = $data1['cmmMsgHeader']['returnReasonCode'];
            $msg1 = $data1['cmmMsgHeader']['returnAuthMsg'];
            echo "공사 API 결과: $code1 - $msg1\n";
            
            if ($code1 === '01') {
                echo "❌ 서비스키 오류 - API 키 문제!\n";
            } elseif ($code1 === '00') {
                echo "✅ 공사 API는 정상 작동 - 서비스키 유효함\n";
            }
        }
    }
} else {
    echo "❌ 공사 API 요청 실패\n";
}

echo "\n" . str_repeat("-", 50) . "\n\n";

// 2. 용역 API - 다른 파라미터 조합 시도
echo "2. 용역 API - 대안 파라미터 테스트\n";
$serviceMethod = 'getBidPblancListInfoServcPPSSrch';

$alternativeTests = [
    'inqryDiv_없음' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5
    ],
    
    'inqryDiv_문자열_10' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryDiv' => '10'  // 10: 공사? 다른 값 시도
    ],
    
    'inqryDiv_문자열_01' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryDiv' => '01'
    ],
    
    'inqryDiv_숫자_11' => [
        'serviceKey' => $serviceKey,
        'pageNo' => 1,
        'numOfRows' => 5,
        'inqryDiv' => 11  // 숫자로 시도
    ]
];

foreach ($alternativeTests as $testName => $params) {
    echo "🔍 $testName 테스트\n";
    
    $testUrl = $baseUrl . '/' . $serviceMethod . '?' . http_build_query($params);
    $response = @file_get_contents($testUrl, false, stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0']
    ]));
    
    if ($response) {
        echo "✅ 응답 수신 (" . strlen($response) . " bytes)\n";
        
        $xml = @simplexml_load_string($response);
        if ($xml) {
            $data = json_decode(json_encode($xml), true);
            
            // 응답 구조별 분석
            if (isset($data['cmmMsgHeader'])) {
                $code = $data['cmmMsgHeader']['returnReasonCode'];
                $msg = $data['cmmMsgHeader']['returnAuthMsg'];
                echo "기본 구조: $code - $msg\n";
                
                if ($code === '00') {
                    echo "🎉 성공! 이 조합 사용 가능\n";
                    
                    if (isset($data['body']['totalCount'])) {
                        echo "총 공고 수: {$data['body']['totalCount']}개\n";
                    }
                    
                    echo "\n✅ 성공 조합: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
                    echo "성공 응답:\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                    break;
                }
                
            } elseif (isset($data['header'])) {
                $code = $data['header']['resultCode'];
                $msg = $data['header']['resultMsg'];
                echo "용역 구조: $code - $msg\n";
                
                if ($code === '00') {
                    echo "🎉 용역 API 성공!\n";
                    break;
                }
            }
            
        } else {
            echo "❌ XML 파싱 실패\n";
        }
    } else {
        echo "❌ 요청 실패\n";
    }
    
    echo "\n";
}

echo "\n=== 서비스키 체크 완료 ===\n";

// 3. API 스펙 확인을 위한 에러 메시지 분석
echo "\n3. 에러 메시지 상세 분석\n";
echo "일관된 '입력범위값 초과 에러'는 다음을 의미할 수 있습니다:\n";
echo "- 날짜 범위 제한 (예: 최대 30일)\n";
echo "- numOfRows 제한 (예: 최대 1000)\n";
echo "- 필수 파라미터 누락\n";
echo "- 서비스키 권한 부족 (특정 API 접근 불가)\n";
echo "- API 스펙 변경 (문서와 실제 구현 차이)\n";
// [END nara:service_key_check]
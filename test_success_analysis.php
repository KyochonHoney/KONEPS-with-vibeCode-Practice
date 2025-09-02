<?php

// [BEGIN nara:success_analysis]
$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

echo "=== 성공 응답 상세 분석 ===\n";
echo "발견: inqryDiv=01이 성공적으로 작동함\n\n";

// 성공한 파라미터 조합
$successParams = [
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 5,
    'inqryDiv' => '01'  // 핵심: 01이 성공키!
];

echo "✅ 성공 파라미터 조합:\n";
echo json_encode($successParams, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

$testUrl = $baseUrl . '/' . $method . '?' . http_build_query($successParams);
echo "성공 URL: $testUrl\n\n";

$response = file_get_contents($testUrl, false, stream_context_create([
    'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0']
]));

if ($response) {
    echo "✅ 응답 수신 (" . strlen($response) . " bytes)\n";
    
    $xml = simplexml_load_string($response);
    if ($xml) {
        $data = json_decode(json_encode($xml), true);
        
        if (isset($data['header']['resultCode']) && $data['header']['resultCode'] === '00') {
            echo "🎉 성공 응답 확인!\n\n";
            
            // 응답 구조 분석
            echo "📋 응답 구조 분석:\n";
            echo "- 최상위 키: " . implode(', ', array_keys($data)) . "\n";
            
            if (isset($data['body'])) {
                $body = $data['body'];
                echo "- body 구조: " . implode(', ', array_keys($body)) . "\n";
                
                if (isset($body['totalCount'])) {
                    echo "- 총 공고 수: {$body['totalCount']}개\n";
                }
                
                if (isset($body['items'])) {
                    $items = $body['items'];
                    
                    if (is_array($items) && count($items) > 0) {
                        echo "- 조회된 공고: " . count($items) . "개\n";
                        
                        $firstItem = $items[0];
                        echo "\n🔍 첫 번째 공고 정보:\n";
                        
                        $importantFields = [
                            'bidNtceNo' => '공고번호',
                            'bidNtceNm' => '공고명', 
                            'ntceDt' => '공고일자',
                            'ntceKndNm' => '공고종류',
                            'demndOrgNm' => '수요기관',
                            'cntrctCnclsMthdNm' => '계약체결방법',
                            'rcptBgnDt' => '접수시작일시',
                            'rcptEndDt' => '접수종료일시',
                            'opengDt' => '개찰일시',
                            'presmptPrce' => '추정가격'
                        ];
                        
                        foreach ($importantFields as $field => $korName) {
                            if (isset($firstItem[$field])) {
                                echo "  {$korName}: {$firstItem[$field]}\n";
                            }
                        }
                        
                        echo "\n📊 첫 번째 공고 전체 필드:\n";
                        foreach ($firstItem as $key => $value) {
                            if (is_string($value) && strlen($value) < 100) {
                                echo "  $key: $value\n";
                            }
                        }
                        
                    } else {
                        echo "- 공고 목록 형태가 다름\n";
                    }
                }
            }
            
            echo "\n✨ inqryDiv 값별 의미 추정:\n";
            echo "- inqryDiv=01: 성공 (모든 공고? 또는 특정 카테고리)\n";
            echo "- inqryDiv=11: 입력범위값 초과 (용역 전용이지만 제약이 있을 수 있음)\n";
            echo "- inqryDiv 없음: HTTP 라우팅 오류\n";
            
            echo "\n📝 결론:\n";
            echo "1. getBidPblancListInfoServcPPSSrch 메서드 정상 작동\n";
            echo "2. inqryDiv=01 파라미터로 데이터 수집 가능\n";
            echo "3. 용역 공고도 포함되어 있을 가능성 높음\n";
            echo "4. NaraApiService.php를 inqryDiv=01로 수정 필요\n";
            
        } else {
            echo "❌ 예상과 다른 응답\n";
        }
        
    } else {
        echo "❌ XML 파싱 실패\n";
    }
} else {
    echo "❌ 요청 실패\n";
}

echo "\n=== 성공 분석 완료 ===\n";
// [END nara:success_analysis]
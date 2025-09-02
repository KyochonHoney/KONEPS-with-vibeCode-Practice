<?php

echo "=== 실제 API 응답 구조 확인 ===\n";

$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

$params = [
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 3,
    'inqryDiv' => '01',
    'inqryBgnDt' => '20240801',
    'inqryEndDt' => '20240801'
];

$testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
echo "API URL: $testUrl\n\n";

$response = file_get_contents($testUrl, false, stream_context_create([
    'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0']
]));

if ($response) {
    echo "✅ API 응답 수신 (" . strlen($response) . " bytes)\n\n";
    
    $xml = simplexml_load_string($response);
    if ($xml) {
        $data = json_decode(json_encode($xml), true);
        
        echo "📋 최상위 구조:\n";
        echo "- 최상위 키들: " . implode(', ', array_keys($data)) . "\n\n";
        
        if (isset($data['body']['items'])) {
            $items = $data['body']['items'];
            
            echo "📦 Items 분석:\n";
            echo "- totalCount: " . ($data['body']['totalCount'] ?? 'N/A') . "\n";
            echo "- Items 타입: " . gettype($items) . "\n";
            
            // Items 구조 확인
            if (is_array($items)) {
                // 키들 확인
                $itemKeys = array_keys($items);
                echo "- Items 키들: " . implode(', ', $itemKeys) . "\n";
                
                // 첫 번째 요소 확인
                $firstKey = $itemKeys[0] ?? null;
                if ($firstKey !== null) {
                    $firstItem = $items[$firstKey];
                    
                    if (is_array($firstItem)) {
                        echo "- 첫 번째 항목 키들: " . implode(', ', array_keys($firstItem)) . "\n";
                        
                        echo "\n🔍 첫 번째 공고 데이터:\n";
                        $fields = ['bidNtceNo', 'bidNtceNm', 'dminsttNm', 'ntceInsttNm', 'bidNtceDt'];
                        foreach ($fields as $field) {
                            echo "- {$field}: " . ($firstItem[$field] ?? '[없음]') . "\n";
                        }
                        
                        echo "\n📝 모든 필드 (처음 10개):\n";
                        $count = 0;
                        foreach ($firstItem as $key => $value) {
                            if ($count++ >= 10) break;
                            $valueStr = is_string($value) ? mb_substr($value, 0, 30) : (string)$value;
                            echo "  {$key}: {$valueStr}\n";
                        }
                        
                    } else {
                        echo "- 첫 번째 항목이 배열이 아님: " . gettype($firstItem) . "\n";
                    }
                }
                
            } else {
                echo "- Items가 배열이 아님\n";
            }
            
        } else {
            echo "❌ body/items 구조 없음\n";
        }
        
        // 전체 구조를 JSON으로 출력 (처음 1000자만)
        echo "\n📄 전체 JSON 구조 (첫 1000자):\n";
        $jsonStr = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo substr($jsonStr, 0, 1000) . "...\n";
        
    } else {
        echo "❌ XML 파싱 실패\n";
    }
} else {
    echo "❌ API 요청 실패\n";
}

echo "\n=== 분석 완료 ===\n";
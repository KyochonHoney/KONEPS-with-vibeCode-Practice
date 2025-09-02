<?php

echo "=== 나라장터 API 전체 필드 분석 ===\n";

$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

$params = [
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 3,
    'inqryDiv' => '01',
    'inqryBgnDt' => '20250825',  // 더 넓은 범위
    'inqryEndDt' => '20250901'
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
        
        echo "📋 전체 응답 구조:\n";
        echo "- header.resultCode: " . ($data['header']['resultCode'] ?? 'N/A') . "\n";
        echo "- body.totalCount: " . ($data['body']['totalCount'] ?? 'N/A') . "\n\n";
        
        if (isset($data['body']['items']['item'])) {
            $items = $data['body']['items']['item'];
            
            if (!empty($items)) {
                $firstItem = $items[0];
                
                echo "🔍 첫 번째 공고의 모든 필드 (총 " . count($firstItem) . "개):\n\n";
                
                $fieldTypes = [];
                $samples = [];
                
                foreach ($firstItem as $field => $value) {
                    $type = gettype($value);
                    $fieldTypes[$field] = $type;
                    
                    if (is_array($value)) {
                        $samples[$field] = '[빈 배열]';
                    } else {
                        $valueStr = (string)$value;
                        $samples[$field] = mb_strlen($valueStr) > 80 ? mb_substr($valueStr, 0, 80) . '...' : $valueStr;
                    }
                }
                
                // 필드 분류
                echo "📝 모든 필드 목록:\n";
                $count = 1;
                foreach ($fieldTypes as $field => $type) {
                    $sample = $samples[$field];
                    printf("%2d. %-25s : %-8s -> %s\n", $count++, $field, $type, $sample);
                }
                
                echo "\n📊 필드 타입 통계:\n";
                $typeCount = array_count_values($fieldTypes);
                foreach ($typeCount as $type => $count) {
                    echo "- $type: {$count}개\n";
                }
                
                echo "\n🗃️ 데이터베이스 컬럼 제안:\n";
                echo "다음 필드들을 tenders 테이블에 추가 권장:\n";
                
                $dbSuggestions = [];
                foreach ($fieldTypes as $field => $type) {
                    if ($type === 'string' && !empty($samples[$field])) {
                        $length = mb_strlen($samples[$field]);
                        if ($length > 80) {
                            $dbSuggestions[$field] = 'TEXT';
                        } elseif ($length > 20) {
                            $dbSuggestions[$field] = 'VARCHAR(255)';
                        } else {
                            $dbSuggestions[$field] = 'VARCHAR(100)';
                        }
                    } elseif ($type === 'array') {
                        $dbSuggestions[$field] = 'TEXT (JSON)';
                    }
                }
                
                foreach ($dbSuggestions as $field => $dbType) {
                    echo "- {$field}: {$dbType}\n";
                }
                
                echo "\n🔑 주요 필드 샘플:\n";
                $importantFields = [
                    'bidNtceNo', 'bidNtceNm', 'dminsttNm', 'ntceInsttNm', 
                    'presmptPrce', 'bidNtceDt', 'bidNtceEndDt', 'opengDt',
                    'cntrctCnclsMthdNm', 'ntceKindNm', 'bidMethdNm'
                ];
                
                foreach ($importantFields as $field) {
                    if (isset($samples[$field])) {
                        echo "- {$field}: {$samples[$field]}\n";
                    }
                }
                
            } else {
                echo "❌ 공고 데이터가 없음\n";
            }
        } else {
            echo "❌ items 구조 없음\n";
        }
        
    } else {
        echo "❌ XML 파싱 실패\n";
    }
} else {
    echo "❌ API 요청 실패\n";
}

echo "\n=== 분석 완료 ===\n";
<?php

echo "=== API 필드 타입 분석 ===\n";

$serviceKey = '3d18152cba55dc1ae0d4b82c0b965225de24e5fc4c97629bbadf4f7a75de6749';
$baseUrl = 'http://apis.data.go.kr/1230000/ad/BidPublicInfoService';
$method = 'getBidPblancListInfoServcPPSSrch';

$params = [
    'serviceKey' => $serviceKey,
    'pageNo' => 1,
    'numOfRows' => 1,  // 1개만 분석
    'inqryDiv' => '01',
    'inqryBgnDt' => date('Ymd', strtotime('-7 days')),
    'inqryEndDt' => date('Ymd')
];

$testUrl = $baseUrl . '/' . $method . '?' . http_build_query($params);
$response = file_get_contents($testUrl);

if ($response) {
    $xml = simplexml_load_string($response);
    $data = json_decode(json_encode($xml), true);
    
    $firstItem = $data['body']['items']['item'][0] ?? null;
    
    if ($firstItem) {
        echo "🔍 첫 번째 공고의 모든 필드 타입 분석:\n\n";
        
        $problemFields = ['presmptPrce', 'bidNtceDt', 'bidNtceEndDt', 'inqryDiv'];
        
        foreach ($firstItem as $field => $value) {
            $type = gettype($value);
            $sample = '';
            
            if (is_array($value)) {
                $sample = '[배열: ' . count($value) . '개 요소] ' . print_r($value, true);
            } else if (is_string($value)) {
                $sample = mb_substr($value, 0, 50) . (mb_strlen($value) > 50 ? '...' : '');
            } else {
                $sample = (string)$value;
            }
            
            $marker = in_array($field, $problemFields) ? '⚠️ ' : '';
            echo "{$marker}{$field}: {$type} -> {$sample}\n";
        }
        
        echo "\n📋 특별히 확인할 필드들:\n";
        foreach ($problemFields as $field) {
            if (isset($firstItem[$field])) {
                echo "- {$field}: " . gettype($firstItem[$field]) . " -> ";
                if (is_array($firstItem[$field])) {
                    echo json_encode($firstItem[$field], JSON_UNESCAPED_UNICODE);
                } else {
                    echo $firstItem[$field];
                }
                echo "\n";
            } else {
                echo "- {$field}: [없음]\n";
            }
        }
        
    } else {
        echo "❌ 데이터 항목을 찾을 수 없음\n";
    }
} else {
    echo "❌ API 요청 실패\n";
}

echo "\n=== 분석 완료 ===\n";
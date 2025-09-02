<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\NaraApiService;

echo "=== 실제 API 데이터 확인 ===\n";

$naraService = new NaraApiService();

try {
    $response = $naraService->getBidPblancListInfoServcPPSSrch([
        'numOfRows' => 1,
        'pageNo' => 1,
        'inqryBgnDt' => '20240801',
        'inqryEndDt' => '20240801'
    ]);
    
    if (isset($response['body']['items']['item'][0])) {
        $firstItem = $response['body']['items']['item'][0];
        
        echo "📋 실제 API 응답 데이터:\n";
        echo "- bidNtceNo: " . print_r($firstItem['bidNtceNo'], true) . "\n";
        echo "- bidNtceOrd: " . print_r($firstItem['bidNtceOrd'], true) . "\n";
        echo "- reNtceYn: " . print_r($firstItem['reNtceYn'], true) . "\n";
        echo "- rgstTyNm: " . print_r($firstItem['rgstTyNm'], true) . "\n";
        echo "- bidMethdNm: " . print_r($firstItem['bidMethdNm'], true) . "\n";
        
        echo "\n🔍 문제 필드들:\n";
        $problemFields = ['bidNtceOrd', 'reNtceYn', 'rgstTyNm', 'bidMethdNm'];
        foreach ($problemFields as $field) {
            $value = $firstItem[$field] ?? 'NOT_SET';
            $type = gettype($value);
            echo "- {$field}: {$type} -> ";
            if (is_array($value)) {
                echo "배열(" . count($value) . "개): " . json_encode($value);
            } else {
                echo $value;
            }
            echo "\n";
        }
        
    } else {
        echo "❌ 데이터 없음\n";
    }
    
} catch (Exception $e) {
    echo "❌ 오류: " . $e->getMessage() . "\n";
}

echo "\n=== 확인 완료 ===\n";
<?php

require_once 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NaraApiService;

echo "=== API 응답 데이터 구조 확인 ===\n";

try {
    $naraService = new NaraApiService();
    
    // 최근 데이터 가져오기
    $response = $naraService->getBidPblancListInfoServcPPSSrch([
        'numOfRows' => 3,
        'pageNo' => 1,
        'inqryBgnDt' => date('Ymd', strtotime('-7 days')),
        'inqryEndDt' => date('Ymd')
    ]);
    
    echo "✅ API 호출 성공\n";
    echo "응답 최상위 키들: " . implode(', ', array_keys($response)) . "\n\n";
    
    if (isset($response['header'])) {
        echo "📋 Header 정보:\n";
        print_r($response['header']);
        echo "\n";
    }
    
    if (isset($response['body'])) {
        $body = $response['body'];
        echo "📄 Body 정보:\n";
        echo "- totalCount: " . ($body['totalCount'] ?? 'N/A') . "\n";
        echo "- numOfRows: " . ($body['numOfRows'] ?? 'N/A') . "\n"; 
        echo "- pageNo: " . ($body['pageNo'] ?? 'N/A') . "\n";
        echo "- Body 키들: " . implode(', ', array_keys($body)) . "\n\n";
        
        if (isset($body['items'])) {
            $items = $body['items'];
            echo "📦 Items 구조 분석:\n";
            echo "- Items 타입: " . gettype($items) . "\n";
            
            if (is_array($items)) {
                if (isset($items[0]) && is_array($items[0])) {
                    // 배열의 배열 구조
                    echo "- Items 개수: " . count($items) . "\n";
                    echo "- 첫 번째 Item 키들: " . implode(', ', array_keys($items[0])) . "\n";
                    
                    echo "\n🔍 첫 번째 공고 상세:\n";
                    $firstItem = $items[0];
                    $importantFields = ['bidNtceNo', 'bidNtceNm', 'dminsttNm', 'ntceInsttNm', 'bidNtceDt', 'presmptPrce'];
                    foreach ($importantFields as $field) {
                        echo "- {$field}: " . ($firstItem[$field] ?? '[없음]') . "\n";
                    }
                    
                    echo "\n📝 전체 필드 목록 (첫 번째 공고):\n";
                    foreach ($firstItem as $key => $value) {
                        $valueStr = is_string($value) ? mb_substr($value, 0, 50) : (string)$value;
                        echo "  {$key}: {$valueStr}\n";
                    }
                    
                } else {
                    // 단일 객체 구조일 가능성
                    echo "- Items가 단일 객체일 수 있음\n";
                    echo "- Items 키들: " . implode(', ', array_keys($items)) . "\n";
                }
            } else {
                echo "- Items가 배열이 아님: " . gettype($items) . "\n";
            }
        } else {
            echo "❌ body에 items 없음\n";
        }
    } else {
        echo "❌ 응답에 body 없음\n";
    }
    
} catch (Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
}

echo "\n=== 디버깅 완료 ===\n";
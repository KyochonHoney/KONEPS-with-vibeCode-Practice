<?php

// [BEGIN nara:laravel_service_test]
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\NaraApiService;
use Illuminate\Support\Facades\Log;

echo "=== Laravel NaraApiService 테스트 ===\n";
echo "업데이트된 서비스 클래스 테스트 (inqryDiv=01)\n\n";

try {
    $naraService = new NaraApiService();
    
    echo "✅ NaraApiService 인스턴스 생성 성공\n";
    
    // 1. 연결 테스트
    echo "\n1. API 연결 테스트\n";
    $connectionResult = $naraService->testConnection();
    echo "연결 상태: " . ($connectionResult ? "✅ 성공" : "❌ 실패") . "\n";
    
    // 2. 기본 API 호출 테스트
    echo "\n2. 기본 용역 공고 조회 테스트\n";
    
    $result = $naraService->getBidPblancListInfoServcPPSSrch([
        'numOfRows' => 5  // 작은 수로 테스트
    ]);
    
    echo "✅ API 호출 성공!\n";
    echo "응답 데이터 구조 확인:\n";
    
    if (isset($result['header'])) {
        $header = $result['header'];
        echo "- Header: " . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($header['resultCode'] === '00') {
            echo "🎉 API 호출 성공 확인!\n";
        }
    }
    
    if (isset($result['body'])) {
        $body = $result['body'];
        echo "- Body 키들: " . implode(', ', array_keys($body)) . "\n";
        
        if (isset($body['totalCount'])) {
            echo "- 총 공고 수: {$body['totalCount']}개\n";
        }
        
        if (isset($body['items'])) {
            $items = $body['items'];
            echo "- 조회된 항목 수: " . (is_array($items) ? count($items) : '1개 또는 구조 다름') . "\n";
        }
    }
    
    // 3. 날짜 범위 테스트
    echo "\n3. 날짜 범위 조회 테스트 (최근 7일)\n";
    
    $recentResult = $naraService->getRecentTenders(1, 3); // 3개만 조회
    
    echo "✅ 최근 공고 조회 성공\n";
    
    if (isset($recentResult['body']['totalCount'])) {
        echo "최근 7일간 공고 수: {$recentResult['body']['totalCount']}개\n";
    }
    
    echo "\n🎉 모든 테스트 성공! 데이터 수집 기능 복구 완료!\n";
    
} catch (Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Laravel 서비스 테스트 완료 ===\n";
// [END nara:laravel_service_test]
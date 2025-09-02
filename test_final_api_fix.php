<?php

// [BEGIN nara:final_api_test]
require_once __DIR__ . '/bootstrap/app.php';

use App\Services\NaraApiService;

echo "=== 최종 API 수정 테스트 ===\n";
echo "실행 시간: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $naraApiService = new NaraApiService();
    
    // 1. 기본 API 테스트 (파라미터 없음)
    echo "1. 기본 API 호출 테스트\n";
    echo "메서드: getBidPblancListInfoServcPPSSrch\n";
    echo "URL: https://apis.data.go.kr/1230000/BidPublicInfoService\n";
    
    try {
        $result = $naraApiService->getTodayTenders(1, 5);
        echo "✅ API 호출 성공!\n";
        echo "응답 구조: " . implode(', ', array_keys($result)) . "\n";
        
        if (isset($result['body'])) {
            echo "📄 데이터 있음\n";
            $body = $result['body'];
            if (isset($body['items'])) {
                $itemCount = is_array($body['items']) ? count($body['items']) : 1;
                echo "공고 개수: $itemCount\n";
            }
        }
        
        echo "전체 응답 (JSON):\n";
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        
    } catch (Exception $e) {
        echo "❌ API 호출 실패: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 2. 최근 7일 테스트
    echo "2. 최근 7일 공고 테스트\n";
    try {
        $recent = $naraApiService->getRecentTenders(1, 3);
        echo "✅ 최근 7일 API 호출 성공!\n";
        echo "응답 구조: " . implode(', ', array_keys($recent)) . "\n";
        
    } catch (Exception $e) {
        echo "❌ 최근 7일 API 호출 실패: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 3. 연결 테스트
    echo "3. 연결 테스트\n";
    $connectionTest = $naraApiService->testConnection();
    echo "연결 상태: " . ($connectionTest ? "✅ 성공" : "❌ 실패") . "\n";
    
} catch (Exception $e) {
    echo "❌ 서비스 초기화 실패: " . $e->getMessage() . "\n";
}

echo "\n=== 테스트 완료 ===\n";
// [END nara:final_api_test]
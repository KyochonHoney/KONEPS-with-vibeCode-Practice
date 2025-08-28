<?php

/**
 * 나라장터 API 연동 스모크 테스트
 */

// [BEGIN nara:api_smoke_test]
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 나라장터 API 연동 기능 테스트 ===\n\n";

use App\Services\NaraApiService;
use App\Services\TenderCollectorService;
use Illuminate\Support\Facades\Log;

try {
    // 1. NaraApiService 인스턴스 테스트
    echo "1. NaraApiService 인스턴스 생성 테스트...\n";
    $naraApi = app(NaraApiService::class);
    if ($naraApi instanceof NaraApiService) {
        echo "   ✅ NaraApiService 인스턴스 생성 성공\n";
    } else {
        echo "   ❌ NaraApiService 인스턴스 생성 실패\n";
    }
    
    // 2. 환경 설정 확인
    echo "2. 환경 설정 확인...\n";
    $apiKey = config('services.nara.api_key');
    $timeout = config('services.nara.timeout');
    
    if (!empty($apiKey)) {
        echo "   ✅ API 키 설정됨 (길이: " . strlen($apiKey) . "자)\n";
    } else {
        echo "   ❌ API 키가 설정되지 않음\n";
    }
    
    if ($timeout > 0) {
        echo "   ✅ 타임아웃 설정됨 ({$timeout}초)\n";
    } else {
        echo "   ❌ 타임아웃 설정 오류\n";
    }
    
    // 3. API 연결 테스트
    echo "3. API 연결 테스트...\n";
    try {
        $connectionTest = $naraApi->testConnection();
        if ($connectionTest) {
            echo "   ✅ API 연결 성공\n";
        } else {
            echo "   ❌ API 연결 실패 (인증 오류 또는 서비스 장애)\n";
        }
    } catch (Exception $e) {
        echo "   ❌ API 연결 테스트 오류: " . $e->getMessage() . "\n";
    }
    
    // 4. 데이터 조회 테스트 (소량)
    echo "4. 데이터 조회 테스트...\n";
    try {
        $testParams = [
            'pageNo' => 1,
            'numOfRows' => 5,
            'inqryDiv' => '11' // 용역
        ];
        
        $response = $naraApi->getBidPblancListInfoServc($testParams);
        
        if (isset($response['response']['header']['resultCode'])) {
            $resultCode = $response['response']['header']['resultCode'];
            $resultMsg = $response['response']['header']['resultMsg'] ?? 'Unknown';
            
            if ($resultCode === '00') {
                $totalCount = $response['response']['body']['totalCount'] ?? 0;
                $items = $response['response']['body']['items'] ?? [];
                echo "   ✅ 데이터 조회 성공 (전체: {$totalCount}건, 조회: " . count($items) . "건)\n";
                
                if (!empty($items)) {
                    $firstItem = $items[0];
                    $bidNtceNo = $firstItem['bidNtceNo'] ?? 'N/A';
                    $bidNtceNm = mb_substr($firstItem['bidNtceNm'] ?? 'N/A', 0, 30);
                    echo "   📋 첫 번째 공고: [{$bidNtceNo}] {$bidNtceNm}...\n";
                }
            } else {
                echo "   ❌ API 오류: [{$resultCode}] {$resultMsg}\n";
            }
        } else {
            echo "   ❌ API 응답 형식 오류\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ 데이터 조회 오류: " . $e->getMessage() . "\n";
    }
    
    // 5. TenderCollectorService 테스트
    echo "5. TenderCollectorService 인스턴스 생성 테스트...\n";
    try {
        $collector = app(TenderCollectorService::class);
        if ($collector instanceof TenderCollectorService) {
            echo "   ✅ TenderCollectorService 인스턴스 생성 성공\n";
        } else {
            echo "   ❌ TenderCollectorService 인스턴스 생성 실패\n";
        }
    } catch (Exception $e) {
        echo "   ❌ TenderCollectorService 생성 오류: " . $e->getMessage() . "\n";
    }
    
    // 6. 데이터베이스 연결 확인
    echo "6. 데이터베이스 연결 확인...\n";
    try {
        $tenderCount = \App\Models\Tender::count();
        echo "   ✅ 데이터베이스 연결 성공 (기존 공고: {$tenderCount}건)\n";
    } catch (Exception $e) {
        echo "   ❌ 데이터베이스 연결 오류: " . $e->getMessage() . "\n";
    }
    
    // 7. Artisan 명령어 등록 확인
    echo "7. Artisan 명령어 등록 확인...\n";
    try {
        $commands = \Artisan::all();
        if (isset($commands['tender:collect'])) {
            echo "   ✅ tender:collect 명령어 등록됨\n";
        } else {
            echo "   ❌ tender:collect 명령어 등록되지 않음\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Artisan 명령어 확인 오류: " . $e->getMessage() . "\n";
    }
    
    // 8. 라우트 등록 확인
    echo "8. 관리자 라우트 등록 확인...\n";
    try {
        $routes = collect(\Route::getRoutes())->filter(function($route) {
            return str_contains($route->getName() ?? '', 'admin.tenders.');
        });
        
        $routeCount = $routes->count();
        if ($routeCount > 0) {
            echo "   ✅ 관리자 입찰공고 라우트 등록됨 ({$routeCount}개)\n";
        } else {
            echo "   ❌ 관리자 입찰공고 라우트 등록되지 않음\n";
        }
    } catch (Exception $e) {
        echo "   ❌ 라우트 확인 오류: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 테스트 완료 ===\n";
    echo "🔗 관리자 입찰공고 관리: https://nara.tideflo.work/admin/tenders\n";
    echo "📊 데이터 수집 페이지: https://nara.tideflo.work/admin/tenders/collect\n";
    echo "🧪 API 테스트: https://nara.tideflo.work/admin/tenders/test-api\n";
    echo "⚡ Artisan 명령어: php artisan tender:collect --help\n\n";
    
} catch (Exception $e) {
    echo "❌ 테스트 중 치명적 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}
// [END nara:api_smoke_test]
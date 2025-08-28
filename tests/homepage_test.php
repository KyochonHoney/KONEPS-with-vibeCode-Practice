<?php

/**
 * 홈페이지 기능 스모크 테스트
 */

// [BEGIN nara:homepage_test]
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 홈페이지 기능 테스트 ===\n\n";

try {
    // 1. 메인 페이지 접근 테스트
    echo "1. 메인 페이지 접근 테스트...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://nara.tideflo.work");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ 메인 페이지 접근 성공 (HTTP {$httpCode})\n";
    } else {
        echo "   ❌ 메인 페이지 접근 실패 (HTTP {$httpCode})\n";
    }
    
    // 2. 페이지 제목 확인
    echo "2. 페이지 제목 확인...\n";
    if (strpos($html, '<title>Nara - 나라장터 AI 제안서 시스템</title>') !== false) {
        echo "   ✅ 페이지 제목 정상\n";
    } else {
        echo "   ❌ 페이지 제목 이상\n";
    }
    
    // 3. 주요 콘텐츠 확인
    echo "3. 주요 콘텐츠 확인...\n";
    $contentChecks = [
        '나라장터 AI 제안서 시스템' => '메인 제목',
        '용역공고 수집' => '기능 1',
        'AI 분석' => '기능 2', 
        '제안서 자동생성' => '기능 3',
        '로그인' => '로그인 버튼',
        '회원가입' => '회원가입 버튼'
    ];
    
    foreach ($contentChecks as $content => $description) {
        if (strpos($html, $content) !== false) {
            echo "   ✅ {$description} 존재\n";
        } else {
            echo "   ❌ {$description} 누락\n";
        }
    }
    
    // 4. 로그인/회원가입 링크 확인
    echo "4. 네비게이션 링크 확인...\n";
    $linkChecks = [
        'href="/login"' => '로그인 링크',
        'href="/register"' => '회원가입 링크'
    ];
    
    foreach ($linkChecks as $link => $description) {
        if (strpos($html, $link) !== false) {
            echo "   ✅ {$description} 정상\n";
        } else {
            echo "   ❌ {$description} 이상\n";
        }
    }
    
    // 5. Bootstrap CSS 및 아이콘 확인
    echo "5. 스타일 및 아이콘 확인...\n";
    if (strpos($html, 'bootstrap') !== false || strpos($html, 'btn-') !== false) {
        echo "   ✅ Bootstrap 스타일 적용\n";
    } else {
        echo "   ⚠️  Bootstrap 스타일 확인 필요\n";
    }
    
    if (strpos($html, 'bi-') !== false) {
        echo "   ✅ Bootstrap 아이콘 적용\n";
    } else {
        echo "   ⚠️  Bootstrap 아이콘 확인 필요\n";
    }
    
    echo "\n=== 홈페이지 테스트 완료 ===\n";
    echo "🏠 메인 페이지: https://nara.tideflo.work\n";
    echo "🔑 로그인 페이지: https://nara.tideflo.work/login\n";
    echo "📝 회원가입 페이지: https://nara.tideflo.work/register\n\n";
    
} catch (Exception $e) {
    echo "❌ 테스트 중 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}
// [END nara:homepage_test]
<?php

/**
 * 도메인 접근성 스모크 테스트 - nara.tideflo.work
 */

// [BEGIN nara:domain_test]
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== nara.tideflo.work 도메인 접근 테스트 ===\n\n";

try {
    // 1. HTTPS 접근성 테스트
    echo "1. HTTPS 접근성 테스트...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://nara.tideflo.work");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ HTTPS 접근 성공 (HTTP {$httpCode})\n";
    } else {
        echo "   ❌ HTTPS 접근 실패 (HTTP {$httpCode})\n";
    }
    
    // 2. HTTP -> HTTPS 리다이렉션 테스트
    echo "2. HTTP -> HTTPS 리다이렉션 테스트...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://nara.tideflo.work");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 301 || $httpCode === 302) {
        echo "   ✅ HTTP -> HTTPS 리다이렉션 성공 (HTTP {$httpCode})\n";
    } else {
        echo "   ⚠️  HTTP 리다이렉션 상태: HTTP {$httpCode}\n";
    }
    
    // 3. Laravel 애플리케이션 응답 확인
    echo "3. Laravel 애플리케이션 응답 확인...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://nara.tideflo.work");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (strpos($html, '<title>Nara</title>') !== false) {
        echo "   ✅ Laravel 애플리케이션 정상 응답\n";
    } else {
        echo "   ❌ Laravel 애플리케이션 응답 이상\n";
    }
    
    // 4. 주요 페이지 접근 테스트
    echo "4. 주요 페이지 접근 테스트...\n";
    $pages = [
        '/login' => '로그인 페이지',
        '/register' => '회원가입 페이지'
    ];
    
    foreach ($pages as $path => $name) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://nara.tideflo.work" . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "   ✅ {$name} 접근 성공\n";
        } else {
            echo "   ❌ {$name} 접근 실패 (HTTP {$httpCode})\n";
        }
    }
    
    // 5. 세션 설정 확인
    echo "5. 세션 도메인 설정 확인...\n";
    $sessionDomain = env('SESSION_DOMAIN');
    if ($sessionDomain === '.tideflo.work') {
        echo "   ✅ 세션 도메인 설정 올바름: {$sessionDomain}\n";
    } else {
        echo "   ⚠️  세션 도메인 설정 확인 필요: {$sessionDomain}\n";
    }
    
    // 6. APP_URL 설정 확인
    echo "6. APP_URL 설정 확인...\n";
    $appUrl = env('APP_URL');
    if ($appUrl === 'https://nara.tideflo.work') {
        echo "   ✅ APP_URL 설정 올바름: {$appUrl}\n";
    } else {
        echo "   ⚠️  APP_URL 설정 확인 필요: {$appUrl}\n";
    }
    
    echo "\n=== 도메인 접근 테스트 완료 ===\n";
    echo "🌐 접속 URL: https://nara.tideflo.work\n";
    echo "📋 테스트 계정:\n";
    echo "   일반 사용자: test@nara.com / password123\n";
    echo "   관리자: admin@nara.com / admin123\n\n";
    
} catch (Exception $e) {
    echo "❌ 테스트 중 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}
// [END nara:domain_test]
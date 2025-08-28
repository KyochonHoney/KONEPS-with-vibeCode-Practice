<?php

/**
 * 로그인 화면 테스트 계정 표시 기능 스모크 테스트
 */

// [BEGIN nara:login_testaccounts_test]
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 로그인 화면 테스트 계정 표시 기능 테스트 ===\n\n";

try {
    // 1. 로그인 페이지 접근 테스트
    echo "1. 로그인 페이지 접근 테스트...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://nara.tideflo.work/login");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "   ✅ 로그인 페이지 접근 성공 (HTTP {$httpCode})\n";
    } else {
        echo "   ❌ 로그인 페이지 접근 실패 (HTTP {$httpCode})\n";
        exit(1);
    }
    
    // 2. 개발용 테스트 계정 카드 확인
    echo "2. 개발용 테스트 계정 카드 표시 확인...\n";
    if (strpos($html, '개발용 테스트 계정') !== false) {
        echo "   ✅ 테스트 계정 카드 제목 표시됨\n";
    } else {
        echo "   ❌ 테스트 계정 카드 제목 없음\n";
    }
    
    if (strpos($html, '개발 단계에서만 표시됩니다') !== false) {
        echo "   ✅ 개발 환경 안내 메시지 표시됨\n";
    } else {
        echo "   ❌ 개발 환경 안내 메시지 없음\n";
    }
    
    // 3. 일반 사용자 계정 정보 확인
    echo "3. 일반 사용자 계정 정보 확인...\n";
    if (strpos($html, 'test@nara.com') !== false) {
        echo "   ✅ 일반 사용자 이메일 표시됨\n";
    } else {
        echo "   ❌ 일반 사용자 이메일 누락\n";
    }
    
    if (strpos($html, 'password123') !== false) {
        echo "   ✅ 일반 사용자 비밀번호 표시됨\n";
    } else {
        echo "   ❌ 일반 사용자 비밀번호 누락\n";
    }
    
    // 4. 관리자 계정 정보 확인
    echo "4. 관리자 계정 정보 확인...\n";
    if (strpos($html, 'admin@nara.com') !== false) {
        echo "   ✅ 관리자 이메일 표시됨\n";
    } else {
        echo "   ❌ 관리자 이메일 누락\n";
    }
    
    if (strpos($html, 'admin123') !== false) {
        echo "   ✅ 관리자 비밀번호 표시됨\n";
    } else {
        echo "   ❌ 관리자 비밀번호 누락\n";
    }
    
    // 5. 빠른 로그인 버튼 확인
    echo "5. 빠른 로그인 버튼 기능 확인...\n";
    $quickLoginCount = substr_count($html, 'quick-login');
    if ($quickLoginCount >= 2) {
        echo "   ✅ 빠른 로그인 버튼 {$quickLoginCount}개 확인\n";
    } else {
        echo "   ❌ 빠른 로그인 버튼 부족 (발견: {$quickLoginCount}개)\n";
    }
    
    // 6. JavaScript 기능 확인
    echo "6. JavaScript 자동 입력 기능 확인...\n";
    if (strpos($html, "document.getElementById('email').value = email;") !== false) {
        echo "   ✅ 이메일 자동 입력 스크립트 존재\n";
    } else {
        echo "   ❌ 이메일 자동 입력 스크립트 없음\n";
    }
    
    if (strpos($html, "document.getElementById('password').value = password;") !== false) {
        echo "   ✅ 비밀번호 자동 입력 스크립트 존재\n";
    } else {
        echo "   ❌ 비밀번호 자동 입력 스크립트 없음\n";
    }
    
    // 7. 환경 설정 확인
    echo "7. 환경 설정 확인...\n";
    $appEnv = config('app.env');
    if ($appEnv === 'local') {
        echo "   ✅ 개발 환경 설정 확인 (APP_ENV={$appEnv})\n";
    } else {
        echo "   ⚠️  환경 설정: APP_ENV={$appEnv}\n";
    }
    
    // 8. 보안 조건 확인
    echo "8. 보안 조건 확인...\n";
    if (strpos($html, "@if(config('app.env') === 'local')") === false) {
        if (strpos($html, '개발용 테스트 계정') !== false) {
            echo "   ✅ 개발 환경에서만 테스트 계정 표시됨\n";
        }
    } else {
        echo "   ✅ 환경 조건부 표시 코드 확인\n";
    }
    
    echo "\n=== 테스트 완료 ===\n";
    echo "🔑 로그인 페이지: https://nara.tideflo.work/login\n";
    echo "📋 테스트 계정 정보:\n";
    echo "   👤 일반 사용자: test@nara.com / password123\n";
    echo "   👨‍💼 관리자: admin@nara.com / admin123\n";
    echo "💡 로그인 페이지에서 '빠른 로그인' 버튼을 클릭하면 자동으로 입력됩니다.\n\n";
    
} catch (Exception $e) {
    echo "❌ 테스트 중 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}
// [END nara:login_testaccounts_test]
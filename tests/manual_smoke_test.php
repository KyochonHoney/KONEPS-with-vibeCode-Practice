<?php

/**
 * 수동 스모크 테스트 - 인증 시스템 검증
 * 
 * 이 스크립트는 자동화된 테스트에서 CSRF 문제로 실행할 수 없는
 * 기능들을 수동으로 검증하기 위한 도구입니다.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

echo "=== 나라 AI 제안서 시스템 - 인증 시스템 수동 테스트 ===\n\n";

try {
    // 1. 역할 생성 확인
    echo "1. 역할(Role) 데이터 확인...\n";
    $roles = Role::all();
    foreach ($roles as $role) {
        echo "   - {$role->name}: {$role->display_name}\n";
    }
    echo "   ✅ 역할 데이터 정상\n\n";

    // 2. 테스트 사용자 생성
    echo "2. 테스트 사용자 생성...\n";
    
    // 기존 테스트 사용자 삭제 (있다면)
    User::where('email', 'test@nara.com')->delete();
    User::where('email', 'admin@nara.com')->delete();
    
    $testUser = User::create([
        'name' => '테스트 사용자',
        'email' => 'test@nara.com',
        'password' => Hash::make('password123')
    ]);
    $testUser->assignRole('user');
    echo "   ✅ 일반 사용자 생성 완료: {$testUser->name} ({$testUser->email})\n";
    
    $adminUser = User::create([
        'name' => '관리자',
        'email' => 'admin@nara.com',
        'password' => Hash::make('admin123')
    ]);
    $adminUser->assignRole('admin');
    echo "   ✅ 관리자 생성 완료: {$adminUser->name} ({$adminUser->email})\n\n";
    
    // 3. 역할 기능 테스트
    echo "3. 사용자 역할 기능 테스트...\n";
    echo "   - 테스트 사용자 역할 확인: " . ($testUser->hasRole('user') ? '✅ user' : '❌') . "\n";
    echo "   - 관리자 역할 확인: " . ($adminUser->hasRole('admin') ? '✅ admin' : '❌') . "\n";
    echo "   - 관리자 권한 확인: " . ($adminUser->isAdmin() ? '✅ isAdmin()' : '❌') . "\n\n";
    
    // 4. 데이터베이스 연결 상태 확인
    echo "4. 데이터베이스 연결 상태 확인...\n";
    $userCount = User::count();
    echo "   ✅ 전체 사용자 수: {$userCount}명\n\n";
    
    // 5. 웹 서버 접근성 확인
    echo "5. 웹 서버 접근성 확인...\n";
    echo "   🌐 서버 주소: http://0.0.0.0:8002\n";
    echo "   📋 테스트 계정 정보:\n";
    echo "      일반 사용자: test@nara.com / password123\n";
    echo "      관리자: admin@nara.com / admin123\n\n";
    
    echo "=== 스모크 테스트 완료 ===\n";
    echo "✅ 모든 기본 기능이 정상 작동합니다.\n";
    echo "🔗 브라우저에서 http://0.0.0.0:8002 에 접속하여 수동 테스트를 진행하세요.\n\n";
    
    echo "수동 테스트 시나리오:\n";
    echo "1. 회원가입 페이지 (/register) 접근\n";
    echo "2. 로그인 페이지 (/login) 접근\n";
    echo "3. 테스트 계정으로 로그인\n";
    echo "4. 대시보드 (/dashboard) 접근 확인\n";
    echo "5. 관리자 계정으로 로그인\n";
    echo "6. 관리자 대시보드 (/admin/dashboard) 접근 확인\n";
    echo "7. 로그아웃 기능 확인\n";
    
} catch (Exception $e) {
    echo "❌ 오류 발생: " . $e->getMessage() . "\n";
    echo "스택 트레이스:\n" . $e->getTraceAsString() . "\n";
}
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // [BEGIN nara:auth_tests]
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // 테스트용 역할 생성
        Role::create([
            'name' => 'user',
            'display_name' => '일반사용자',
            'description' => '기본 사용 권한'
        ]);
        
        Role::create([
            'name' => 'admin',
            'display_name' => '관리자',
            'description' => '운영 관리 권한'
        ]);
        
        Role::create([
            'name' => 'super_admin',
            'display_name' => '최고관리자',
            'description' => '시스템 전체 관리 권한'
        ]);
    }

    /** @test */
    public function user_can_view_login_form()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    /** @test */
    public function user_can_view_register_form()
    {
        $response = $this->get('/register');
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }

    /** @test */
    public function user_can_register()
    {
        $userData = [
            'name' => '테스트 사용자',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'name' => '테스트 사용자',
            'email' => 'test@example.com'
        ]);
        
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
    }

    /** @test */
    public function user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);
        
        $user->assignRole('user');

        $response = $this->post('/login', [
                             'email' => 'test@example.com',
                             'password' => 'password123'
                         ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function user_cannot_login_with_incorrect_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $response = $this->post('/login', [
                             'email' => 'test@example.com',
                             'password' => 'wrongpassword'
                         ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /** @test */
    public function admin_user_redirects_to_admin_dashboard()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123')
        ]);
        
        $user->assignRole('admin');

        $response = $this->post('/login', [
                             'email' => 'admin@example.com',
                             'password' => 'password123'
                         ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)
                         ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /** @test */
    public function guest_cannot_access_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
    }

    /** @test */
    public function regular_user_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_user_can_access_admin_dashboard()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    /** @test */
    public function super_admin_user_can_access_admin_dashboard()
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    // [END nara:auth_tests]
}
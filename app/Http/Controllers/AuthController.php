<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
    // [BEGIN nara:auth_controller]
    
    /**
     * 로그인 폼 표시
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * 로그인 처리
     */
    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => '이메일은 필수입니다.',
            'email.email' => '올바른 이메일 형식을 입력해주세요.',
            'password.required' => '비밀번호는 필수입니다.',
            'password.min' => '비밀번호는 최소 6자 이상이어야 합니다.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            // 사용자 역할에 따른 리다이렉션
            $user = Auth::user();
            if ($user->isSuperAdmin()) {
                return redirect()->intended('/admin/dashboard');
            } elseif ($user->isAdmin()) {
                return redirect()->intended('/admin/dashboard');
            } else {
                return redirect()->intended('/dashboard');
            }
        }

        return redirect()->back()->withErrors([
            'email' => '제공된 자격 증명이 일치하지 않습니다.',
        ])->withInput($request->only('email'));
    }

    /**
     * 회원가입 폼 표시
     */
    public function showRegisterForm(): View
    {
        return view('auth.register');
    }

    /**
     * 회원가입 처리
     */
    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'name.required' => '이름은 필수입니다.',
            'email.required' => '이메일은 필수입니다.',
            'email.email' => '올바른 이메일 형식을 입력해주세요.',
            'email.unique' => '이미 사용 중인 이메일입니다.',
            'password.required' => '비밀번호는 필수입니다.',
            'password.min' => '비밀번호는 최소 6자 이상이어야 합니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->only('name', 'email'));
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 기본 역할 할당 (일반 사용자)
        $user->assignRole('user');

        Auth::login($user);

        return redirect('/dashboard')->with('success', '회원가입이 완료되었습니다.');
    }

    /**
     * 로그아웃 처리
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/')->with('success', '로그아웃되었습니다.');
    }

    /**
     * 대시보드 표시
     */
    public function dashboard(): View
    {
        $user = Auth::user();
        $stats = [
            'total_tenders' => 0, // TODO: 실제 데이터로 교체
            'total_analyses' => 0, // TODO: 실제 데이터로 교체
            'total_proposals' => 0, // TODO: 실제 데이터로 교체
        ];

        return view('dashboard', compact('user', 'stats'));
    }

    /**
     * 관리자 대시보드 표시
     */
    public function adminDashboard(): View
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            abort(403, '접근 권한이 없습니다.');
        }

        $stats = [
            'total_users' => User::count(),
            'total_tenders' => 0, // TODO: 실제 데이터로 교체
            'total_analyses' => 0, // TODO: 실제 데이터로 교체
            'total_proposals' => 0, // TODO: 실제 데이터로 교체
        ];

        return view('admin.dashboard', compact('user', 'stats'));
    }

    // [END nara:auth_controller]
}
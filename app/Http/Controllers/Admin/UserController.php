<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * 사용자 목록 표시
     */
    public function index(): View
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * 사용자 생성 폼 표시
     */
    public function create(): View
    {
        return view('admin.users.create');
    }

    /**
     * 사용자 생성 처리
     */
    public function store(Request $request): RedirectResponse
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

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', '사용자가 성공적으로 생성되었습니다.');
    }

    /**
     * 사용자 수정 폼 표시
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * 사용자 수정 처리
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed',
        ], [
            'name.required' => '이름은 필수입니다.',
            'email.required' => '이메일은 필수입니다.',
            'email.email' => '올바른 이메일 형식을 입력해주세요.',
            'email.unique' => '이미 사용 중인 이메일입니다.',
            'password.min' => '비밀번호는 최소 6자 이상이어야 합니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->only('name', 'email'));
        }

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', '사용자 정보가 수정되었습니다.');
    }

    /**
     * 사용자 삭제
     */
    public function destroy(User $user): RedirectResponse
    {
        // 자기 자신은 삭제 불가
        if ($user->id === auth()->id()) {
            return redirect()->back()
                ->with('error', '자기 자신은 삭제할 수 없습니다.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', '사용자가 삭제되었습니다.');
    }
}

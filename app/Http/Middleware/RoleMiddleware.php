<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    // [BEGIN nara:role_middleware]
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login')->with('error', '로그인이 필요합니다.');
        }

        $user = Auth::user();

        // 역할 확인
        if (!empty($roles) && !$user->hasAnyRole($roles)) {
            abort(403, '접근 권한이 없습니다.');
        }

        return $next($request);
    }
    // [END nara:role_middleware]
}
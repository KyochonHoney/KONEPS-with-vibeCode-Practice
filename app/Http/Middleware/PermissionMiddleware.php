<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    // [BEGIN nara:permission_middleware]
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            return redirect('/login')->with('error', '로그인이 필요합니다.');
        }

        $user = Auth::user();

        // 권한 확인
        if (!$user->hasPermission($permission)) {
            abort(403, '해당 기능을 사용할 권한이 없습니다.');
        }

        return $next($request);
    }
    // [END nara:permission_middleware]
}
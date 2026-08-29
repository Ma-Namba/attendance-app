<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;
        //「ゲスト向けミドルウェア（RedirectIfAuthenticated）」誤作動の修正(/admin/loginアクセスで/loginへの強制遷移修正)
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // adminガードでログイン済みの場合は管理者ダッシュボードへ
                if ($guard === 'admin') {
                    return redirect()->route('admin.attendance.index');
                }

                // それ以外（一般ユーザー）は RouteServiceProvider::HOME （通常は /）へ
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}

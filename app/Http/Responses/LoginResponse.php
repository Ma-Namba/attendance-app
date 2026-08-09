<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログイン成功後のリダイレクト先を制御
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // 管理者ガード(admin)でログインしているか判定
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.attendance.list');
        }

        // 一般ユーザーのマイページ（勤怠画面）へリダイレクト
        else return redirect()->route('attendance.create');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Requests\LoginRequest; // Fortify標準のバリデーションを利用

class AdminAuthController extends Controller
{
    /**
     * 管理者ログイン画面の表示
     */
    public function showLoginForm()
    {
        return view('admin.admin-login'); // 管理者用のログインBladeを指定
    }
}

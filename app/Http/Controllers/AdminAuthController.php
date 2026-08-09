<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Http\Requests\LoginRequest;

class AdminAuthController extends Controller
{
    /**
     * 管理者ログイン処理を実行
     */
    public function store(LoginRequest $request)
    {
        // リクエストデータのバリデーション（Fortifyのルールを適用）
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // adminガードを使って認証試行
        if (
            Auth::guard('admin')->attempt(
                $request->only('email', 'password'),
                $request->boolean('remember')
            )
        ) {
            // セッションの再生成（固定化攻撃対策）
            $request->session()->regenerate();

            // 前フェーズでバインド済みのカスタム LoginResponse を呼び出す
            return app(LoginResponse::class);
        }

        // 認証失敗時はエラーメッセージを日本語で返却
        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    /**
     * 管理者ログアウト処理
     */
    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}

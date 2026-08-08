<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
// use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        // Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // RateLimiter::for('two-factor', function (Request $request) {
        //     return Limit::perMinute(5)->by($request->session()->get('login.id'));
        // });

        // リクエストURLに応じた設定の動的切り替え
        if ($request->is('admin*') || $request->is('api/admin*')) {
            config([
                'fortify.guard' => 'admin',                     // 管理者用のガードを適用
                'fortify.home' => RouteServiceProvider::ADMIN_HOME, // 管理者用のリダイレクト先
            ]);
        } else {
            config([
                'fortify.guard' => 'web',
                'fortify.home' => RouteServiceProvider::HOME,
            ]);
        }

        // ログイン画面（一般ユーザー / 管理者）
        Fortify::loginView(function (Request $request) {
            // 配列を使うことで、'admin' と 'admin/login'（または admin/*）のみに限定できます
            if ($request->is('admin') || $request->is('admin/*')) {
                return view('admin.admin-login');
            }

            return view('user.user-login');
        });

        // 会員登録画面（一般ユーザーのみ）
        Fortify::registerView(function () {
            return view('user.register');
        });

        // 認証処理（マルチガード対応）
        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            // 管理者ログイン時の処理
            if ($request->is('admin*')) {
                $admin = Admin::where('email', $request->email)->first();
                if ($admin && Hash::check($request->password, $admin->password)) {
                    return $admin;
                }
            }
            // 一般ユーザーログイン時の処理
            else {
                $user = User::where('email', $request->email)->first();
                if ($user && Hash::check($request->password, $user->password)) {
                    return $user;
                }
            }
            return null;
        });
    }
}

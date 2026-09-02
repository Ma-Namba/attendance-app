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
use App\Http\Responses\LoginResponse; // 追加
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract; // 追加
use Illuminate\Support\Facades\Auth;

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

        // まず、URL自体が完全に管理者専用（admin/〜）であるか、
        // または「共用URL（stamp_correction_request*）であり、かつ管理者だけがログインしている状態」かを厳密に判定します。
        $isStrictAdminRoute = $request->is('admin*') || $request->is('api/admin*');
        $isSharedRouteAndAdminOnly = $request->is('stamp_correction_request*') && \Auth::guard('admin')->check() && !\Auth::guard('web')->check();

        if ($isStrictAdminRoute || $isSharedRouteAndAdminOnly) {
            config([
                'fortify.guard' => 'admin',
                'fortify.home' => RouteServiceProvider::ADMIN_HOME,
            ]);
        } else {
            // 両方ログインしている場合、または一般ユーザーのみの場合は、安全のために「一般ユーザーの世界」を適用します。
            // これにより、管理者が一般ユーザーとして打刻画面（/attendance）を操作する際、管理者のクッキーに邪魔されなくなります。
            config([
                'fortify.guard' => 'web',
                'fortify.home' => RouteServiceProvider::HOME,
            ]);
        }

        // ログイン画面の切り替え設定(一般ユーザー/管理者)
        Fortify::loginView(function (Request $request) {
            // URLに 'admin' が含まれる場合は管理者用Blade、それ以外は一般用Bladeを返す
            if ($request->is('admin*')) {
                return view('admin.admin-login'); // 既存の管理者用Bladeを指定
            }
            return view('user.user-login');
        });

        // --- 追加：ログインレスポンスのカスタムバインド ---
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);

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

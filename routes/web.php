<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAuthController;
use Laravel\Fortify\Fortify;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//
Route::middleware(['auth'])->group(function () {
    // 勤怠管理メイン画面（ログイン後のリダイレクト先）
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');

    // 今後実装する打刻用アクションのルーティング枠
    // Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    // Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    // Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])->name('attendance.break-in');
    // Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])->name('attendance.break-out');
});

// 管理者専用ルーティング（参考用）
// 管理者用のログイン関連（未ログイン時のみアクセス可能にするため guest:admin ミドルウェアを推奨）
Route::middleware(['guest:admin'])->prefix('admin')->name('admin.')->group(function () {
    // 管理者ログイン画面の表示
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');

    // 管理者ログイン処理（送信先）
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');
});
// 未ログイン時のトップページ（またはログイン画面へリダイレクト）
Route::redirect('/', '/login');

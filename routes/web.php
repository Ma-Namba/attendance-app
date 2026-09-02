<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\StaffController;
use Laravel\Fortify\Fortify;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;

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

// 管理者認証済み向けルート（ログアウトなど）
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

    // ログイン後のダッシュボード（遷移先）
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.index');

    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('attendance.show')->whereNumber('id');
    Route::get('/attendance/staff/{id}', [StaffController::class, 'show'])->name('staff.attendance.show');

    Route::patch('/attendance/{id}', [AdminAttendanceController::class, 'update'])->name('attendance.update');
    Route::get('/staff/list', [StaffController::class, 'index'])->name('staff.index');

});

// 一般ユーザー認証向けルート
Route::middleware(['auth'])->group(function () {
    // 勤怠管理メイン画面（ログイン後のリダイレクト先）
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/{id}', [AttendanceController::class, 'storeApplication'])->name('attendance.application.store')->whereNumber('id');
    // idデータがない打刻詳細に遷移した時の安全コード
    Route::get('/attendance/unrecorded/{date}', [AttendanceController::class, 'showUnrecorded'])->name('attendance.unrecorded');
    Route::get('/stamp_correction_request/list', [ApplicationController::class, 'userShowApplication'])->name('user.application.list');
    Route::get('/application/{id}', [AttendanceController::class, 'show']);
});

// 管理者ゲスト（未ログイン）向けルート
Route::middleware(['guest:admin'])->prefix('admin')->name('admin.')->group(function () {
    // ログイン画面表示（FortifyServiceProviderのloginViewが呼ばれます）
    Route::get('/login', function () {
        return view('admin.admin-login');
    })->name('login');
    // ログイン処理実行
    Route::post('/login', [AdminAuthController::class, 'store']);
});


// 未ログイン時のトップページ（またはログイン画面へリダイレクト）
Route::redirect('/', '/login');

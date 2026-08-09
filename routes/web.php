<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAuthController;
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

// 管理者ゲスト（未ログイン）向けルート
Route::middleware(['guest:admin'])->prefix('admin')->name('admin.')->group(function () {
    // ログイン画面表示（FortifyServiceProviderのloginViewが呼ばれます）
    Route::get('/login', function () {
        return view('admin.admin-login');
    })->name('login');

    // ログイン処理実行
    Route::post('/login', [AdminAuthController::class, 'store']);
});

// 管理者認証済み向けルート（ログアウトなど）
Route::middleware(['auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

    // ログイン後のダッシュボード（遷移先）
    Route::get('/admin/attendance/list', function () {
        //ビューに渡すデータの取得
        $date = Carbon::today();
        $previousDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();
        $users = User::all();
        $attendanceRecords = Attendance::all();

        return view('admin.admin-attendance-list', compact('date', 'previousDay', 'nextDay', 'users', 'attendanceRecords')); // 管理者トップ画面
    })->name('attendance.list');
});

// 未ログイン時のトップページ（またはログイン画面へリダイレクト）
Route::redirect('/', '/login');

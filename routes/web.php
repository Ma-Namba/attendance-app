<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
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

// 仮ルート
Route::get('/login', function () {
    // URLの先頭が admin/ または admin そのものである場合
    if (request()->is('admin*')) {
        return view('admin.admin-login');
    }
    return view('user.user-login');
});

Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');

Route::get('/', [AttendanceController::class, 'create'])->name('attendance.create');

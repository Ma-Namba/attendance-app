<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class StaffController extends Controller
{
    public function index()
    {
        // 1. 【テーブル分割対応】一般ユーザーテーブル（users）から全従業員を取得
        $users = User::all();

        return view('admin.staff-list', compact(
            'users'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // 1. 現在ログインしているユーザーの情報を取得
        $user = Auth::user();

        // 2. ログインユーザーの過去の勤怠データを取得
        $formattedDate = now()->isoFormat('YYYY/MM/DD (ddd)');

        // 3. 履歴データ（$attendances）を取得
        $formattedTime = now()->format('H:i');

        // 4. 履歴データ（$attendances）と一緒に、日付文字列（$formattedDate）もビューに渡す
        $attendances = $user->attendances()->orderBy('date', 'desc')->get();

        return view('user.attendance-register',compact('user', 'attendances', 'formattedDate', 'formattedTime'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

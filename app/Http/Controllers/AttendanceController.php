<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;
use Log;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance_break;

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
        $user = Auth::user();
        $status = $user->attendance_status; // 現在のステータスを取得
        $today = Carbon::today()->toDateString(); // 本日の日付 (YYYY-MM-DD)
        $now = Carbon::now();

        // 画面のボタン（action属性）の値によって処理を分岐
        switch ($request->input('action')) {

        // 1. 出勤処理
            case 'clock_in':
                if ($status !== '勤務外') {
                    return redirect()->back()->with('error', '既に出勤しているか、出勤できない状態です。');
                }

                try {
                    // [user_id, date] の複合UKによるエラーを防ぐため、一意性を担保して作成
                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $today,
                        'clock_in' => $now,
                        'new_breaks' => json_encode([]),     // 初期状態は空のJSON配列
                    ]);
                    $message = '出勤しました。';
                } catch (\Illuminate\Database\QueryException $e) {
                    // ボタン連打などで複合UK（一意性制約）に引っかかった場合
                    Log::warning("二重出勤エラーを検知: User ID {$user->id}");
                    return redirect()->back()->with('error', '既に本日分の出勤打刻が存在します。');
                }
                break;

            // 2. 退勤処理
            case 'clock_out':
                if ($status !== '出勤中') {
                    return redirect()->back()->with('error', '退勤できる状態ではありません。');
                }

                // 本日の勤怠レコードを取得して退勤時刻を更新
                $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();
                if ($attendance) {
                    $attendance->update([
                        'clock_out' => $now,
                    ]);
                }
                $message = '退勤しました。';
                break;

            // 3. 休憩開始処理
            case 'break_in':
                if ($status !== '出勤中') {
                    return redirect()->back()->with('error', '休憩に入れる状態ではありません。');
                }

                $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();
                if (!$attendance) {
                    return redirect()->back()->with('error', '出勤データが見つかりません。');
                }

                // 親JSON用に、現在の時刻文字列を完全に固定のテキストとして抽出
                // この文言がない場合、世界標準日時形式（UTC）にてデータが保存されてしまう(例：2026-08-10T10:13:03.240937Z)
                $currentTimeString = $now->toTimeString(); // 例: "19:15:00"

                DB::transaction(function () use ($attendance, $now, $currentTimeString) {
                    $attendanceBreak = $attendance->attendanceBreaks()->create([
                        'break_in' => $now,
                    ]);

                    // $attendance->new_breaks が確実に「配列」であることを保証する
                    // もし null や空文字列が入っていた場合は、強制的に空配列 [] にするため
                    $breaksArray = $attendance->new_breaks;
                    if (!is_array($breaksArray)) {
                        $breaksArray = [];
                    }

                    $breaksArray[] = [
                        'id' => $attendanceBreak->id,
                        'breakIn' => $currentTimeString,
                        'breakOut' => null,
                    ];

                    $attendance->update([
                        'new_breaks' => $breaksArray,
                    ]);
                });

                $message = '休憩に入りました。';
                break;

            // 4. 休憩終了処理
            case 'break_out':
                if ($status !== '休憩中') {
                    return redirect()->back()->with('error', '休憩から戻れる状態ではありません。');
                }

                $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();
                if (!$attendance) {
                    return redirect()->back()->with('error', '出勤データが見つかりません。');
                }

                // 【重要】こちらも同様に、現在の時刻文字列を完全に固定のテキストとして抽出
                $currentTimeString = $now->toTimeString();

                DB::transaction(function () use ($attendance, $now, $currentTimeString) {
                    $latestBreak = $attendance->attendanceBreaks()->whereNull('break_out')->latest()->first();
                    if ($latestBreak) {
                        $latestBreak->update([
                            'break_out' => $now,
                        ]);
                    }

                    $breaksArray = $attendance->new_breaks ?? [];
                    if (!empty($breaksArray)) {
                        $lastIndex = count($breaksArray) - 1;
                        $breaksArray[$lastIndex]['breakOut'] = $currentTimeString;
                    }

                    $attendance->update([
                        'new_breaks' => $breaksArray,
                    ]);
                });

                $message = '休憩から戻りました。';
                break;

            default:
                return redirect()->back()->with('error', '不正な操作が行われました。');
        }

        // リダイレクトしてメッセージを返す
        return redirect()->back()->with('success', $message);

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

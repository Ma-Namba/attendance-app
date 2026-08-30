<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\User;
use App\Enums\ApprovalStatus;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        // 1. 【テーブル分割対応】一般ユーザーテーブル（users）から全従業員を取得
        $users = User::all();

        // 2. リクエストキー「date」を取得（無ければ「今日」が自動で入る）
        $dateParam = $request->input('date', Carbon::today()->format('Y-m-d'));

        // 3. 【超厳格クレンジング】不純物や時分秒（%2000:00:00など）を強制カットして純粋な10文字にする
        if (!empty($dateParam) && strlen($dateParam) >= 10) {
            $cleanDate = substr($dateParam, 0, 10);
        } else {
            $cleanDate = Carbon::today()->format('Y-m-d');
        }

        // 4. 【【決定的なバグ修正】Carbonのメモリ共有を完全に遮断（独立インスタンス化）
        // clone を使用し、それぞれの変数が持つメモリ空間を物理的に完全に切り離します。
        // これにより、Blade側でリレーションや配列アクセスが発生しても、URL用変数が汚染されるのを100%防御します。
        $baseCarbon = Carbon::createFromFormat('Y-m-d', $cleanDate)->startOfDay();

        $previousDay = $baseCarbon->copy()->subDay()->toDateString(); // 完全な「10文字の文字列」
        $nextDay = $baseCarbon->copy()->addDay()->toDateString(); // 完全な「10文字の文字列」

        // Blade側の {{ $date->format(...) }} のメソッド呼び出し制約をクリアするためのクリーンなオブジェクト
        $date = $baseCarbon->copy();

        // 5. 【キャスト・SQLiteバグ完全撃破】
        // Bladeが要求する変数名「$attendanceRecords」として、指定日当日のデータを一括取得
        // whereDate を使用することで、モデルキャストが裏で悪さをするのを防ぎ、完全一致検索を成功させます！
        $attendanceRecords = Attendance::whereDate('date', $cleanDate)
            ->with(['attendanceBreaks']) // 休憩子テーブル（Attendance_break）を同時ロードしてN+1問題を防御
            ->get();

        // 6. 【未打刻ユーザーの非表示バグ救済ハック】
        // 提示されたBladeは「$attendanceが無い（未出勤の）ユーザーは行ごと非表示」になる構造（@if ($attendance)）です。
        // 未打刻のユーザーも一覧に漏れなく出し、詳細リンクのエラーを防ぐため、
        // レコードが存在しないユーザーに対しては、メモリ上で空のAttendanceインスタンスを擬似生成して滑り込ませます。
        foreach ($users as $user) {
            $hasRecord = $attendanceRecords->where('user_id', $user->id)->first();

            if (!$hasRecord) {
                $fallbackAttendance = new Attendance();
                // 属性（attributes）を直接配列として初期設定し、Blade側の $attendance['id'] での文字列結合バグを完全無効化
                $fallbackAttendance->setRawAttributes([
                    'id' => 0, // 未打刻の識別用ID
                    'user_id' => $user->id,
                    'date' => $cleanDate,
                    'clock_in' => null,
                    'clock_out' => null,
                    'total_break_time' => null,
                    'total_time' => null,
                ]);

                // コレクションに追加してBladeに引き渡す
                $attendanceRecords->push($fallbackAttendance);
            }
        }

        // 7. 提示されたBladeが直接アクセスしている変数名（compact）で完全に一致させてビューに注入
        return view('admin.admin-attendance-list', compact(
            'users',
            'attendanceRecords',
            'date',
            'previousDay',
            'nextDay'
        ));
    }

    public function show($id)
    {
        $attendance = Attendance::with([
            'attendanceBreaks' => function ($query) {
                // 💡 休憩開始時間（break_in）が早い順（昇順）に並び替えてから取得する
                $query->orderBy('break_in', 'asc');
            }
        ])->findOrFail($id);
        $user = User::findOrFail($attendance->user_id);

        // 最初から配列に変換してしまう！
        $recordArray = $attendance->toArray();

        // 配列のキーに対して、時間をクレンジングして入れる（エディタは怒りません）
        $recordArray['clock_in'] = $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '未打刻';
        $recordArray['clock_out'] = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '未打刻';

        // 日付データも配列に安全に追加
        $baseDate = Carbon::parse($attendance->date);
        $recordArray['year'] = $baseDate->format('Y年');
        $recordArray['date'] = $baseDate->format('m月d日');

        // 休憩データのクレンジングと統合
        $cleanBreaks = [];
        foreach ($attendance->attendanceBreaks as $break) {
            $cleanBreaks[] = [
                'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
            ];
            $recordArray['breaks'] = $cleanBreaks;

            // 変数名を合わせてBladeに渡す
            $attendanceRecord = $recordArray;

            return view('admin.admin-detail', compact(
                'attendanceRecord',
                'user'
            ));
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;

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

    public function show(Request $request, $id)
    {
        // 1. 指定されたuserを取得
        $user = User::findOrFail($id);

        // 2. リクエストキー「date」を取得
        // パラメータが存在しない場合は、本日の属する年月「Y-m」をデフォルトにする
        $dateParam = $request->input('date', Carbon::today()->format('Y-m'));

        // 3. 基準となる日時オブジェクトを生成（月末バグを防ぐため、必ず「1日」に固定）
        $cleanYearMonth = substr($dateParam, 0, 7); // "2026-07" のように先頭7文字を安全に抽出
        $date = Carbon::parse($cleanYearMonth . '-01')->startOfDay();

        // 4. 前月・次月の情報を取得（リンク生成用）
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 5. 当月の勤怠データを SQLite の LIKE 用に前方一致で取得
        // 【確定】モデルの定義名「attendanceBreaks」にミリ単位で完全一致させてEager Loading
        $attendanceRecords = Attendance::where('user_id', $user->id)
            ->where('date', 'LIKE', "{$cleanYearMonth}%")
            ->orderBy('date', 'asc')
            ->with(['attendanceBreaks'])
            ->get();

        // 6. Bladeに渡す直前で、日付と時刻のフォーマットを強制クレンジング
        $formattedAttendanceRecords = $attendanceRecords->map(function ($attendance) {

            // attendance_id を安全に抽出
            $attendanceId = null;
            if (is_object($attendance) && isset($attendance->id)) {
                $attendanceId = $attendance->id;
            } elseif (is_array($attendance) && isset($attendance['id'])) {
                $attendanceId = $attendance['id'];
            }

            return [
                'id' => $attendanceId ?? 0,

                // 日付に「00:00:00」が混入しているのを排除し、"2026-07-01" の10文字にする
                'date' => $attendance->date
                    ? Carbon::parse($attendance->date)->format('Y-m-d')
                    : '---',

                // 出勤時刻の秒をカットして "09:00" にする
                'clock_in' => $attendance->clock_in
                    ? Carbon::parse($attendance->clock_in)->format('H:i')
                    : '---',

                // 退勤時刻の秒をカットして "18:00" にする
                'clock_out' => $attendance->clock_out
                    ? Carbon::parse($attendance->clock_out)->format('H:i')
                    : '---',

                // モデルに定義済みのアクセサから文字列を取得して配列に格納
                'total_time' => $attendance->total_time,
                'total_break_time' => $attendance->total_break_time,
            ];
        });

        // 7. 既存の変数と、取得したコレクションをそのままビューに注入して返却
        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}

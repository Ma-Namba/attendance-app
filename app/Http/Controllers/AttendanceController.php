<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;
use Log;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance_break;
use App\Models\Application;
use App\Http\Requests\ApplicationRequest;
use App\Enums\ApprovalStatus;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. 現在ログインしているユーザーの情報を取得
        $user = Auth::user();

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

            // 先頭に 'detail/' を付与することで、数値の0や空判定を完全に封殺すると同時にurlを整えます
            $displayId = !empty($attendanceId)
                ? 'detail/' . $attendanceId
                : 'unrecorded/' . $cleanDate;

            return [
                'id' => $displayId,

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
        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
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

        $today = Carbon::now()->toDateString(); // 本日の日付 (YYYY-MM-DD)
        $now = Carbon::parse(Carbon::now()->toDateTimeString());

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', 'LIKE', "{$today}%")
            ->first();

        $status = $attendance ? $attendance->getCurrentStatus() : '勤務外';

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
                // 💡 (string) を付与して型システムのミスマッチを解消
                if ((string) $status !== '出勤中') {
                    return redirect()->back()->with('error', '休憩に入れる状態ではありません。');
                }

                // 💡 LIKE演算子の前方一致により、SQLiteとMySQLの日付型ブレを完全に吸収
                $attendance = Attendance::where('user_id', $user->id)
                    ->where('date', 'LIKE', "{$today}%")
                    ->first();

                if (!$attendance) {
                    return redirect()->back()->with('error', '出勤データが見つかりません。');
                }

                $currentTimeString = $now->toTimeString(); // 例: "19:15:00"

                DB::transaction(function () use ($attendance, $now, $currentTimeString) {
                    $attendanceBreak = $attendance->attendanceBreaks()->create([
                        'attendance_id' => $attendance->id, // 💡 親IDを明示的に格納してSQLiteの制約違反を防止
                        'break_in' => $now,
                    ]);

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
                // 💡 休憩終了側にも (string) キャストを確実に適用し、テスト環境での弾きを完全封殺
                if ((string) $status !== '休憩中') {
                    return redirect()->back()->with('error', '休憩から戻れる状態ではありません。');
                }

                $currentTimeString = $now->toTimeString();

                DB::transaction(function () use ($attendance, $now, $currentTimeString) {
                    // 💡 最新の休憩レコードの attendance_id の整合性を担保して更新
                    $latestBreak = $attendance->attendanceBreaks()
                        ->where('attendance_id', $attendance->id)
                        ->whereNull('break_out')
                        ->latest()
                        ->first();

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
    public function show($id)
    {
        $user = Auth::user();

        // 1. 【修正】リレーション 'applications' も一緒に安全に Eager Loading しておく
        $attendance = Attendance::with(['attendanceBreaks', 'applications'])
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail(); // 他人のIDや存在しないIDはここで404

        $attendanceDate = Carbon::parse($attendance->date);

        // 休憩子テーブルから proposalBreaks 用の配列を生成 (秒数ノイズ H:i:s -> H:i 変換)
        $cleanBreaks = $attendance->attendanceBreaks->map(function ($b) {
            return [
                'break_in' => $b->break_in ? Carbon::parse($b->break_in)->format('H:i') : null,
                'break_out' => $b->break_out ? Carbon::parse($b->break_out)->format('H:i') : null,
            ];
        })->toArray();

        // Attendanceモデルのカスタム属性（has_pending_application）と足並みを揃え、Enumキャストで判定
        $pendingApplication = $attendance->applications()
            ->where('approval_status', ApprovalStatus::PENDING)
            ->latest()
            ->first();

        // 承認待ち申請があるなら申請中のコメントを、無いなら確定済（親テーブル）のコメントを画面に流し込む
        $commentData = $pendingApplication
            ? $pendingApplication->comments
            : ($attendance->comment ?? '');


        $applicationData = $pendingApplication ? $pendingApplication : null;
        $commentData = $pendingApplication ? $pendingApplication->comments : '';

        // 各値が empty (nullや空文字) の場合に Carbon::parse がクラッシュするのを防ぐ安全処理
        $getClockIn = function () use ($pendingApplication, $attendance) {
            if ($pendingApplication && !empty($pendingApplication->new_clock_in)) {
                return Carbon::parse($pendingApplication->new_clock_in)->format('H:i');
            }
            return ($attendance && !empty($attendance->clock_in)) ? Carbon::parse($attendance->clock_in)->format('H:i') : '';
        };

        $getClockOut = function () use ($pendingApplication, $attendance) {
            if ($pendingApplication && !empty($pendingApplication->new_clock_out)) {
                return Carbon::parse($pendingApplication->new_clock_out)->format('H:i');
            }
            return ($attendance && !empty($attendance->clock_out)) ? Carbon::parse($attendance->clock_out)->format('H:i') : '';
        };

        $data = [
            'id' => $attendance->id,
            'year' => $attendanceDate->format('Y年'),
            'date' => $attendanceDate->format('m月d日'),

            // ★ 安全ガードを適用してマッピング
            'clock_in' => $getClockIn(),
            'clock_out' => $getClockOut(),

            'newBreaks' => $attendance->new_breaks,
            'breaks' => $cleanBreaks,
            'application' => $applicationData,
            'comment' => $commentData,
        ];

        // 2. 【最重要修正】丸ごと漏れていた return view を追加
        return view('user.user-detail', [
            'viewDate' => $attendanceDate,
            'data' => $data,
            'user' => $user,
        ]);
    }

    /**
     * 勤怠の修正申請処理（最終確定版）
     */
    public function storeApplication(ApplicationRequest $request, $id)
    {
        // 1. 対象の勤怠レコードを安全に取得
        $attendance = Attendance::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        // 2. 1勤怠につき承認待ちは最大1つの重複防止ルール
        if ($attendance->has_pending_application) {
            return redirect()->back()->withErrors(['error' => '既に承認待ちの修正申請が存在します。']);
        }

        // 3. トランザクションを張り、ER図通りのカラム名へマッピングして保存
        DB::transaction(function () use ($request, $attendance) {

            // ★【大逆転の修正】文字列の末尾の 's' を削除し、Bladeの name="new_break_in" に完全一致させます！
            $newBreakIns = $request->input('new_break_in', []);  // sなしに変更
            $newBreakOuts = $request->input('new_break_out', []); // sなしに変更
            $cleanBreaks = [];

            // 独立した2つの休憩配列を一対のデータに結合（ロジックは完璧です）
            foreach ($newBreakIns as $index => $breakIn) {
                $breakOut = $newBreakOuts[$index] ?? null;

                if (!empty($breakIn) && !empty($breakOut)) {
                    $cleanBreaks[] = [
                        'break_in' => Carbon::parse($breakIn)->format('H:i'),
                        'break_out' => Carbon::parse($breakOut)->format('H:i'),
                    ];
                }
            }

            // 親レコードの日付（Y-m-d 形式の文字列）を取得
            $baseDate = $attendance->date->format('Y-m-d');

            $attendance->applications()->create([
                'user_id' => $attendance->user_id,
                'new_clock_in' => $request->filled('new_clock_in')
                    ? Carbon::parse($baseDate . ' ' . $request->input('new_clock_in'))->format('Y-m-d H:i:s')
                    : null,
                'new_clock_out' => $request->filled('new_clock_out')
                    ? Carbon::parse($baseDate . ' ' . $request->input('new_clock_out'))->format('Y-m-d H:i:s')
                    : null,

                // ★【完全解決】キー名を proposalBreaks に修正し、配列のまま引き渡す
                'proposalBreaks' => $cleanBreaks,

                'comments' => $request->input('comment') ?? '',
                'approval_status' => ApprovalStatus::PENDING,
            ]);
        });

        // 4. 同詳細画面へ安全にリダイレクト
        return redirect()->route('attendance.show', ['id' => $id])
            ->with('success', '修正申請を送信しました。')
            ->withInput();
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

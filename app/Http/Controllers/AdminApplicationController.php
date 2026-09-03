<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Attendance_break;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class AdminApplicationController extends Controller
{
    /**
     * 1. 修正申請一覧画面（管理者・一般ユーザー完全共用）
     */
    public function index()
    {
        $isAdmin = Auth::guard('admin')->check();
        $isUser = Auth::guard('web')->check();

        if ($isAdmin) {
            // 【管理者用】全ユーザーの申請データを一括取得
            $applicationData = Application::with(['attendance.user'])->latest()->get();
        } elseif ($isUser) {
            // 【一般ユーザー用】今ログインしている自分の申請データだけを取得
            $currentUserId = Auth::guard('web')->id();
            $applicationData = Application::with(['attendance.user'])
                ->whereHas('attendance', function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId);
                })
                ->latest()
                ->get();
        } else {
            return redirect()->route('login');
        }

        // フロント制約に合わせたデータのクレンジング
        $applications = $applicationData->map(function ($app) {
            if (!$app->attendance) {
                return null;
            }

            $safeParseTime = function ($value, $default = '--:--') {
                if (empty($value) || is_array($value) || is_object($value)) {
                    return $default;
                }
                try {
                    return Carbon::parse($value)->format('H:i');
                } catch (\Exception $e) {
                    return $default;
                }
            };

            try {
                $attendanceDate = Carbon::parse($app->attendance->date)->format('Y年m月d日');
            } catch (\Exception $e) {
                $attendanceDate = '日付不明';
            }

            $oldClockIn = $safeParseTime($app->attendance->clock_in);
            $oldClockOut = $safeParseTime($app->attendance->clock_out);

            $oldBreaksList = [];
            $rawOldBreaks = $app->attendance->new_breaks;

            if (!empty($rawOldBreaks) && (is_array($rawOldBreaks) || is_object($rawOldBreaks))) {
                foreach ($rawOldBreaks as $b) {
                    $bIn = is_array($b) ? ($b['breakIn'] ?? null) : ($b->breakIn ?? null);
                    $bOut = is_array($b) ? ($b['breakOut'] ?? null) : ($b->breakOut ?? null);

                    if ($bIn || $bOut) {
                        $timeIn = $safeParseTime($bIn);
                        $timeOut = $safeParseTime($bOut);
                        $oldBreaksList[] = $timeIn . '〜' . $timeOut;
                    }
                }
            }

            $oldBreaksString = !empty($oldBreaksList) ? implode(', ', $oldBreaksList) : '無';

            $dateCombinedString = sprintf(
                "%s\n%s〜%s\n[休憩:%s]",
                $attendanceDate,
                $oldClockIn,
                $oldClockOut,
                $oldBreaksString
            );

            $proposalBreaksList = [];
            if (!empty($app->proposalBreaks) && (is_array($app->proposalBreaks) || is_object($app->proposalBreaks))) {
                $breaksLoop = is_array($app->proposalBreaks) && !isset($app->proposalBreaks['break_in']) && !isset($app->proposalBreaks['breakIn'])
                    ? $app->proposalBreaks
                    : [$app->proposalBreaks];

                foreach ($breaksLoop as $breakPair) {
                    $bIn = is_array($breakPair) ? ($breakPair['break_in'] ?? $breakPair['breakIn'] ?? '') : ($breakPair->break_in ?? $breakPair->breakIn ?? '');
                    $bOut = is_array($breakPair) ? ($breakPair['break_out'] ?? $breakPair['breakOut'] ?? '') : ($breakPair->break_out ?? $breakPair->breakOut ?? '');

                    if ($bIn || $bOut) {
                        $timeIn = (is_string($bIn) && strlen($bIn) <= 5) ? $bIn : $safeParseTime($bIn);
                        $timeOut = (is_string($bOut) && strlen($bOut) <= 5) ? $bOut : $safeParseTime($bOut);
                        $proposalBreaksList[] = $timeIn . '〜' . $timeOut;
                    }
                }
            }

            $proposalBreaksString = !empty($proposalBreaksList) ? implode(', ', $proposalBreaksList) : '無';

            $statusString = is_object($app->approval_status) && isset($app->approval_status->value)
                ? $app->approval_status->value
                : (string) $app->approval_status;

            // id に申請本来の主キー ($app->id) を正しく紐付け
            return (object) [
                'id' => $app->id,
                'attendance_id' => $app->attendance_id,
                'date' => $dateCombinedString,
                'application_date' => $app->created_at ? $app->created_at->toDateTimeString() : $app->attendance->date,
                'comment' => ($app->comments ?? '備考なし'), // DBのカラム「comments」をマッピング
                'approval_status' => $statusString,
                'user' => $app->attendance->user,
                'AttendanceRecord' => $app->attendance,
            ];
        })->filter();

        if (Auth::guard('admin')->check()) {
            return view('admin.admin-application-list', ['applications' => $applications]);
        } else {
            return view('user.user-application-list', ['applications' => $applications]);
        }
    }

    /**
     * 2. 修正申請の詳細画面（URL・ビュー完全共用）
     */
    public function show($id)
    {
        // 👇 【デバッグ1】ここを差し込み
        $debugApp = \App\Models\Application::find($id);
        if (!$debugApp) {
            dd('そもそも applications テーブルに id:' . $id . ' のレコードが存在しません。');
        }
        // 1. 管理者・一般ユーザーに応じた適切なリレーションでのデータ取得
        $query = Application::with(['attendance.user']);
        $data = Auth::guard('admin')->check()
            ? $query->findOrFail($id)
            : $query->whereHas('attendance', function ($q) {
                $q->where('user_id', Auth::guard('web')->id());
            })->findOrFail($id);

        // 2. Bladeの要求に100%適合するようデータをstdClassへクレンジング
        $app = new \stdClass();
        $app->id = $data->id;
        $app->comment = $data->comments ?? '備考なし';

        $app->approval_status = ($data->approval_status instanceof \App\Enums\ApprovalStatus) ? $data->approval_status->value : (string) $data->approval_status;


        // 【Blade適合】new_date を Carbon インスタンスのまま渡す（Blade側で ->format() を実行するため）
        // new_clock_in（日時）の値を優先し、なければ元の勤怠日（date）を使用
        $app->new_date = $data->new_clock_in
            ? Carbon::parse($data->new_clock_in)
            : Carbon::parse($data->attendance->date ?? Carbon::today());

        // 出退勤時刻（Bladeの {{ $application->new_clock_in }} へ H:i 形式でマッピング）
        $app->new_clock_in = $data->new_clock_in ? Carbon::parse($data->new_clock_in)->format('H:i') : '--:--';
        $app->new_clock_out = $data->new_clock_out ? Carbon::parse($data->new_clock_out)->format('H:i') : '--:--';

        // 休憩データのクレンジング（文字列/配列/オブジェクトの揺れを吸収しCollection化）
        $rawB = $data->proposalBreaks;
        if (is_string($rawB)) {
            $rawB = json_decode($rawB, true);
        }

        $app->proposalBreaks = collect($rawB ?? [])->map(function ($b) {
            $res = new \stdClass();
            // 配列・オブジェクト両方のキー対応
            $res->break_in = is_array($b) ? ($b['break_in'] ?? ($b['breakIn'] ?? null)) : ($b->break_in ?? ($b->breakIn ?? null));
            $res->break_out = is_array($b) ? ($b['break_out'] ?? ($b['breakOut'] ?? null)) : ($b->break_out ?? ($b->breakOut ?? null));
            return $res;
        })->filter(function ($b) {
            // Bladeでの \Carbon\Carbon::parse エラーを防ぐため、break_in が空のデータは除外
            return !empty($b->break_in);
        });

        // 3. Viewへの変数展開（userはリレーションから安全に取得）
        return view('admin.admin-application-detail', [
            'application' => $app,
            'user' => $data->attendance->user ?? null,
        ]);
    }


    /**
     * 3. 申請の承認処理（管理者専用アクション）
     */
    public function approve(Request $request, $id)
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $app = Application::findOrFail($id);

        // 1. Enumオブジェクトのvalue（値）を取り出して比較
        $currentStatus = isset($app->approval_status->value) ? $app->approval_status->value : $app->approval_status;
        if ($currentStatus === '承認済み') {
            return redirect('/stamp_correction_request/list');
        }

        DB::beginTransaction();
        try {
            // 2. 【解決】Enumオブジェクトを直接渡してステータスを更新
            $app->update([
                'approval_status' => \App\Enums\ApprovalStatus::APPROVED // またはEnumのケース名（例: APPROVED）
            ]);

            // 3. 元の勤怠レコードを更新
            $at = Attendance::findOrFail($app->attendance_id);
            $at->update([
                'clock_in' => $app->new_clock_in,
                'clock_out' => $app->new_clock_out,
                'comment' => $app->comments ?? $at->comment
            ]);

            // 4. 古い休憩データをクリア
            Attendance_break::where('attendance_id', $at->id)->delete();

            // 5. 【解決】休憩データを「日付 + 時刻」の日時形式に変換して保存
            // 新出勤日時（2026-09-01 09:00:00）から「日付（2026-09-01）」を抽出
            $targetDate = Carbon::parse($app->new_clock_in)->toDateString();

            $rawB = $app->proposalBreaks; // デバッグ結果よりすでに配列
            if (!empty($rawB) && is_array($rawB)) {
                foreach ($rawB as $b) {
                    $breakInTime = $b['break_in'] ?? ($b['breakIn'] ?? null);
                    $breakOutTime = $b['break_out'] ?? ($b['breakOut'] ?? null);

                    if (!empty($breakInTime)) {
                        // 「2026-09-01」 + 「12:00」 = 「2026-09-01 12:00:00」
                        $breakInDateTime = Carbon::parse($targetDate . ' ' . $breakInTime)->toDateTimeString();
                        $breakOutDateTime = !empty($breakOutTime)
                            ? Carbon::parse($targetDate . ' ' . $breakOutTime)->toDateTimeString()
                            : null;

                        Attendance_break::create([
                            'attendance_id' => $at->id,
                            'break_in' => $breakInDateTime,
                            'break_out' => $breakOutDateTime,
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect('/stamp_correction_request/list')->with('success', '承認しました');

        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('勤怠承認エラー: ' . $e->getMessage());
            return redirect()->back()->with('error', '承認処理に失敗しました: ' . $e->getMessage());
        }
    }
}

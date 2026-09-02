<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;

class AdminApplicationController extends Controller
{
    public function index()
    {
        // 💡 1. どっちの権限でログインしているかをチェック
        $isAdmin = Auth::guard('admin')->check();
        $isUser = Auth::guard('web')->check();

        // 💡 2. 権限に応じて、取得する申請データを切り替える
        if ($isAdmin) {
            // 【管理者用】全ユーザーの申請データを一括取得
            $applicationData = Application::with(['attendance.user'])->latest()->get();
        } elseif ($isUser) {
            // 【一般ユーザー用】今ログインしている自分の申請データだけを取得
            $currentUserId = Auth::guard('web')->id(); // ログイン中の一般ユーザーのID

            // ※Applicationモデルにuser_idがある場合、またはattendance経由で絞り込む場合
            // あなたのDB構造に合わせて調整してください。例：attendanceに紐づく自分のデータ
            $applicationData = Application::with(['attendance.user'])
                ->whereHas('attendance', function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId);
                })
                ->latest()
                ->get();
        } else {
            // どちらでもない（未ログイン）ならログイン画面へ
            return redirect()->route('login');
        }

        // 3. フロント制約に合わせたクレンジング
        $applications = $applicationData->map(function ($app) {
            // 安全ガード: 万が一紐づく勤怠データがない場合の例外処理
            if (!$app->attendance) {
                return null;
            }

            // 💡 日付変換の安全関数 (try-catchでInvalidFormatExceptionを完全に防ぎます)
            $safeParseTime = function ($value, $default = '--:--') {
                if (empty($value) || is_array($value) || is_object($value)) {
                    return $default;
                }
                try {
                    return Carbon::parse($value)->format('H:i');
                } catch (\Exception $e) {
                    return $default; // 読めない形式ならデフォルト値を返す
                }
            };

            // 💡 日付自体の安全パース
            try {
                $attendanceDate = Carbon::parse($app->attendance->date)->format('Y年m月d日');
            } catch (\Exception $e) {
                $attendanceDate = '日付不明';
            }

            // 対象データ（修正前）の文字列作成
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

            // 申請データ（修正案）の文字列作成
            $newClockIn = $safeParseTime($app->new_clock_in);
            $newClockOut = $safeParseTime($app->new_clock_out);

            $proposalBreaksList = [];
            if (!empty($app->proposalBreaks) && (is_array($app->proposalBreaks) || is_object($app->proposalBreaks))) {
                $breaksLoop = is_array($app->proposalBreaks) && !isset($app->proposalBreaks['break_in']) && !isset($app->proposalBreaks['breakIn'])
                    ? $app->proposalBreaks
                    : [$app->proposalBreaks];

                foreach ($breaksLoop as $breakPair) {
                    $bIn = is_array($breakPair) ? ($breakPair['break_in'] ?? $breakPair['breakIn'] ?? '') : ($breakPair->break_in ?? $breakPair->breakIn ?? '');
                    $bOut = is_array($breakPair) ? ($breakPair['break_out'] ?? $breakPair['breakOut'] ?? '') : ($breakPair->break_out ?? $breakPair->breakOut ?? '');

                    if ($bIn || $bOut) {
                        // 文字列長や形式を問わず安全に変換
                        $timeIn = (is_string($bIn) && strlen($bIn) <= 5) ? $bIn : $safeParseTime($bIn);
                        $timeOut = (is_string($bOut) && strlen($bOut) <= 5) ? $bOut : $safeParseTime($bOut);
                        $proposalBreaksList[] = $timeIn . '〜' . $timeOut;
                    }
                }
            }

            $proposalBreaksString = !empty($proposalBreaksList) ? implode(', ', $proposalBreaksList) : '無';

            $appDateCombinedString = sprintf(
                "%s\n%s〜%s\n[休憩:%s]",
                $attendanceDate,
                $newClockIn,
                $newClockOut,
                $proposalBreaksString
            );

            // Enumオブジェクトのクレンジング
            $statusString = is_object($app->approval_status) && isset($app->approval_status->value)
                ? $app->approval_status->value
                : (string) $app->approval_status;

            return (object) [
                'id' => $app->attendance_id,
                'attendance_id' => $app->attendance_id,
                'date' => $dateCombinedString,
                'application_date' => $app->created_at ? $app->created_at->toDateTimeString() : $app->attendance->date,
                'comment' => ($app->comment ?? '備考なし'),
                'approval_status' => $statusString,
                'user' => $app->attendance->user,
                'AttendanceRecord' => $app->attendance,
            ];
        })->filter();

        if (Auth::guard('admin')->check()) {

            // 【管理者用】全従業員用の一覧画面を表示
            return view('admin.admin-application-list', [
                'applications' => $applications,
            ]);

        } else {

            // 【一般ユーザー用】自分専用の一覧画面を表示
            return view('user.user-application-list', [ // 👈 新しいBladeファイルを指定
                'applications' => $applications,
            ]);
        }
    }
}

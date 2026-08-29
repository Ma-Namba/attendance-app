<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Application;
use Carbon\Carbon;

class ApplicationController extends Controller
{
    public function userShowApplication(Request $request)
    {
        // 1. 現在ログインしているユーザーの情報を取得
        $userID = Auth::user()->id;

        // 2. 現在ログインしているユーザーの申請データを取得
        $applications = Application::query()
            ->with(['attendance'])
            ->whereHas('attendance', function ($query) use ($userID) {
                $query->where('user_id', $userID);
            })
            ->latest()
            ->get();

        // 3. フロント制約に合わせたクレンジング
        $formattedApplications = $applications->map(function ($app) {

            $attendanceDate = Carbon::parse($app->attendance->date);
            $appCreatedAt = Carbon::parse($app->created_at);

            // 対象データの文字列作成
            $oldClockIn = $app->attendance->clock_in ? Carbon::parse($app->attendance->clock_in)->format('H:i') : '--:--';
            $oldClockOut = $app->attendance->clock_out ? Carbon::parse($app->attendance->clock_out)->format('H:i') : '--:--';

            $oldBreaksList = [];
            $rawOldBreaks = $app->attendance->new_breaks;

            if (!empty($rawOldBreaks) && (is_array($rawOldBreaks) || is_object($rawOldBreaks))) {
                // 配列の配列（2次元）としてループで回す
                foreach ($rawOldBreaks as $b) {
                    // 配列アクセス・オブジェクトアクセスの双方に対応する安全ガード
                    $bIn = is_array($b) ? ($b['breakIn'] ?? null) : ($b->breakIn ?? null);
                    $bOut = is_array($b) ? ($b['breakOut'] ?? null) : ($b->breakOut ?? null);

                    if ($bIn || $bOut) {
                        // "2026-08-06 12:00:00" から 時刻（12:00）だけを綺麗に切り出す
                        $timeIn = $bIn ? Carbon::parse($bIn)->format('H:i') : '--:--';
                        $timeOut = $bOut ? Carbon::parse($bOut)->format('H:i') : '--:--';
                        $oldBreaksList[] = $timeIn . '〜' . $timeOut;
                    }
                }
            }

            $oldBreaksString = !empty($oldBreaksList) ? implode(', ', $oldBreaksList) : '無';

            $dateCombinedString = sprintf(
                "%s\n%s〜%s\n[休憩:%s]",
                $attendanceDate->format('Y年m月d日'),
                $oldClockIn,
                $oldClockOut,
                $oldBreaksString
            );

            // 申請データの文字列作成
            $newClockIn = $app->new_clock_in ? Carbon::parse($app->new_clock_in)->format('H:i') : '--:--';
            $newClockOut = $app->new_clock_out ? Carbon::parse($app->new_clock_out)->format('H:i') : '--:--';

            $proposalBreaksList = [];
            if (!empty($app->proposalBreaks) && (is_array($app->proposalBreaks) || is_object($app->proposalBreaks))) {
                $breaksLoop = is_array($app->proposalBreaks) && !isset($app->proposalBreaks['break_in']) && !isset($app->proposalBreaks['breakIn'])
                    ? $app->proposalBreaks
                    : [$app->proposalBreaks];

                foreach ($breaksLoop as $breakPair) {
                    // スネーク・キャメル双方のタイポを許容する超安全ガード
                    $bIn = is_array($breakPair) ? ($breakPair['break_in'] ?? $breakPair['breakIn'] ?? '') : ($breakPair->break_in ?? $breakPair->breakIn ?? '');
                    $bOut = is_array($breakPair) ? ($breakPair['break_out'] ?? $breakPair['breakOut'] ?? '') : ($breakPair->break_out ?? $breakPair->breakOut ?? '');

                    if ($bIn || $bOut) {
                        $timeIn = (strlen($bIn) > 5) ? Carbon::parse($bIn)->format('H:i') : $bIn;
                        $timeOut = (strlen($bOut) > 5) ? Carbon::parse($bOut)->format('H:i') : $bOut;
                        $proposalBreaksList[] = $timeIn . '〜' . $timeOut;
                    }
                }
            }

            $proposalBreaksString = !empty($proposalBreaksList) ? implode(', ', $proposalBreaksList) : '無';

            $appDateCombinedString = sprintf(
                "%s\n%s〜%s\n[休憩:%s]",
                $attendanceDate->format('Y年m月d日'),
                $newClockIn,
                $newClockOut,
                $proposalBreaksString
            );

            // Enumオブジェクトのクレンジング
            $statusString = is_object($app->approval_status) && isset($app->approval_status->value)
                ? $app->approval_status->value
                : (string) $app->approval_status;

            return [
                'id' => $app->attendance_id,
                'attendance_id' => $app->attendance_id,
                'date' => $dateCombinedString,
                'application_date' => $appDateCombinedString,
                'comment' => ($app->comments ?? $app->attendance->comment ?? '備考なし'),
                'approval_status' => $statusString,
            ];
        })->toArray();

        return view('user.user-application-list',[
            'formattedApplications'=>$formattedApplications,
            'user' => Auth::user(),
        ]);
    }
}

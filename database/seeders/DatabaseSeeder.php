<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\Attendance_break;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /**
         * 1. ユーザー情報の作成（固定アカウント）
         */
        $user1 = User::factory()->create([
            'name' => '一般ユーザー1',
            'email' => 'user1@example.com',
        ]);

        $user2 = User::factory()->create([
            'name' => '一般ユーザー2',
            'email' => 'user2@example.com',
        ]);

        Admin::factory()->create([
            'name' => '管理者ユーザー3',
            'email' => 'user3@example.com',
        ]);

        $generalUsers = collect([$user1, $user2]);

        /**
         * 2. 勤怠・休憩データの作成（実運用に近い日付分布）
         */
        $startDate = Carbon::today()->subMonths(5)->startOfMonth();
        $endDate = Carbon::today();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

            if ($date->isWeekend()) {
                continue;
            }

            $dateStr = $date->toDateString(); // "Y-m-d" 形式

            foreach ($generalUsers as $user) {

                // 複合UK [user_id, date] の衝突を完全に防ぐため、トランザクション内で処理
                DB::transaction(function () use ($user, $date, $dateStr) {

                    if ($date->isToday()) {
                        // 本日は出勤打刻のみ（退勤なし・休憩なしの状態）
                        Attendance::create([
                            'user_id' => $user->id,
                            'date' => $dateStr,
                            'clock_in' => "{$dateStr} 09:00:00", // 厳格な19文字補完
                            'clock_out' => null,
                            'new_breaks' => [], // キャストを考慮し、空配列を明示
                        ]);
                        return; // 次のユーザーへ
                    }

                    // 通常勤務（12:00〜13:00に1時間休憩、18:00退勤）のデータ定義
                    $clockInStr = "{$dateStr} 09:00:00";
                    $breakInStr = "{$dateStr} 12:00:00";
                    $breakOutStr = "{$dateStr} 13:00:00";
                    $clockOutStr = "{$dateStr} 18:00:00";

                    // 親JSON用のキャメルケース統一配列（フロント制約準拠）
                    $newBreaksArray = [
                        [
                            'breakIn' => $breakInStr,
                            'breakOut' => $breakOutStr,
                        ]
                    ];

                    // 1. 親テーブル（attendances）の作成
                    $attendance = Attendance::create([
                        'user_id' => $user->id,
                        'date' => $dateStr,
                        'clock_in' => $clockInStr,
                        'clock_out' => $clockOutStr,
                        'new_breaks' => $newBreaksArray, // モデルの $casts => 'array' で自動JSON化
                    ]);

                    // 2. 子テーブル（attendance_breaks）の物理作成（完全同期）
                    // user_id は存在せず、親の attendance_id を明示流し込みしてNOT NULL制約違反を封殺
                    Attendance_break::create([
                        'attendance_id' => $attendance->id,
                        'break_in' => $breakInStr,
                        'break_out' => $breakOutStr,
                    ]);
                });
            }
        }
    }
}


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

        // 【最重要】user1の当月17日間の特殊パターン配列（通常10, 残業3, 遅刻2, 早退1, 長時間1）
        // 順序に関わらず合算で予測値（遅刻2, 早退1, 長時間1）を確実に満たすように定義
        $user1CurrentMonthPatterns = [
            // 通常勤務 10日間 (9:00 - 18:00)
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            ['in' => '09:00:00', 'out' => '18:00:00'],
            // 残業 3日間 (9:00 - 20:00)
            ['in' => '09:00:00', 'out' => '20:00:00'],
            ['in' => '09:00:00', 'out' => '20:00:00'],
            ['in' => '09:00:00', 'out' => '20:00:00'],
            // 遅刻 2日間 (9:30 - 18:00)
            ['in' => '09:30:00', 'out' => '18:00:00'],
            ['in' => '09:30:00', 'out' => '18:00:00'],
            // 早退 1日間 (9:00 - 17:00)
            ['in' => '09:00:00', 'out' => '17:00:00'],
            // 長時間労働 1日間 (8:00 - 21:00)
            ['in' => '08:00:00', 'out' => '21:00:00'],
        ];

        /**
         * 2. 勤怠・休憩データの作成（実運用に近い日付分布）
         */
        $startDate = Carbon::today()->subMonths(5)->startOfMonth();
        // 当月17日分を確実に生成するため、終了日を当月末まで拡張します
        $endDate = Carbon::today()->endOfMonth();

        // 各月の「生成済み平日日数」をカウントするための連想配列
        $monthlyWeekdayCounts = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

            if ($date->isWeekend()) {
                continue;
            }

            $dateStr = $date->toDateString(); // "Y-m-d"
            $monthStr = $date->format('Y-m'); // "Y-m"

            // 月ごとの平日カウントを初期化・インクリメント
            if (!isset($monthlyWeekdayCounts[$monthStr])) {
                $monthlyWeekdayCounts[$monthStr] = 0;
            }
            $monthlyWeekdayCounts[$monthStr]++;

            $currentCount = $monthlyWeekdayCounts[$monthStr];

            // 一般ユーザー全員に対してループ
            $users = collect([$user1, $user2]);
            foreach ($users as $user) {

                DB::transaction(function () use ($user, $user1, $date, $dateStr, $monthStr, $currentCount, $user1CurrentMonthPatterns) {

                    $isCurrentMonth = ($monthStr === Carbon::today()->format('Y-m'));

                    // --- ★user1 の意図的データ制御ロジック ---
                    if ($user->id === $user1->id) {
                        if (!$isCurrentMonth) {
                            // 過去5ヶ月：各月平日15日（計75日）のみ生成。15日を超えた平日はスキップ
                            if ($currentCount > 15) {
                                return;
                            }
                            // 過去5ヶ月は一律通常勤務 (9:00 - 18:00)
                            $clockInTime = "09:00:00";
                            $clockOutTime = "18:00:00";
                        } else {
                            // 当月：17日間のパターンを適用。17日を超えた平日はスキップ
                            if ($currentCount > 17) {
                                return;
                            }
                            // 配列から今日のパターンを正確に抽出 (0〜16のインデックス)
                            $pattern = $user1CurrentMonthPatterns[$currentCount - 1];
                            $clockInTime = $pattern['in'];
                            $clockOutTime = $pattern['out'];
                        }
                    } else {
                        // --- user2（その他の一般ユーザー）の通常ロジック ---
                        // user2はカレンダー通りの平日すべてに通常勤務データを生成
                        $clockInTime = "09:00:00";
                        $clockOutTime = "18:00:00";
                    }

                    // 日付文字列と結合して厳格な19文字形式を生成
                    $clockInStr = "{$dateStr} {$clockInTime}";
                    $clockOutStr = "{$dateStr} {$clockOutTime}";

                    // 全レコード固定休憩 12:00-13:00（1時間）を完全付与
                    $breakInStr = "{$dateStr} 12:00:00";
                    $breakOutStr = "{$dateStr} 13:00:00";

                    // 親JSON用のキャメルケース配列（フロント制約準拠）
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
                        'new_breaks' => $newBreaksArray,
                    ]);

                    // 2. 子テーブル（attendance_breaks）の物理作成
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


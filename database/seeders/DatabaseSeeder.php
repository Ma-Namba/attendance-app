<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        /**
         * 1. ユーザー情報の作成（固定アカウント）
         */

        // ユーザー1（一般）
        $user1 = User::factory()->create([
            'name' => '一般ユーザー1',
            'email' => 'user1@example.com',
        ]);

        // ユーザー2（一般）
        $user2 = User::factory()->create([
            'name' => '一般ユーザー2',
            'email' => 'user2@example.com',
        ]);

        // ユーザー3（管理者）
        Admin::factory()->create([
            'name' => '管理者ユーザー3',
            'email' => 'user3@example.com',
        ]);

        $generalUsers = collect([$user1, $user2]);

        /**
         * 2. 勤怠・休憩データの作成（実運用に近い日付分布）
         */

        // 過去5ヶ月前（計6ヶ月分）から本日までの範囲を対象とする
        $startDate = Carbon::today()->subMonths(5)->startOfMonth();
        $endDate = Carbon::today();

        // 開始日から本日まで1日ずつループ処理
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {

            // 実運用に合わせるため、土日はスキップして平日のみ生成
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($generalUsers as $user) {

                // 本日のデータはまだ退勤していない状態（出勤中）にするなど、実運用に合わせる
                if ($date->isToday()) {
                    // 本日は出勤打刻のみ（退勤なし・休憩なしの状態）
                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $date->toDateString(),
                        'clock_in' => '09:00:00',
                        'clock_out' => null,
                        'new_breaks' => null,
                    ]);
                    continue;
                }

                // --- 将来の拡張用（★user1 の意図的データ用のプレースホルダー） ---
                if ($user->id === $user1->id) {
                    // ここに将来、当月17日間の特殊パターン（残業・遅刻など）を判定するロジックを注入可能
                    // 現フェーズでは、まずは一律で美しい通常勤務データを生成します
                }

                // 通常の勤怠データをファクトリー経由で生成（休憩も自動同期）
                Attendance::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]);
            }
        }
    }
}

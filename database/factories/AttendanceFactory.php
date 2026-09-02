<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Attendance_break;
use App\Models\Attendance;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // シーダーから注入
            'date' => null,    // シーダーから注入
            'clock_in' => function (array $attributes) {
                $dateStr = $attributes['date'] ?? now()->format('Y-m-d');
                $this->faker->dateTimeBetween("{$dateStr} 09:00:00", "{$dateStr} 10:00:00");
            },
            'clock_out' => function (array $attributes) {
                $dateStr = $attributes['date'] ?? now()->format('Y-m-d');
                $this->faker->dateTimeBetween("{$dateStr} 17:00:00", "{$dateStr} 18:00:00");
            },
        ];
    }

    /**
     * 紐づく休憩データ（JSON & 子テーブル）を完全同期生成する
     */
    public function configure()
    {
        return $this->afterCreating(function (Attendance $attendance) {
            // 退勤時刻がある場合のみ休憩を生成（拡張性を考慮）
            if ($attendance->clock_out) {
                $dateStr = is_string($attendance->date)
                    ? $attendance->date
                    : $attendance->date->format('Y-m-d');

                // 日付と固定時間を結合して、固定休憩 12:00 - 13:00
                $breakData = [
                    [
                        'break_in' => now()->setTime(12, 0, 0)->format('Y-m-d H:i:s'),
                        'break_out' => now()->setTime(13, 0, 0)->format('Y-m-d H:i:s'),
                    ]
                ];

                // 1. 子テーブル (attendance_breaks) へ保存
                foreach ($breakData as $b) {
                    Attendance_break::create([
                        'attendance_id' => $attendance->id,
                        'break_in' => $b['break_in'],
                        'break_out' => $b['break_out'],
                    ]);
                }
            }
        });
    }
}

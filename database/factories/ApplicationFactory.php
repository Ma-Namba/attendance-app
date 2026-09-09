<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // リレーション先（User, Attendance）を未指定の場合に自動生成する定義
            'user_id' => User::factory(),
            'attendance_id' => Attendance::factory(),

            // Bladeやテスト仕様に合わせた初期値
            'approval_status' => '承認待ち', // "承認待ち" または "承認済み"
            'comments' => '備考',
        ];
    }
}


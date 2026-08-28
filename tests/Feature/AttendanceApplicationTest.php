<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Enums\ApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AttendanceApplicationTest extends TestCase
{
    use RefreshDatabase; // スキーマをテスト毎にクリーンアップ

    /** @test */
    public function 一般ユーザーが自身の勤怠詳細画面にアクセスできる()
    {
        // 1. 先にユーザーを確実に作成
        $user = User::factory()->create();

        // 2. そのユーザーに完全に紐づいた勤怠レコードを作成（ user_id, date 複合UK適合）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-03',
            'clock_in' => '2026-08-03 09:00:00',
            'clock_out' => '2026-08-03 18:00:00',
        ]);

        // 3. actingAsで明示的にそのユーザーとしてログイン
        $response = $this->actingAs($user)
            ->get(route('attendance.show', ['id' => $attendance->id]));

        // 4. クレンジングされたデータ構造の検証
        $response->assertStatus(200);
        $response->assertViewHas('user', function ($viewUser) use ($user) {
            return $viewUser->id === $user->id; // $userの要求を満たしているか
        });
        $response->assertViewHas('data', function ($data) {
            return isset($data['year']) && $data['year'] === '2026年'
                && isset($data['date']) && $data['date'] === '08月03日'; // キー縛りハックの検証
        });
    }

    /** @test */
    public function 修正申請が正しくapplicationsテーブルに保存されdatetime結合される()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-03',
        ]);

        // Bladeから届くフラットなリクエストデータをシミュレート
        $requestData = [
            'new_clock_in' => '20:00',
            'new_clock_out' => '22:00',
            'new_break_in' => ['12:00', '15:00'],
            'new_break_out' => ['13:00', '15:15'],
            'comment' => '残業および休憩の修正申請を行います。',
        ];

        $response = $this->actingAs($user)
            ->post(url('/attendance/' . $attendance->id), $requestData);

        // リダイレクトされるか（または保存成功の応答）
        $response->assertRedirect();

        // DBにdatetime型で結合されて格納されているか検証（MySQL/SQLite共通）
        $this->assertDatabaseHas('applications', [
            'attendance_id' => $attendance->id,
            'new_clock_in' => '2026-08-03 20:00:00', // コントローラーのクレンジング結果
            'new_clock_out' => '2026-08-03 22:00:00',
            'approval_status' => '承認待ち',
        ]);
    }
}

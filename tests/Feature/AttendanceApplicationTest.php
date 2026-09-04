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

    public function test_勤怠詳細情報修正（一般ユーザー）時バリデーションメッセージ（出勤が退勤より後）()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 基準となる正しい勤怠レコード（初期状態）をDBに作成
        $todayStr = '2026-08-03';
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => "{$todayStr} 09:00:00",
            'clock_out' => "{$todayStr} 18:00:00",
        ]);

        // バリデーションを発生させるための「不正な入力値」をフォームデータとして用意
        // 出勤(18:00) が 退勤(06:00) より後になっているデータ
        $postData = [
            'new_clock_in' => '18:00',
            'new_clock_out' => '06:00',
            'comment' => 'テスト修正申請',
        ];

        // 一般ユーザーとしてログインし、「POSTメソッド」で送信
        $response = $this->actingAs($user, 'web')
            ->post(route('attendance.application.store', ['id' => $attendance->id]), $postData);

        // バリデーションエラーによって元の画面に 302 リダイレクトされることを確認
        $response->assertStatus(302);

        // 指定したバリデーションメッセージがセッションにあるか検証
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間が不適切な値です',
        ]);
    }

    public function test_勤怠詳細情報修正（一般ユーザー）時バリデーションメッセージ（休憩入が戻より後）()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 基準となる正しい勤怠レコードをDBに作成
        $todayStr = '2026-08-03';
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => "{$todayStr} 09:00:00",
            'clock_out' => "{$todayStr} 18:00:00",
        ]);

        // 休憩入り(13:00) が 休憩戻り(12:00) より後になっている不正な配列データを用意
        // Blade仕様（new_break_in[0]）に合わせてマッピング
        $postData = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['13:00'], // 配列
            'new_break_out' => ['12:00'], // 配列
            'comment' => 'テスト修正申請',
        ];

        // 一般ユーザーとしてログインし、「POSTメソッド」で送信
        $response = $this->actingAs($user, 'web')
            ->post(route('attendance.application.store', ['id' => $attendance->id]), $postData);

        $response->assertStatus(302);

        // 休憩時間のバリデーションエラーを検証
        // 配列エラーの場合は、'new_break_out.0' のようにインデックス形式でエラーが返るため対応
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_勤怠詳細情報修正（一般ユーザー）時バリデーションメッセージ（休憩が退勤より後）()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 基準となる正しい勤怠レコードをDBに作成
        $todayStr = '2026-08-03';
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => "{$todayStr} 09:00:00",
            'clock_out' => "{$todayStr} 18:00:00",
        ]);

        // 休憩入り(19:00) と 休憩戻り(20:00) が退勤時間より後になっている不正な配列データを用意
        // Blade仕様（new_break_in[0]）に合わせてマッピング
        $postData = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'], // 配列
            'new_break_out' => ['20:00'], // 配列
            'comment' => 'テスト修正申請',
        ];

        // 一般ユーザーとしてログインし、「POSTメソッド」で送信
        $response = $this->actingAs($user, 'web')
            ->post(route('attendance.application.store', ['id' => $attendance->id]), $postData);

        $response->assertStatus(302);

        // 休憩時間のバリデーションエラーを検証
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_勤怠詳細情報修正（一般ユーザー）時バリデーションメッセージ（備考の入力なし）()
    {
        // ユーザーを作成
        $user = User::factory()->create();

        // 基準となる正しい勤怠レコードをDBに作成
        $todayStr = '2026-08-03';
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => $todayStr,
            'clock_in' => "{$todayStr} 09:00:00",
            'clock_out' => "{$todayStr} 18:00:00",
        ]);

        // 休憩入り(12:00) と 休憩戻り(13:00) が退勤時間より後になっている不正な配列データを用意
        // Blade仕様（new_break_in[0]）に合わせてマッピング
        $postData = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'], // 配列
            'new_break_out' => ['13:00'], // 配列
            'comment' => '',
        ];

        // 一般ユーザーとしてログインし、「POSTメソッド」で送信
        $response = $this->actingAs($user, 'web')
            ->post(route('attendance.application.store', ['id' => $attendance->id]), $postData);

        $response->assertStatus(302);

        // 休憩時間のバリデーションエラーを検証
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }
}

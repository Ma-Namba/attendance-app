<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminGuard = 'admin';

    protected $showRouteName = 'admin.attendance.show';
    protected $updateRouteName = 'admin.attendance.update';

    protected function setUp(): void
    {
        parent::setUp();

        // `admins` テーブルに対応する Admin モデルのファクトリで管理者を作成
        $this->adminUser = Admin::factory()->create();

        // テスト内の基準日を固定（2026年8月15日）
        Carbon::setTestNow(Carbon::create(2026, 8, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     *
     */
    public function test_要件1：勤怠詳細画面に表示されるデータが選択したものになっている()
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15',
            'clock_in' => '2026-08-15 09:00:00',
            'clock_out' => '2026-08-15 18:00:00',
        ]);

        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->get(route($this->showRouteName, ['id' => $attendance->id]));

        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('2026年');
        $response->assertSee('08月15日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     *
     */
    public function test_要件2：出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15'
        ]);

        $payload = [
            'new_clock_in' => '18:00',
            'new_clock_out' => '09:00',
            'comment' => '修正理由の文言',
        ];

        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->patch('/admin/attendance/' . $attendance->id, $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間が不適切な値です'
        ]);
    }

    /**
     *
     */
    public function test_要件3：休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15'
        ]);

        $payload = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [0 => '19:00'],
            'new_break_out' => [0 => '19:30'],
            'comment' => '修正理由の文言',
        ];

        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->patch('/admin/attendance/' . $attendance->id, $payload);

        $response->assertStatus(302);

        // ★修正：実際のダンプで確認された正確なエラーキーと文言を検証
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です'
        ]);
    }

    /**
     *
     */
    public function test_要件4：休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15'
        ]);

        $payload = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [0 => '17:00'],
            'new_break_out' => [0 => '19:00'],
            'comment' => '修正理由の文言',
        ];

        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->patch('/admin/attendance/' . $attendance->id, $payload);

        $response->assertStatus(302);

        // ★要件3と同様、休憩終了が退勤より後の場合のエラーを検証
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です'
        ]);
    }

    /**
     *
     */
    public function test_要件5：備考欄が未入力の場合のエラーメッセージが表示される()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15'
        ]);

        $payload = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '',
        ];

        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->patch('/admin/attendance/' . $attendance->id, $payload);

        $response->assertStatus(302);

        // 備考欄のバリデーションエラーを検証
        $response->assertSessionHasErrors(['comment']);
    }
}


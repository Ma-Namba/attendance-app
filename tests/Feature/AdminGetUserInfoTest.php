<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin; // プロジェクトの管理者モデルに合わせて変更してください
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGetUserInfoTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 管理者ユーザーの作成（既存の仕様に合わせて調整）
        $this->admin = Admin::factory()->create();

        // テスト内の基準日時を固定（2026年9月）
        Carbon::setTestNow(Carbon::create(2026, 9, 1));
    }

    /**
     * 要件1: 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
     */
    public function test_admin_can_see_all_general_users_name_and_email()
    {
        $user1 = User::factory()->create(['name' => 'テスト太郎', 'email' => 'taro@example.com']);
        $user2 = User::factory()->create(['name' => 'テスト花子', 'email' => 'hanako@example.com']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/staff/list'); // スタッフ一覧画面（管理者）URL

        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('taro@example.com');
        $response->assertSee('テスト花子');
        $response->assertSee('hanako@example.com');
    }

    /**
     * 要件2: ユーザーの勤怠情報が正しく表示される
     */
    public function test_admin_can_see_user_attendance_info_accurately()
    {
        $user = User::factory()->create();

        // datetime型に合わせてデータを定義
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'clock_in' => '2026-09-01 09:00:00',
            'clock_out' => '2026-09-01 18:00:00',
            'new_breaks' => [], // array型
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/attendance/staff/{$user->id}"); // スタッフ別勤怠一覧画面（管理者）URL

        $response->assertStatus(200);
        $response->assertSee('2026-09-01');
        // 画面の表示形式（秒まで表示、あるいは分まで表示）に合わせて調整してください
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * 要件3: 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_admin_can_navigate_to_previous_month_attendance()
    {
        $user = User::factory()->create();

        // 前月（2026年8月）の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-15',
            'clock_in' => '2026-08-15 08:45:00',
            'clock_out' => '2026-08-15 17:45:00',
            'new_breaks' => [],
        ]);

        // クエリパラメータ等で前月(8月)を指定してアクセス
        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/attendance/staff/{$user->id}?date=2026-08");

        $response->assertStatus(200);
        $response->assertSee('2026-08-15');
        $response->assertSee('08:45');
    }

    /**
     * 要件4: 「翌月」を押下した時に表示月の翌月の情報が表示される
     */
    public function test_admin_can_navigate_to_next_month_attendance()
    {
        $user = User::factory()->create();

        // 翌月（2026年10月）の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:15:00',
            'clock_out' => '2026-10-10 18:15:00',
            'new_breaks' => [],
        ]);

        // クエリパラメータ等で翌月(10月)を指定してアクセス
        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/attendance/staff/{$user->id}?date=2026-10");

        $response->assertStatus(200);
        $response->assertSee('2026-10-10');
        $response->assertSee('09:15');
    }

    /**
     * 要件5: 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_admin_can_see_detail_link_and_navigate_to_approve_url()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'clock_in' => '2026-09-01 09:00:00',
            'new_breaks' => [],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/admin/attendance/staff/{$user->id}");

        $response->assertStatus(200);

        // 確定仕様の共用の勤怠詳細画面（管理者）URLがHTML内に含まれているか
        $expectedUrl = "/admin/attendance/{$attendance->id}";
        $response->assertSee($expectedUrl);
    }
}


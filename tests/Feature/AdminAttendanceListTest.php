<?php

namespace Tests\Feature;

use App\Models\Admin; // ★ 管理者モデルをインポート
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $adminGuard = 'admin'; // 管理者用の認証ガード名
    protected $routeName = 'admin.attendance.index';
    protected string $testDate = '2026-08-15';
    protected function setUp(): void
    {
        parent::setUp();

        // `admins` テーブルに対応する Admin モデルのファクトリで管理者を作成
        $this->adminUser = Admin::factory()->create();

        // テスト内の現在日時を 「2026年9月8日」 に固定
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 00:00:00"));
    }

    protected function tearDown(): void
    {
        // テスト終了時に時刻固定を解除
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     *
     */
    public function test_要件1＆2：遷移した際に現在の日付が表示され、その日の全ユーザーの勤怠情報が正確に確認できる()
    {
        // 1. 一般ユーザー（勤怠データを持つ側）のテストデータを準備
        $user1 = User::factory()->create(['name' => 'テスト太郎']);
        $user2 = User::factory()->create(['name' => 'テスト次郎']);

        // 当日（2026-08-15）の勤怠データ（DateTime型）
        Attendance::factory()->create([
            'user_id' => $user1->id,
            'date' => '2026-08-15',
            'clock_in' => '2026-08-15 09:00:00',
            'clock_out' => '2026-08-15 18:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user2->id,
            'date' => '2026-08-15',
            'clock_in' => '2026-08-15 10:00:00',
            'clock_out' => '2026-08-15 19:00:00',
        ]);

        // 別日（2026-08-14）の勤怠データ（当日に表示されてはいけない）
        Attendance::factory()->create([
            'user_id' => $user1->id,
            'date' => '2026-08-14',
            'clock_in' => '2026-08-14 09:00:00',
            'clock_out' => '2026-08-14 18:00:00',
        ]);

        // 2. 管理者ガード（admin）を指定して、名前付きルートでアクセス
        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->get(route($this->routeName));

        // 3. 検証
        $response->assertStatus(200);

        // 【要件2】現在の日付が表示されていること
        $response->assertSee('2026年08月15日'); // 画面の表記に合わせて調整してください

        // 【要件1】その日の全ユーザーの勤怠情報が正確に確認できること
        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        $response->assertSee('テスト次郎');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /**
     *
     */
    public function test_要件3：「前日」を押下した時に前の日の勤怠情報が表示される()
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);

        // 前日（2026-08-14）の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-14',
            'clock_in' => '2026-08-14 08:30:00',
            'clock_out' => '2026-08-14 17:30:00',
        ]);

        // クエリパラメータ ?date=2026-08-14 を伴うURLでアクセス
        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->get(route($this->routeName, ['date' => '2026-08-14']));

        $response->assertStatus(200);

        // 前日の日付と、その日の勤怠情報が正確に表示されていること
        $response->assertSee('2026年08月14日');
        $response->assertSee('テスト太郎');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     *
     */
    public function test_要件4：「翌日」を押下した時に次の日の勤怠情報が表示される()
    {
        $user = User::factory()->create(['name' => 'テスト太郎']);

        // 翌日（2026-08-16）の勤怠データ
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-08-16',
            'clock_in' => '2026-08-16 09:15:00',
            'clock_out' => '2026-08-16 18:15:00',
        ]);

        // クエリパラメータ ?date=2026-08-16 を伴うURLでアクセス
        $response = $this->actingAs($this->adminUser, $this->adminGuard)
            ->get(route($this->routeName, ['date' => '2026-08-16']));

        $response->assertStatus(200);

        // 翌日の日付と、その日の勤怠情報が正確に表示されていること
        $response->assertSee('2026年08月16日');
        $response->assertSee('テスト太郎');
        $response->assertSee('09:15');
        $response->assertSee('18:15');
    }
}

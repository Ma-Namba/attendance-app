<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\Application;

class AdminApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $adminListUrl = '/stamp_correction_request/list';

    protected function setUp(): void
    {
        parent::setUp();
        // テスト用管理者の生成
        $this->admin = Admin::factory()->create();
    }

    /** @test */
    public function 管理者ユーザーの申請リストページで承認待ちの修正申請が全て表示されている()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $attendanceA = Attendance::factory()->create(['user_id' => $userA->id, 'date' => '2026-09-07']);
        $attendanceB = Attendance::factory()->create(['user_id' => $userB->id, 'date' => '2026-09-07']);

        // 【修正】'comment' から 'comments' に変更
        $appA = Application::factory()->create([
            'user_id' => $userA->id,
            'attendance_id' => $attendanceA->id,
            'approval_status' => '承認待ち',
            'comments' => 'Aさんの承認待ち申請'
        ]);
        $appB = Application::factory()->create([
            'user_id' => $userB->id,
            'attendance_id' => $attendanceB->id,
            'approval_status' => '承認待ち',
            'comments' => 'Bさんの承認待ち申請'
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get($this->adminListUrl);

        $response->assertStatus(200);
        $response->assertSee('Aさんの承認待ち申請');
        $response->assertSee('Bさんの承認待ち申請');
    }

    /** @test */
    public function 管理者ユーザーの申請リストページで承認済みの修正申請が全て表示されている()
    {
        $userA = User::factory()->create();
        $attendanceA = Attendance::factory()->create(['user_id' => $userA->id, 'date' => '2026-09-07']);

        // 【修正】'comment' から 'comments' に変更
        $appApproved = Application::factory()->create([
            'user_id' => $userA->id,
            'attendance_id' => $attendanceA->id,
            'approval_status' => '承認済み',
            'comments' => 'Aさんの承認済みデータ'
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get($this->adminListUrl);

        $response->assertStatus(200);
        $response->assertSee('Aさんの承認済みデータ');
    }

    /** @test */
    public function 管理者ユーザーの申請リストページで修正申請の詳細内容が正しく表示されている()
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create(['user_id' => $user->id, 'date' => '2026-09-07']);

        // 【修正】'comment' から 'comments' に変更
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'approval_status' => '承認待ち',
            'comments' => '打刻を忘れたため修正します'
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get("/stamp_correction_request/approve/{$application->id}");

        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('打刻を忘れたため修正します');
    }

    /** @test */
    public function 管理者ユーザーの申請リストページで修正申請の承認処理が正しく行われる()
    {
        $user = User::factory()->create();

        // 一般ユーザーの申請理由は、attendanceテーブルの comment カラムに保存される仕様
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-07',
            'comment' => '打刻を忘れたため修正します（一般ユーザーの理由）'
        ]);

        // 申請時は管理者コメント（comments）はまだ空（null）の状態
        $application = Application::factory()->create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'approval_status' => '承認待ち',
        ]);

        // 管理者が「承認済み」へ更新し、管理者コメントをPOST送信する
        $response = $this->actingAs($this->admin, 'admin')
            ->post("/stamp_correction_request/approve/{$application->id}", [
                'approval_status' => '承認済み',
                'comments' => '確認しました。'
            ]);

        $response->assertRedirect();

        // applications テーブルの approval_status と comments が正しく更新されているか検証
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'approval_status' => '承認済み',
            'comments' => '確認しました。'
        ]);
    }

}

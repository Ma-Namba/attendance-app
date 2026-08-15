<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Models\Attendance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceTest extends TestCase
{
    // メソッド間のメモリ・セッションの残り香を完全に物理クリーンアップするトレイト
    use DatabaseMigrations;

    protected string $testDate = '2026-08-15';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Tokyo']);
        date_default_timezone_set('Asia/Tokyo');
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 00:00:00"));

        // プロジェクトの設定に負けないよう、テスト起動時にテーブルを強制作成
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @test
     * 正常系: 画面UIへの現在日時出力、各打刻ボタンの連動（出勤・休憩入・休憩戻・退勤）、
     * および一日に何回でも休憩ができる仕様のフルフローUI網羅テスト
     */
    public function test_一般ユーザーの一連の打刻UI仕様と打刻画面への反映が正常に機能すること(): void
    {
        $user = User::factory()->create();
        $createRoute = route('attendance.create');

        // ==========================================
        // 0. 初期状態：現在の日時情報がUIと同じ形式で出力されていることの検証
        // ==========================================
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 08:50:00", 'Asia/Tokyo'));

        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertStatus(200);

        // 💡 画面上の初期表記形式（UIテキスト）を検証
        $response->assertSee('2026/08/15 (土)');
        $response->assertSee('08:50');
        $response->assertSee('<p class="attendance__status--item">勤務外</p>', false);
        $response->assertSee('<button class="attendance__button--submit--clock-in" type="submit" name="action" value="clock_in">出勤</button>', false);

        // ==========================================
        // 1. 出勤ボタンが正しく機能する ➔ 画面ステータスは「出勤中」に切り替わる
        // ==========================================
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 09:00:00", 'Asia/Tokyo'));

        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->post(route('attendance.store'), ['action' => 'clock_in']);
        $response->assertRedirect();

        // SQLiteの日付型ブレを10文字に上書き調停
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 画面を再取得して、打刻画面上の現在のステータスが「出勤中」に切り替わっているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertStatus(200);
        $response->assertSee('<p class="attendance__status--item">出勤中</p>', false);

        // 親レコード特定
        $attendance = Attendance::where('user_id', $user->id)->where('date', 'LIKE', "{$this->testDate}%")->first();
        $this->assertNotNull($attendance);

        // ==========================================
        // 2. 休憩ボタンが正しく機能する（1回目：12:00:00）➔ 画面ステータスは「休憩中」へ
        // ==========================================
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 12:00:00", 'Asia/Tokyo'));

        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');
        $attendance->unsetRelations();

        $response = $this->post(route('attendance.store'), ['action' => 'break_in']);
        $response->assertRedirect();
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 打刻画面上で現在のステータスが「休憩中」に切り替わっているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertSee('<p class="attendance__status--item">休憩中</p>', false);

        // ==========================================
        // 3. 休憩戻ボタンが正しく機能する（1回目：12:30:00）➔ 画面ステータスは「出勤中」へ戻る
        // ==========================================
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 12:30:00", 'Asia/Tokyo'));

        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');
        $attendance->unsetRelations();

        $response = $this->post(route('attendance.store'), ['action' => 'break_out']);
        $response->assertRedirect();
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 打刻画面上で現在のステータスが「出勤中」に戻っているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertSee('<p class="attendance__status--item">出勤中</p>', false);

        // ==========================================
        // 4. 休憩・休憩戻は一日に何回でもできる（2回目：15:00:00 〜 15:15:00）
        // ==========================================
        // 💡 2回目の休憩入り打刻
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 15:00:00", 'Asia/Tokyo'));
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');
        $attendance->unsetRelations();

        $response = $this->post(route('attendance.store'), ['action' => 'break_in']);
        $response->assertRedirect();
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 2回目の休憩入りにより、画面ステータスが再び「休憩中」になっているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertSee('<p class="attendance__status--item">休憩中</p>', false);

        // 💡 2回目の休憩戻り打刻
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 15:15:00", 'Asia/Tokyo'));
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');
        $attendance->unsetRelations();

        $response = $this->post(route('attendance.store'), ['action' => 'break_out']);
        $response->assertRedirect();
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 2回目の休憩戻りにより、画面ステータスが再び「出勤中」に戻っているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertSee('<p class="attendance__status--item">出勤中</p>', false);

        // ==========================================
        // 5. 退勤ボタンが正しく機能する ➔ 打刻画面ステータスが「退勤済」に切り替わる
        // ==========================================
        Carbon::setTestNow(Carbon::parse("{$this->testDate} 18:00:00", 'Asia/Tokyo'));

        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');
        $attendance->unsetRelations();

        $response = $this->post(route('attendance.store'), ['action' => 'clock_out']);
        $response->assertRedirect();
        DB::table('attendances')->where('user_id', $user->id)->update(['date' => $this->testDate]);

        // 💡 打刻画面上で現在のステータスが最終状態の「退勤済」に切り替わっているか検証
        $user = $user->fresh();
        config(['fortify.guard' => 'web']);
        $this->actingAs($user, 'web');
        Auth::shouldUse('web');

        $response = $this->get($createRoute);
        $response->assertSee('<p class="attendance__status--item">退勤済</p>', false);

        $this->travelBack();
    }

    /**
     * @test
     * 異常系: 出勤は一日一回のみできることの検証（複合UK制約）
     */
    public function test_出勤は一日一回のみしか登録できないこと(): void
    {
        $user = User::factory()->create();
        $today = '2026-08-15';

        Attendance::create([
            'user_id' => $user->id,
            'date' => "{$today}",
            'clock_in' => "{$today} 09:00:00",
            'new_breaks' => [],
        ]);

        $this->expectException(QueryException::class);

        Attendance::create([
            'user_id' => $user->id,
            'date' => "{$today}",
            'clock_in' => "{$today} 09:05:00",
            'new_breaks' => [],
        ]);
    }
}


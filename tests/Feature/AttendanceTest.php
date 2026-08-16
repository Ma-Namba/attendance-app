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

    /**
     * @test
     * 自分が行った勤怠情報が全て表示されている
     */
    public function test_自分が行った勤怠情報が全て表示されている(): void
    {
        // テストユーザーの作成とログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 他人のデータが混入しないことを検証するため、別のユーザーも作成
        $otherUser = User::factory()->create();

        // テスト実行月を「2026-08」に偽装固定
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));

        // ログインユーザーの勤怠データを2日分作成
        Attendance::create([
            'user_id' => $user->id,
            'date' => "2026-08-01",
            'clock_in' => "2026-08-01 09:00:00",
            'clock_out' => "2026-08-01 18:00:00",
            'new_breaks' => [],
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => "2026-08-02",
            'clock_in' => "2026-08-02 09:00:00",
            'clock_out' => "2026-08-02 18:00:00",
            'new_breaks' => [],
        ]);

        // 他人の勤怠データを作成（一覧に表示されてはいけないデータ）
        Attendance::create([
            'user_id' => $otherUser->id,
            'date' => "2026-08-01",
            'clock_in' => "2026-08-01 09:00:00",
            'clock_out' => "2026-08-01 18:00:00",
            'new_breaks' => [],
        ]);

        // 【確定解決】確定したルート名を使用してURL「attendance/list」へリクエスト
        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);

        // ビューに渡されたデータ（コレクション）を取得
        $recordsCollection = $response->viewData('formattedAttendanceRecords');

        // アサーション：自分のデータのみ（2件）が取得されていること
        $this->assertCount(2, $recordsCollection);

        // 純粋な多次元配列に完全に変換
        $records = $recordsCollection->toArray();

        // インデックスを指定して各レコードのクレンジング結果を厳格に検証
        // 1レコード目（0番目）
        $this->assertEquals('2026-08-01', $records[0]['date']);
        $this->assertEquals('09:00', $records[0]['clock_in']);
        $this->assertEquals('18:00', $records[0]['clock_out']);

        // 2レコード目（1番目）
        $this->assertEquals('2026-08-02', $records[1]['date']);

        // 時間偽装をリセット
        Carbon::setTestNow();
    }

    /**
     * @test
     * 勤怠一覧画面に遷移した際に現在の月が表示される
     */
    public function test_勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 現在の時刻を「2026-08-17」に完全に固定
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));

        // 確定ルートでアクセス
        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);

        // ビューに渡された $date を取得
        $viewDate = $response->viewData('date');

        // インポート元の違いによる判定泥沼化を防ぐため、Laravel標準のCarbonフルパスで厳格にアサーション
        $this->assertInstanceOf(\Carbon\Carbon::class, $viewDate);
        $this->assertEquals('2026-08-01', $viewDate->toDateString());

        // 画面上に提供されたBladeの「2026/08」というテキストが含まれているか検証
        $response->assertSee('2026/08');

        Carbon::setTestNow();
    }

    /**
     * @test
     * 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        // 先月の通常データを1件仕込む
        Attendance::create([
            'user_id' => $user->id,
            'date' => "2026-07-10",
            'clock_in' => "2026-07-10 09:00:00",
            'clock_out' => "2026-07-10 18:00:00",
            'new_breaks' => [],
        ]);

        // 【確定解決】提供Bladeの「?date=...」の仕様とURLを完全に一致させて遷移
        $response = $this->get(route('attendance.index', ['date' => '2026-07']));

        $response->assertStatus(200);

        // ビューに渡されたデータが 7 月のものかアサーション
        $viewDate = $response->viewData('date');
        $this->assertEquals('2026-07-01', $viewDate->toDateString());

        // 文字列型前月・次月パラメータが月末バグを起こさずスライドしているか確認
        $this->assertEquals('2026-06', $response->viewData('previousMonth'));
        $this->assertEquals('2026-08', $response->viewData('nextMonth'));

        // 7月のデータのみ（1件）が抽出されていることの検証
        $recordsCollection = $response->viewData('formattedAttendanceRecords');
        $this->assertCount(1, $recordsCollection);

        $records = $recordsCollection->toArray();
        $this->assertEquals('2026-07-10', $records[0]['date']);

        Carbon::setTestNow();
    }

    /**
     * @test
     * 「翌月」を押下した時に表示月の翌月の情報が表示される
     */
    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00'));

        // 翌月（9月）のデータを1件仕込む
        Attendance::create([
            'user_id' => $user->id,
            'date' => "2026-09-05",
            'clock_in' => "2026-09-05 09:00:00",
            'clock_out' => "2026-09-05 18:00:00",
            'new_breaks' => [],
        ]);

        // 【確定解決】提供Bladeの「?date=...」の仕様とURLを完全に一致させて遷移
        $response = $this->get(route('attendance.index', ['date' => '2026-09']));

        $response->assertStatus(200);

        // ビューに渡されたデータが 9 月のものかアサーション
        $viewDate = $response->viewData('date');
        $this->assertEquals('2026-09-01', $viewDate->toDateString());

        $this->assertEquals('2026-08', $response->viewData('previousMonth'));
        $this->assertEquals('2026-10', $response->viewData('nextMonth'));

        // 9月のデータのみ（1件）が抽出されていることの検証
        $recordsCollection = $response->viewData('formattedAttendanceRecords');
        $this->assertCount(1, $recordsCollection);

        $records = $recordsCollection->toArray();
        $this->assertEquals('2026-09-05', $records[0]['date']);

        Carbon::setTestNow();
    }

}

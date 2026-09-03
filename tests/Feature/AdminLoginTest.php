<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Admin;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase; // テストごとにデータベースをリセット
    /**
     * A basic feature test example.
     */
    public function test_管理者向けのログイン画面が正常に表示されるか(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_管理者ログイン画面でバリデーションメッセージの検証（異常系）(): void
    {
        // 1. テスト用の管理者データを1件作成
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 2-1. 間違ったパスワードでリクエスト
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        // 2-2. ゲスト状態（未ログイン）であることを検証
        $this->assertGuest('admin');

        // 2-3. バリデーションメッセージが含まれているか検証
        $response->assertSessionHasErrors([
            'email' =>  'ログイン情報が登録されていません',
        ]);

        // 3-1. 未入力でリクエスト
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => '',
            'password' => '',
        ]);

        // 3-2. ゲスト状態（未ログイン）であることを検証
        $this->assertGuest('admin');

        // 3-3. バリデーションメッセージが含まれているか検証
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_管理者ユーザーが正しい認証情報でログインでき、勤怠一覧へリダイレクトされることを検証（正常系）(): void
    {
        // 1. テスト用の管理者データを1件作成
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 2. ログインをリクエスト
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        // 3. adminガードで認証されているか検証
        $this->assertAuthenticated('admin');

        // 4. リダイレクト先（勤怠一覧ルート）かを検証
        $response->assertRedirect(route('admin.attendance.index'));
    }
}

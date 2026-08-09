<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class UserLoginTest extends TestCase
{
    use RefreshDatabase; //テストごとにDBをリセット

    public function test_一般ユーザー向けのログイン画面が正常に表示されるか(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_一般ユーザーログイン画面でバリデーションメッセージの検証（異常系）(): void
    {
        // 1. テスト用ユーザーの作成()
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // 2-1. 間違ったパスワードでリクエスト
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // 2-2. ログイン画面へリダイレクトバックされることの検証
        $response->assertRedirect('/login');

        // 2-3. バリデーションエラーがあることを確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        // 2-4. 認証されていない状態であることの検証
        $this->assertGuest('web');

        // 3-1. 未入力でリクエスト
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        // 3-2. ログイン画面へリダイレクトバックされることの検証
        $response->assertRedirect('/login');

        // 3-3. バリデーションエラーがあることを確認
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);

        // 3-4. 認証されていない状態であることの検証
        $this->assertGuest('web');

        // 4-1. 未入力でリクエスト
        $response = $this->from('/login')->post('/login', [
            'email' => 'wrong-address@email.com',
            'password' => $user->password,
        ]);

        // 4-2. ログイン画面へリダイレクトバックされることの検証
        $response->assertRedirect('/login');

        // 4-3. バリデーションエラーがあることを確認
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        // 4-4. 認証されていない状態であることの検証
        $this->assertGuest('web');
    }

    public function test_一般ユーザーが正しい認証情報でログインでき、打刻画面へリダイレクトされることを検証（正常系）():void
    {
        // 1. テスト用ユーザーの作成
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        // 2. ログイン画面の表示確認
        $response = $this->get('/login');
        $response->assertStatus(200);

        // 3. ログインリクエストの送信
        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // 4. セッションと認証状態、リダイレクト先の検証
        $response->assertRedirect('/attendance');
        $this->assertAuthenticatedAs($user, 'web');
    }
}

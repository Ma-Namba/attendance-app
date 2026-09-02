<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class UserRegisterTest extends TestCase
{
    use RefreshDatabase; //テストごとにDBをリセット

    public function test_新規登録画面が正常に表示されるか(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_会員登録画面でバリデーションメッセージの検証（異常系）(): void
    {
        // 1-1. 空のデータを送信して必須バリデーションを検証
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        // 1-2. バリデーションエラーがあることを確認
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);

        // 2-1. 不正な形式のデータを送信して検証(メールアドレス,パスワード)
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'invalid-email-format', // 不正なメール形式
            'password' => 'short',               // 短すぎるパスワード
            'password_confirmation' => 'short', // パスワード確認は一致
        ]);

        // 2-2. メール形式ではない、パスワードの入力規制違反の場合
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスはメール形式で入力してください',
            'password' => 'パスワードは8文字以上で入力してください',
        ]);

        // 3-1. 不正な形式のデータを送信して検証（メールアドレス、パスワード確認）
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'invalid-email-format', // 不正なメール形式
            'password' => 'password',               // 正常なパスワード
            'password_confirmation' => 'mismatch', // パスワード確認は一致
        ]);

        // 3-2. メール形式ではない、パスワード確認の不一致
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスはメール形式で入力してください',
            'password' => 'パスワードと一致しません',
        ]);
    }

    public function test_正しい入力情報での会員登録と自動ログイン・リダイレクトの検証（正常系）(): void
    {
        // 1. 新規登録に必要なValidなデータを定義
        $registrationData = [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // 2. 登録処理を実行（CSRFはテスト環境では自動でハンドリングされます）
        $response = $this->post('/register', $registrationData);

        // 3. データベースに登録したユーザー情報が保存されたか検証
        $this->assertDatabaseHas('users', [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
        ]);

        // 4. パスワードがハッシュ化（生パスワードで保存されていないこと）されているか検証
        $user = User::where('email', 'yamada@example.com')->first();
        $this->assertTrue(\Hash::check('password123', $user->password));

        // 5. 登録完了後に自動でログイン状態（認証済）になっているか検証
        $this->assertAuthenticatedAs($user);

        // 6. ログイン完了後、一般ユーザーの指定リダイレクト先（/attendance）に遷移するか検証
        $response->assertRedirect('/attendance');
    }
}

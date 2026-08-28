<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認証はミドルウェアで制御するためtrue
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => 'required|date_format:H:i',
            'new_clock_out' => 'required|date_format:H:i|after:new_clock_in',

            'new_break_in.*' => 'nullable|date_format:H:i',
            // 休憩戻りのバリデーション
            'new_break_out.*' => [
                'nullable',
                'date_format:H:i',
                    function ($attribute, $value, $fail) {
                        // 1. 属性名から現在の配列インデックス（0, 1, 2...）を抽出
                        preg_match('/\.(\d+)$/', $attribute, $matches);
                        $index = $matches[1] ?? null;

                        if ($index !== null) {
                            $breakIn = $this->input("new_break_in.{$index}");
                            $clockOut = $this->input('new_clock_out');

                            // タイムスタンプに変換して時間の一致・前後を比較できるようにする(空欄の場合変換しない)
                            $currentBreakInTime = !empty($breakIn) ? strtotime($breakIn) : null;
                            $currentBreakOutTime = !empty($value) ? strtotime($value) : null;

                            if (!empty($breakIn) && !empty($value)) {
                                $currentBreakInTime = strtotime($breakIn);
                                $currentBreakOutTime = strtotime($value);

                                // 【条件1】休憩戻り時間が、同じ回の休憩入り時間より早い（または同時）の場合
                                if ($currentBreakOutTime <= $currentBreakInTime) {
                                    $fail('休憩時間が不適切な値です');
                                    return;
                                }
                            }

                            // 【条件1】休憩戻り時間が、同じ回の休憩入り時間より早い（または同時）の場合
                            if ($currentBreakInTime && $currentBreakOutTime <= $currentBreakInTime) {
                                $fail('休憩時間が不適切な値です');
                                return; // 1つ目のエラーが出たらこの回の判定を終了
                            }

                            // 【条件2】休憩入り、または休憩戻りが、退勤時間より遅い（または同時）の場合
                            if (!empty($clockOut)) {
                                $currentClockOutTime = strtotime($clockOut);
                                $currentBreakInTime = !empty($breakIn) ? strtotime($breakIn) : null;
                                $currentBreakOutTime = !empty($value) ? strtotime($value) : null;

                                // 休憩入りが退勤時間以降、または休憩戻りが退勤時間以降の場合
                                $isBreakInAfterClockOut = $currentBreakInTime && $currentBreakInTime >= $currentClockOutTime;
                                $isBreakOutAfterClockOut = $currentBreakOutTime && $currentBreakOutTime >= $currentClockOutTime;

                                if ($isBreakInAfterClockOut || $isBreakOutAfterClockOut) {
                                    $fail('休憩時間もしくは退勤時間が不適切な値です');
                                    return;
                                }
                            }
                        }
                    }
            ],
            'comment' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            // 1. 出勤時間の不適切チェック（形式エラー時）
            'new_clock_in.date_format' => '出勤時間が不適切な値です',
            'new_clock_in.required' => '出勤時間を入力してください。',

            // 2. 休憩時間の不適切チェック（終了が開始より前、または形式エラー時）
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',

            // 3. 休憩時間もしくは退勤時間の不適切チェック（退勤が出勤より前の場合）
            'new_clock_out.after' => '出勤時間が不適切な値です',
            'new_clock_out.date_format' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '退勤時間を入力してください',

            // 4. コメントの不適切チェック
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は500文字以内で入力してください',
        ];
    }
}

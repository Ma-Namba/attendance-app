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

            'new_break_in' => 'nullable|array',
            'new_break_in.*' => 'nullable|date_format:H:i',
            'new_break_out' => 'nullable|array',
            'new_break_out.*' => 'nullable|date_format:H:i|after:new_break_in.*',

            'comment' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時刻を入力してください。',
            'new_clock_out.required' => '退勤時刻を入力してください。',
            'new_clock_out.after' => '退勤時刻は出勤時刻より後の時間を入力してください。',
            'new_break_out.*.after' => '休憩終了時刻は休憩開始時刻より後の時間を入力してください。',
        ];
    }
}

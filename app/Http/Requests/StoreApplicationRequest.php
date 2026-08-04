<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => 'required|date_format:H:i',
            'new_clock_out' => 'required|date_format:H:i|after:new_clock_in',
            'comment' => 'nullable|string|max:255',

            // 休憩データの配列バリデーション
            'proposalBreaks' => 'nullable|array',
            'breaks.*.break_start' => 'required_with:breaks|date_format:H:i',
            'breaks.*.break_end' => 'required_with:breaks.*.break_start|date_format:H:i|after:breaks.*.break_start',
        ];
    }
}

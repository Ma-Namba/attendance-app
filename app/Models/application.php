<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class application extends Model
{
    /**
     * 複数代入を許可する属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_id',
        'user_id',
        'new_check_in',
        'new_check_out',
        'proposed_breaks',
        'comment',
        'status',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'proposed_breaks' => 'array', // DBのJSON文字列をPHPの配列(array)に自動変換
    ];

    // リレーション：親である勤怠データを取得（1対多の対になる相手）
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // リレーション：申請したユーザーを取得
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\ApprovalStatus;

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
        'proposalBreaks',
        'comment',
        'approval_status', //誤字修正
    ];

    // 🌟 approval_statusをEnumクラスとしてキャストする
    protected $casts = [
        'approval_status' => ApprovalStatus::class,
        'proposal_breaks' => 'array',
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

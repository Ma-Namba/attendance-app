<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\ApprovalStatus;

class Attendance extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'new_breaks',
    ];

    /**
     * Summary of casts
     * @var array<string,string>
     */
    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'new_breaks' => 'array',
    ];

    /**
     * リレーション：所属する一般ユーザーを取得
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * リレーション：複数の休憩データを持つ
     */
    public function attendanceBreaks(): HasMany
    {
        return $this->hasMany(attendance_break::class, 'attendance_id');
    }

    /**
     * リレーション：1つの修正申請を持つ
     */
    public function applications(): HasMany
    {
        // 1対1 (hasOne) から 1対多 (hasMany) に変更
        return $this->hasMany(Application::class, 'attendance_id');
    }

    /**
     * メソッド：現在のステータス（勤務外・出勤中・休憩中・退勤済）を判定して返す
     */
    public function getCurrentStatus():string
    {
        if (!$this->clock_in) {
            return '勤務外';
        }
        if ($this->clock_in && !$this->clock_out) {
            $latestBreak = $this->attendanceBreaks()->latest()->first();
            if ($latestBreak && !$latestBreak->break_out) {
                return '休憩中'; // ← ここで「休憩中」が返る
            }
            return '出勤中';     // ← ここで「出勤中」が返る
        }
        return '退勤済';         // ← ここで「退勤済」が返る
    }

    /**
     * 合計休憩時間をリアルタイムに計算して返すアクセサ
     */
    public function getTotalBreakTimeAttribute(): ?string
    {
        $totalMinutes = 0;

        // リレーション名とカラム名を修正（break_in, break_out）
        foreach ($this->attendanceBreaks as $break) {
            if ($break->break_in && $break->break_out) {
                $start = Carbon::parse($break->break_in);
                $end = Carbon::parse($break->break_out);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        if ($totalMinutes === 0) {
            return null;
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    /**
     * 合計実働時間をリアルタイムに計算して返すアクセサ
     */
    public function getTotalTimeAttribute(): ?string
    {
        // カラム名を clock_in, clock_out に修正
        if (!$this->clock_in || !$this->clock_out) {
            return null;
        }

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        $grossMinutes = $clockIn->diffInMinutes($clockOut);

        // カラム名を break_in, break_out に修正
        $breakMinutes = 0;
        foreach ($this->attendanceBreaks as $break) {
            if ($break->break_in && $break->break_out) {
                $breakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
            }
        }

        $netMinutes = $grossMinutes - $breakMinutes;

        if ($netMinutes < 0) {
            return '00:00:00';
        }

        $hours = floor($netMinutes / 60);
        $minutes = $netMinutes % 60;
        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    /**
     * 現在承認待ちの申請があるかどうかを判定するカスタム属性
     */
    public function getHasPendingApplicationAttribute(): bool
    {
        // メソッド名を applications() に、カラム名を approval_status に、値をEnumに修正
        return $this->applications()
            ->where('approval_status', ApprovalStatus::PENDING)
            ->exists();
    }
}

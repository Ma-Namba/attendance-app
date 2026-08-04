<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class attendance extends Model
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
        'new_breaks'
    ];

    /**
     * Summary of casts
     * @var array<string,string>
     */
    protected $casts = [
        'date' => 'date',
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
    public function attendance_break()
    {
        return $this->hasMany(attendance_break::class);
    }

    /**
     * リレーション：1つの修正申請を持つ
     */
    public function application()
    {
        return $this->hasOne(Application::class);
    }

    /**
     * メソッド：現在のステータス（勤務外・出勤中・休憩中・退勤済）を判定して返す
     */
    public function getCurrentStatus():string
    {
        if (!$this->check_in) {
            return '勤務外';
        }
        if ($this->check_in && !$this->check_out) {
            $latestBreak = $this->attendanceBreaks()->latest()->first();
            if ($latestBreak && !$latestBreak->break_end) {
                return '休憩中'; // ← ここで「休憩中」が返る
            }
            return '出勤中';     // ← ここで「出勤中」が返る
        }
        return '退勤済';         // ← ここで「退勤済」が返る
    }

    /**
     * 合計休憩時間をリアルタイムに計算して返すアクセサ:total_break_time
     *
     * @return string|null
     */
    public function getTotalBreakTimeAttribute(): ?string
    {
        $totalMinutes = 0;

        // 紐づくすべての休憩レコードをループで回す
        foreach ($this->attendanceBreaks as $break) {
            // 休憩戻り（break_end）がしっかり記録されている場合のみ計算
            if ($break->break_start && $break->break_end) {
                $start = Carbon::parse($break->break_start);
                $end = Carbon::parse($break->break_end);

                // 差分の分（分単位）を足していく
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        if ($totalMinutes === 0) {
            return null;
        }

        // 「H:i:s」の形式の文字列に変換して返す
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    /**
     * 合計実働時間（退勤 - 出勤 - 休憩合計）をリアルタイムに計算して返すアクセサ:total_time
     *
     * @return string|null
     */
    public function getTotalTimeAttribute(): ?string
    {
        // 出勤と退勤が揃っていない場合は計算できないので null を返す
        if (!$this->check_in || !$this->check_out) {
            return null;
        }

        $checkIn = Carbon::parse($this->check_in);
        $checkOut = Carbon::parse($this->check_out);

        // 拘束時間（出勤から退勤までの総分数）を計算
        $grossMinutes = $checkIn->diffInMinutes($checkOut);

        // 先ほど作った「合計休憩時間」のアクセサを呼び出して、休憩の総分数を出す
        $breakMinutes = 0;
        foreach ($this->attendanceBreaks as $break) {
            if ($break->break_start && $break->break_end) {
                $breakMinutes += Carbon::parse($break->break_start)->diffInMinutes(Carbon::parse($break->break_end));
            }
        }

        // 実働時間 ＝ 拘束時間 ー 休憩時間
        $netMinutes = $grossMinutes - $breakMinutes;

        if ($netMinutes < 0) {
            return '00:00:00';
        }

        // 「H:i:s」の形式の文字列に変換して返す
        $hours = floor($netMinutes / 60);
        $minutes = $netMinutes % 60;
        return sprintf('%02d:%02d:00', $hours, $minutes);
    }
}

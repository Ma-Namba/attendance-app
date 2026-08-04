<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // リレーション：1人のユーザーは複数の勤怠データを持つ
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // リレーション：1人のユーザーは複数の修正申請を持つ
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    /**
     * データベースにはない「attendance_status」プロパティを動的に生み出す（アクセサ）
     * @return string
     */
    public function getAttendanceStatusAttribute(): string
    {
        $today = now()->format('Y-m-d');
        $attendance = $this->attendances()->where('date', $today)->first();

        if (!$attendance){
            return '勤務外';
        }
        // レコードがある場合は、Attendanceモデルの「getCurrentStatus()」を実行して返す
        return $attendance->getCurrentStatus();

    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();  //usersテーブルと紐付け(userが消えたら勤怠も消える)
            $table->date('date'); //勤務日(例: 2026-08-04)
            $table->time('clock_in')->nullable(); //出勤時刻
            $table->time('clock_out')->nullable(); //退勤時刻
            $table->text('new_breaks')->nullable(); // JSON文字列を保持するTEXT型
            $table->timestamps();

            // 1ユーザー1日1レコードを保証する一意制約
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

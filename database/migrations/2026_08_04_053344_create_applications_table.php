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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 出退勤の修正後の希望時刻
            $table->time('new_clock_in')->nullable();
            $table->time('new_clock_out')->nullable();

            // 複数の休憩の修正案(TEXT形式)
            $table->text('proposalBreaks')->nullable();

            // 修正のコメント
            $table->string('comment');

            // 承認待ち,承認済
            $table->enum('approval_status', ['承認待ち', '承認済み'])->default('承認待ち');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};

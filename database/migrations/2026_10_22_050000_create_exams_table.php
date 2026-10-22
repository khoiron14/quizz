<?php

use App\Enums\ExamStatus;
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
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->integer('duration_minutes');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('token', 6);
            $table->boolean('is_randomize')->default(false);
            $table->string('status', 20)->default(ExamStatus::Draft->value);
            $table->timestamps();

            $table->index(['token', 'status'], 'idx_exams_token_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

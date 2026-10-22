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
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('question_text');
            $table->json('options')->nullable();
            $table->json('answer_key')->nullable();
            $table->double('score_weight')->default(1.0);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index('exam_id', 'idx_questions_exam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

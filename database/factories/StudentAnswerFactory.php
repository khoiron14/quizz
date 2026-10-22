<?php

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAnswer>
 */
class StudentAnswerFactory extends Factory
{
    protected $model = StudentAnswer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => ExamAttempt::factory(),
            'question_id' => Question::factory(),
            'answer_content' => 'A',
            'is_flagged' => false,
            'score_awarded' => null,
            'last_synced_at' => now(),
        ];
    }
}

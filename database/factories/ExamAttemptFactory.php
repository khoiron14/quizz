<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'student_id' => User::factory(),
            'started_at' => now(),
            'submitted_at' => null,
            'extra_time_minutes' => 0,
            'total_score' => null,
            'status' => AttemptStatus::InProgress,
        ];
    }

    /**
     * Indicate that the attempt is submitted.
     */
    public function submitted(?float $totalScore = 100.0): static
    {
        return $this->state(fn () => [
            'submitted_at' => now(),
            'total_score' => $totalScore,
            'status' => AttemptStatus::Submitted,
        ]);
    }
}

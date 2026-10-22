<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory(),
            'title' => fake()->sentence(4),
            'duration_minutes' => 60,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'token' => strtoupper(Str::random(6)),
            'is_randomize' => false,
            'status' => ExamStatus::Published,
        ];
    }

    /**
     * Indicate that the exam is draft.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Draft,
        ]);
    }

    /**
     * Indicate that the exam is published.
     */
    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Published,
        ]);
    }

    /**
     * Indicate that the exam is archived.
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ExamStatus::Archived,
        ]);
    }
}

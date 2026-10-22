<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'type' => QuestionType::MultipleChoice,
            'question_text' => fake()->sentence().'?',
            'options' => [
                ['key' => 'A', 'text' => fake()->word()],
                ['key' => 'B', 'text' => fake()->word()],
                ['key' => 'C', 'text' => fake()->word()],
                ['key' => 'D', 'text' => fake()->word()],
            ],
            'answer_key' => 'A',
            'score_weight' => 1.0,
            'order' => 1,
        ];
    }

    /**
     * Multiple choice question.
     */
    public function multipleChoice(string $answerKey = 'A'): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::MultipleChoice,
            'options' => [
                ['key' => 'A', 'text' => 'Option A'],
                ['key' => 'B', 'text' => 'Option B'],
                ['key' => 'C', 'text' => 'Option C'],
                ['key' => 'D', 'text' => 'Option D'],
            ],
            'answer_key' => $answerKey,
        ]);
    }

    /**
     * Essay question.
     */
    public function essay(): static
    {
        return $this->state(fn () => [
            'type' => QuestionType::Essay,
            'options' => null,
            'answer_key' => null,
            'score_weight' => 5.0,
        ]);
    }
}

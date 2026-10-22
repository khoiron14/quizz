<?php

namespace App\Repositories\Eloquent;

use App\Models\Question;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class QuestionRepository implements QuestionRepositoryInterface
{
    /**
     * Find a question by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?Question
    {
        return Question::query()
            ->with($with)
            ->find($id);
    }

    /**
     * Get all questions for a given exam ordered by order column.
     *
     * @return Collection<int, Question>
     */
    public function getByExamId(string $examId): Collection
    {
        return Question::query()
            ->where('exam_id', $examId)
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Create a new question for an exam.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Question
    {
        if (! isset($data['order'])) {
            $data['order'] = $this->getNextOrder($data['exam_id']);
        }

        return Question::create($data);
    }

    /**
     * Update an existing question.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Question|string $question, array $data): Question
    {
        $model = $question instanceof Question ? $question : Question::findOrFail($question);

        $model->update($data);

        return $model->fresh();
    }

    /**
     * Delete a question.
     */
    public function delete(Question|string $question): bool
    {
        $model = $question instanceof Question ? $question : Question::findOrFail($question);

        return (bool) $model->delete();
    }

    /**
     * Determine the next order index for a new question in the exam.
     */
    public function getNextOrder(string $examId): int
    {
        $maxOrder = Question::query()
            ->where('exam_id', $examId)
            ->max('order');

        return $maxOrder !== null ? (int) $maxOrder + 1 : 1;
    }

    /**
     * Update order positions for questions in an exam.
     *
     * @param  array<int, string>  $questionIdsInOrder
     */
    public function reorder(string $examId, array $questionIdsInOrder): void
    {
        foreach ($questionIdsInOrder as $index => $questionId) {
            Question::query()
                ->where('exam_id', $examId)
                ->where('id', $questionId)
                ->update(['order' => $index + 1]);
        }
    }
}

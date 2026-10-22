<?php

namespace App\Repositories\Contracts;

use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Question persistence operations.
 */
interface QuestionRepositoryInterface
{
    /**
     * Find a question by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?Question;

    /**
     * Get all questions for a given exam ordered by order column.
     *
     * @return Collection<int, Question>
     */
    public function getByExamId(string $examId): Collection;

    /**
     * Create a new question for an exam.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Question;

    /**
     * Update an existing question.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Question|string $question, array $data): Question;

    /**
     * Delete a question.
     */
    public function delete(Question|string $question): bool;

    /**
     * Determine the next order index for a new question in the exam.
     */
    public function getNextOrder(string $examId): int;

    /**
     * Update order positions for questions in an exam.
     *
     * @param  array<int, string>  $questionIdsInOrder
     */
    public function reorder(string $examId, array $questionIdsInOrder): void;
}

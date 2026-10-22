<?php

namespace App\Repositories\Contracts;

use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for ExamAttempt and StudentAnswer persistence operations.
 */
interface AttemptRepositoryInterface
{
    /**
     * Find an exam attempt by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?ExamAttempt;

    /**
     * Find an active in-progress attempt for a student in an exam.
     */
    public function findActiveAttempt(string $examId, string $studentId): ?ExamAttempt;

    /**
     * Find any attempt (active or submitted) for a student in an exam.
     */
    public function findAttempt(string $examId, string $studentId): ?ExamAttempt;

    /**
     * Get all attempts taken by a specific student.
     *
     * @return Collection<int, ExamAttempt>
     */
    public function getByStudentId(string $studentId): Collection;

    /**
     * Get all attempts for a given exam (for monitoring and grading).
     *
     * @param  array<int, string>  $with
     * @return Collection<int, ExamAttempt>
     */
    public function getByExamId(string $examId, array $with = []): Collection;

    /**
     * Create a new exam attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAttempt(array $data): ExamAttempt;

    /**
     * Update an exam attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAttempt(ExamAttempt|string $attempt, array $data): ExamAttempt;

    /**
     * Atomically save or update a student's answer for a question.
     */
    public function saveAnswer(
        string $attemptId,
        string $questionId,
        ?string $answerContent,
        bool $isFlagged = false,
        ?float $scoreAwarded = null
    ): StudentAnswer;

    /**
     * Get all answers for an attempt.
     *
     * @return Collection<int, StudentAnswer>
     */
    public function getAnswers(string $attemptId): Collection;

    /**
     * Add emergency minutes to an attempt's extra_time_minutes.
     */
    public function extendTime(ExamAttempt|string $attempt, int $minutes): ExamAttempt;

    /**
     * Reset an attempt back to in_progress so the student can re-enter their session.
     */
    public function resetSession(ExamAttempt|string $attempt): ExamAttempt;
}

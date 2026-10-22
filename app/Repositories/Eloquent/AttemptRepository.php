<?php

namespace App\Repositories\Eloquent;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AttemptRepository implements AttemptRepositoryInterface
{
    /**
     * Find an exam attempt by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->with($with)
            ->find($id);
    }

    /**
     * Find an active in-progress attempt for a student in an exam.
     */
    public function findActiveAttempt(string $examId, string $studentId): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->where('status', AttemptStatus::InProgress)
            ->first();
    }

    /**
     * Find any attempt (active or submitted) for a student in an exam.
     */
    public function findAttempt(string $examId, string $studentId): ?ExamAttempt
    {
        return ExamAttempt::query()
            ->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->latest()
            ->first();
    }

    /**
     * Get all attempts taken by a specific student.
     *
     * @return Collection<int, ExamAttempt>
     */
    public function getByStudentId(string $studentId): Collection
    {
        return ExamAttempt::query()
            ->where('student_id', $studentId)
            ->with(['exam'])
            ->latest()
            ->get();
    }

    /**
     * Get all attempts for a given exam (for monitoring and grading).
     *
     * @param  array<int, string>  $with
     * @return Collection<int, ExamAttempt>
     */
    public function getByExamId(string $examId, array $with = []): Collection
    {
        return ExamAttempt::query()
            ->with(array_merge(['student', 'answers'], $with))
            ->where('exam_id', $examId)
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Create a new exam attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAttempt(array $data): ExamAttempt
    {
        return ExamAttempt::create($data);
    }

    /**
     * Update an exam attempt.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAttempt(ExamAttempt|string $attempt, array $data): ExamAttempt
    {
        $model = $attempt instanceof ExamAttempt ? $attempt : ExamAttempt::findOrFail($attempt);

        $model->update($data);

        return $model->fresh();
    }

    /**
     * Atomically save or update a student's answer for a question.
     */
    public function saveAnswer(
        string $attemptId,
        string $questionId,
        ?string $answerContent,
        bool $isFlagged = false,
        ?float $scoreAwarded = null
    ): StudentAnswer {
        $attributes = [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
        ];

        $values = [
            'answer_content' => $answerContent,
            'is_flagged' => $isFlagged,
            'last_synced_at' => now(),
        ];

        if ($scoreAwarded !== null) {
            $values['score_awarded'] = $scoreAwarded;
        }

        return StudentAnswer::updateOrCreate($attributes, $values);
    }

    /**
     * Get all answers for an attempt.
     *
     * @return Collection<int, StudentAnswer>
     */
    public function getAnswers(string $attemptId): Collection
    {
        return StudentAnswer::query()
            ->where('attempt_id', $attemptId)
            ->get();
    }

    /**
     * Add emergency minutes to an attempt's extra_time_minutes.
     */
    public function extendTime(ExamAttempt|string $attempt, int $minutes): ExamAttempt
    {
        $model = $attempt instanceof ExamAttempt ? $attempt : ExamAttempt::findOrFail($attempt);

        $model->increment('extra_time_minutes', $minutes);

        return $model->fresh();
    }

    /**
     * Reset an attempt back to in_progress so the student can re-enter their session.
     */
    public function resetSession(ExamAttempt|string $attempt): ExamAttempt
    {
        $model = $attempt instanceof ExamAttempt ? $attempt : ExamAttempt::findOrFail($attempt);

        $model->update([
            'status' => AttemptStatus::InProgress,
            'submitted_at' => null,
        ]);

        return $model->fresh();
    }
}

<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ExamService
{
    public function __construct(
        protected ExamRepositoryInterface $examRepository,
        protected QuestionRepositoryInterface $questionRepository,
    ) {}

    /**
     * Get all exams authored by a teacher.
     *
     * @return Collection<int, Exam>
     */
    public function getTeacherExams(string $teacherId): Collection
    {
        return $this->examRepository->getByTeacherId($teacherId);
    }

    /**
     * Find an exam by ID.
     *
     * @param  array<int, string>  $with
     */
    public function getExamById(string $examId, array $with = []): ?Exam
    {
        return $this->examRepository->findById($examId, $with);
    }

    /**
     * Create a new exam with an auto-generated or custom unique token.
     *
     * @param  array<string, mixed>  $data
     */
    public function createExam(User $teacher, array $data): Exam
    {
        if (empty($data['token'])) {
            $data['token'] = $this->generateUniqueToken();
        } else {
            $data['token'] = strtoupper($data['token']);
        }

        $data['teacher_id'] = $teacher->id;
        $data['status'] ??= ExamStatus::Draft;

        return $this->examRepository->create($data);
    }

    /**
     * Update an existing exam.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateExam(Exam $exam, array $data): Exam
    {
        $updated = $this->examRepository->update($exam, $data);
        $this->forgetExamCache($updated->id);

        return $updated;
    }

    /**
     * Delete an exam and clear its caches.
     */
    public function deleteExam(Exam $exam): bool
    {
        $this->forgetExamCache($exam->id);

        return $this->examRepository->delete($exam);
    }

    /**
     * Add a question to an exam.
     *
     * @param  array<string, mixed>  $data
     */
    public function addQuestion(Exam $exam, array $data): Question
    {
        $data['exam_id'] = $exam->id;
        $question = $this->questionRepository->create($data);
        $this->forgetExamCache($exam->id);

        return $question;
    }

    /**
     * Update an existing question.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateQuestion(Question $question, array $data): Question
    {
        $updated = $this->questionRepository->update($question, $data);
        $this->forgetExamCache($updated->exam_id);

        return $updated;
    }

    /**
     * Delete a question from an exam.
     */
    public function deleteQuestion(Question $question): bool
    {
        $examId = $question->exam_id;
        $deleted = $this->questionRepository->delete($question);
        $this->forgetExamCache($examId);

        return $deleted;
    }

    /**
     * Reorder questions in an exam.
     *
     * @param  array<int, string>  $questionIdsInOrder
     */
    public function reorderQuestions(Exam $exam, array $questionIdsInOrder): void
    {
        $this->questionRepository->reorder($exam->id, $questionIdsInOrder);
        $this->forgetExamCache($exam->id);
    }

    /**
     * Generate an unambiguous, collision-free 6-character exam token.
     */
    public function generateUniqueToken(): string
    {
        // Avoid confusing characters: 0, O, 1, I
        $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $token = '';
            for ($i = 0; $i < 6; $i++) {
                $token .= $pool[random_int(0, strlen($pool) - 1)];
            }
        } while ($this->examRepository->tokenExists($token));

        return $token;
    }

    /**
     * Clear cached question sets for an exam.
     */
    public function forgetExamCache(string $examId): void
    {
        Cache::forget("cbt:exam:{$examId}:questions:student");
        Cache::forget("cbt:exam:{$examId}:questions:teacher");
    }
}

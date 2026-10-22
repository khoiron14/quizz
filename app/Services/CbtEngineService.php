<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CbtEngineService
{
    public function __construct(
        protected ExamRepositoryInterface $examRepository,
        protected QuestionRepositoryInterface $questionRepository,
        protected AttemptRepositoryInterface $attemptRepository,
    ) {}

    /**
     * Start or resume an exam attempt for a student using an access token.
     *
     * @return array{attempt: ExamAttempt, exam: Exam, questions: array<int, array<string, mixed>>, answers: array<string, mixed>, deadline: string}
     */
    public function startOrResumeExam(string $token, User $student): array
    {
        $exam = $this->examRepository->findActiveByToken($token);

        if (! $exam) {
            throw new RuntimeException('Invalid or expired exam token, or the exam is not currently active.');
        }

        // Check if student already has an attempt
        $existingAttempt = $this->attemptRepository->findAttempt($exam->id, $student->id);

        if ($existingAttempt) {
            if ($existingAttempt->status === AttemptStatus::Submitted) {
                throw new RuntimeException('You have already submitted this exam.');
            }

            if ($existingAttempt->status === AttemptStatus::TimedOut) {
                throw new RuntimeException('This exam attempt has expired.');
            }

            $attempt = $existingAttempt;
        } else {
            // Create fresh attempt
            $attempt = $this->attemptRepository->createAttempt([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'started_at' => now(),
                'status' => AttemptStatus::InProgress,
                'extra_time_minutes' => 0,
            ]);
        }

        // Check deadline
        $deadline = $attempt->getDeadline();
        if (now()->greaterThan($deadline)) {
            $this->attemptRepository->updateAttempt($attempt, [
                'status' => AttemptStatus::TimedOut,
                'submitted_at' => now(),
            ]);

            throw new RuntimeException('The time allocated for this exam has already expired.');
        }

        $questions = $this->getStudentQuestions($exam);
        $answers = $this->getSyncedAnswersMap($attempt->id);

        return [
            'attempt' => $attempt,
            'exam' => $exam,
            'questions' => $questions,
            'answers' => $answers,
            'deadline' => $deadline->toIso8601String(),
        ];
    }

    /**
     * Get high-concurrency cached question set strictly sanitized for student delivery.
     * Guaranteed zero leak of answer_key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStudentQuestions(Exam $exam): array
    {
        $cacheKey = "cbt:exam:{$exam->id}:questions:student";

        return Cache::remember($cacheKey, 3600, function () use ($exam) {
            $questions = $this->questionRepository->getByExamId($exam->id);

            return $questions->map(function (Question $question) {
                return [
                    'id' => $question->id,
                    'type' => $question->type->value,
                    'question_text' => $question->question_text,
                    'options' => $question->options,
                    'score_weight' => $question->score_weight,
                    'order' => $question->order,
                ];
            })->values()->all();
        });
    }

    /**
     * Map previously synced answers indexed by question_id for fast frontend state rehydration.
     *
     * @return array<string, array{answer_content: string|null, is_flagged: bool}>
     */
    public function getSyncedAnswersMap(string $attemptId): array
    {
        $answers = $this->attemptRepository->getAnswers($attemptId);
        $map = [];

        foreach ($answers as $answer) {
            $map[$answer->question_id] = [
                'answer_content' => $answer->answer_content,
                'is_flagged' => (bool) $answer->is_flagged,
            ];
        }

        return $map;
    }

    /**
     * Atomically save student answer in background (idempotent upsert).
     */
    public function saveAnswer(
        string $attemptId,
        string $questionId,
        ?string $answerContent,
        bool $isFlagged = false
    ): StudentAnswer {
        $attempt = $this->attemptRepository->findById($attemptId);

        if (! $attempt || ! $attempt->status->isActive()) {
            throw new RuntimeException('Cannot save answer for an inactive or completed attempt.');
        }

        // Validate time limit
        if (now()->greaterThan($attempt->getDeadline())) {
            $this->attemptRepository->updateAttempt($attempt, [
                'status' => AttemptStatus::TimedOut,
                'submitted_at' => now(),
            ]);

            throw new RuntimeException('Exam deadline has passed. Answers can no longer be recorded.');
        }

        return $this->attemptRepository->saveAnswer(
            $attemptId,
            $questionId,
            $answerContent,
            $isFlagged
        );
    }

    /**
     * Submit an exam attempt and automatically grade all Multiple Choice questions.
     */
    public function submitExam(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status === AttemptStatus::Submitted) {
            return $attempt;
        }

        $exam = $this->examRepository->findById($attempt->exam_id, ['questions']);
        $questions = $exam->questions->keyBy('id');
        $answers = $this->attemptRepository->getAnswers($attempt->id)->keyBy('question_id');

        $totalScore = 0.0;

        foreach ($questions as $questionId => $question) {
            $answer = $answers->get($questionId);
            $studentChoice = $answer?->answer_content;

            if ($question->type === QuestionType::MultipleChoice) {
                $expectedKey = is_array($question->answer_key)
                    ? ($question->answer_key['key'] ?? null)
                    : (string) $question->answer_key;

                $isCorrect = $studentChoice !== null
                    && strtoupper(trim($studentChoice)) === strtoupper(trim((string) $expectedKey));

                $score = $isCorrect ? (float) $question->score_weight : 0.0;
                $totalScore += $score;

                if ($answer) {
                    $answer->update(['score_awarded' => $score]);
                } else {
                    $this->attemptRepository->saveAnswer(
                        attemptId: $attempt->id,
                        questionId: $questionId,
                        answerContent: null,
                        isFlagged: false,
                        scoreAwarded: 0.0
                    );
                }
            } elseif ($question->type === QuestionType::Essay) {
                // If already manually scored, include in total
                if ($answer && $answer->score_awarded !== null) {
                    $totalScore += (float) $answer->score_awarded;
                }
            }
        }

        return $this->attemptRepository->updateAttempt($attempt, [
            'status' => AttemptStatus::Submitted,
            'submitted_at' => now(),
            'total_score' => $totalScore,
        ]);
    }

    /**
     * Manually score an essay student answer and update attempt total score.
     */
    public function gradeEssayAnswer(string $answerId, float $score): StudentAnswer
    {
        $answer = StudentAnswer::with(['attempt'])->findOrFail($answerId);
        $answer->update(['score_awarded' => $score]);

        $this->recalculateAttemptScore($answer->attempt);

        return $answer->fresh();
    }

    /**
     * Recalculate total score for an attempt based on all student answers.
     */
    public function recalculateAttemptScore(ExamAttempt $attempt): void
    {
        $total = (float) StudentAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->whereNotNull('score_awarded')
            ->sum('score_awarded');

        $attempt->update(['total_score' => $total]);
    }

    /**
     * Emergency proctor tool: add extra minutes to an ongoing attempt.
     */
    public function extendTime(string $attemptId, int $minutes): ExamAttempt
    {
        return $this->attemptRepository->extendTime($attemptId, $minutes);
    }

    /**
     * Emergency proctor tool: unlock crashed student session without losing answers.
     */
    public function resetSession(string $attemptId): ExamAttempt
    {
        return $this->attemptRepository->resetSession($attemptId);
    }
}

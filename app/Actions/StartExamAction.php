<?php

namespace App\Actions;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\CbtEngineService;

/**
 * Action to initiate or resume a student's CBT examination session.
 */
class StartExamAction
{
    public function __construct(
        protected CbtEngineService $cbtEngineService,
    ) {}

    /**
     * Start or resume the exam attempt.
     *
     * @return array{attempt: ExamAttempt, exam: Exam, questions: array<int, array<string, mixed>>, answers: array<string, mixed>, deadline: string}
     */
    public function execute(string $token, User $student): array
    {
        return $this->cbtEngineService->startOrResumeExam($token, $student);
    }

    /**
     * @return array{attempt: ExamAttempt, exam: Exam, questions: array<int, array<string, mixed>>, answers: array<string, mixed>, deadline: string}
     */
    public function __invoke(string $token, User $student): array
    {
        return $this->execute($token, $student);
    }
}

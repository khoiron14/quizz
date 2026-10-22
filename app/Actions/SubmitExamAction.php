<?php

namespace App\Actions;

use App\Models\ExamAttempt;
use App\Services\CbtEngineService;

/**
 * Action to finalize an exam attempt and trigger auto-grading.
 */
class SubmitExamAction
{
    public function __construct(
        protected CbtEngineService $cbtEngineService,
    ) {}

    /**
     * Finalize exam attempt and calculate scores.
     */
    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        return $this->cbtEngineService->submitExam($attempt);
    }

    public function __invoke(ExamAttempt $attempt): ExamAttempt
    {
        return $this->execute($attempt);
    }
}

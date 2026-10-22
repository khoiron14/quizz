<?php

namespace App\Actions;

use App\Models\ExamAttempt;
use App\Services\CbtEngineService;

/**
 * Emergency proctor action to unlock a student session after workstation failure.
 */
class ResetStudentSessionAction
{
    public function __construct(
        protected CbtEngineService $cbtEngineService,
    ) {}

    /**
     * Unlock student session and retain previously synced answers.
     */
    public function execute(string $attemptId): ExamAttempt
    {
        return $this->cbtEngineService->resetSession($attemptId);
    }

    public function __invoke(string $attemptId): ExamAttempt
    {
        return $this->execute($attemptId);
    }
}

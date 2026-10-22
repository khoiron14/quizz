<?php

namespace App\Actions;

use App\Models\ExamAttempt;
use App\Services\CbtEngineService;

/**
 * Emergency proctor action to extend time for an active exam attempt.
 */
class ExtendTimeAction
{
    public function __construct(
        protected CbtEngineService $cbtEngineService,
    ) {}

    /**
     * Add emergency minutes to the attempt.
     */
    public function execute(string $attemptId, int $minutes): ExamAttempt
    {
        return $this->cbtEngineService->extendTime($attemptId, $minutes);
    }

    public function __invoke(string $attemptId, int $minutes): ExamAttempt
    {
        return $this->execute($attemptId, $minutes);
    }
}

<?php

namespace App\Actions;

use App\Models\StudentAnswer;
use App\Services\CbtEngineService;

/**
 * Action to atomically upsert a student's answer in background.
 */
class SaveStudentAnswerAction
{
    public function __construct(
        protected CbtEngineService $cbtEngineService,
    ) {}

    /**
     * Atomically save or update answer.
     */
    public function execute(
        string $attemptId,
        string $questionId,
        ?string $answerContent,
        bool $isFlagged = false
    ): StudentAnswer {
        return $this->cbtEngineService->saveAnswer(
            $attemptId,
            $questionId,
            $answerContent,
            $isFlagged
        );
    }

    public function __invoke(
        string $attemptId,
        string $questionId,
        ?string $answerContent,
        bool $isFlagged = false
    ): StudentAnswer {
        return $this->execute($attemptId, $questionId, $answerContent, $isFlagged);
    }
}

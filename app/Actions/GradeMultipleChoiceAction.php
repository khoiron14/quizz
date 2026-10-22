<?php

namespace App\Actions;

use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;

/**
 * Action to evaluate and score all Multiple Choice questions for an exam attempt.
 */
class GradeMultipleChoiceAction
{
    public function __construct(
        protected ExamRepositoryInterface $examRepository,
        protected AttemptRepositoryInterface $attemptRepository,
    ) {}

    /**
     * Grade all multiple choice questions on the attempt and return the total MC score.
     */
    public function execute(ExamAttempt $attempt): float
    {
        $exam = $this->examRepository->findById($attempt->exam_id, ['questions']);
        $questions = $exam->questions->keyBy('id');
        $answers = $this->attemptRepository->getAnswers($attempt->id)->keyBy('question_id');

        $mcTotalScore = 0.0;

        foreach ($questions as $questionId => $question) {
            if ($question->type !== QuestionType::MultipleChoice) {
                continue;
            }

            $answer = $answers->get($questionId);
            $studentChoice = $answer?->answer_content;

            $expectedKey = is_array($question->answer_key)
                ? ($question->answer_key['key'] ?? null)
                : (string) $question->answer_key;

            $isCorrect = $studentChoice !== null
                && strtoupper(trim($studentChoice)) === strtoupper(trim((string) $expectedKey));

            $score = $isCorrect ? (float) $question->score_weight : 0.0;
            $mcTotalScore += $score;

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
        }

        return $mcTotalScore;
    }

    public function __invoke(ExamAttempt $attempt): float
    {
        return $this->execute($attempt);
    }
}

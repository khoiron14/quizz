<?php

namespace App\Http\Controllers;

use App\Actions\SaveStudentAnswerAction;
use App\Actions\StartExamAction;
use App\Actions\SubmitExamAction;
use App\Enums\AttemptStatus;
use App\Http\Requests\Cbt\SaveAnswerRequest;
use App\Http\Requests\Cbt\StartExamRequest;
use App\Http\Requests\Cbt\SubmitExamRequest;
use App\Models\ExamAttempt;
use App\Services\CbtEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StudentExamController extends Controller
{
    /**
     * Display token entry screen or redirect to active in-progress attempt.
     */
    public function showTokenForm(Request $request): Response|RedirectResponse
    {
        $activeAttempt = ExamAttempt::query()
            ->where('student_id', $request->user()->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('started_at')
            ->first();

        if ($activeAttempt && now()->lessThanOrEqualTo($activeAttempt->getDeadline())) {
            return redirect()->route('cbt.exam.workspace', $activeAttempt->id);
        }

        return Inertia::render('Exam/TokenEntry');
    }

    /**
     * Start a new exam or resume active attempt via token.
     */
    public function start(StartExamRequest $request, StartExamAction $startExam): Response|RedirectResponse|JsonResponse
    {
        $token = $request->validated('token');
        $result = $startExam->execute($token, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'attempt_id' => $result['attempt']->id,
                'redirect_url' => route('cbt.exam.workspace', $result['attempt']->id),
            ]);
        }

        return redirect()->route('cbt.exam.workspace', $result['attempt']->id);
    }

    /**
     * Single-page CBT exam workspace.
     */
    public function workspace(Request $request, ExamAttempt $attempt, CbtEngineService $cbtEngine): Response|RedirectResponse
    {
        // Enforce ownership
        if ($attempt->student_id !== $request->user()->id) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Unauthorized access to this exam attempt.');
        }

        // If submitted or expired, redirect to result
        if ($attempt->status === AttemptStatus::Submitted) {
            return redirect()->route('cbt.exam.result', $attempt->id);
        }

        if (now()->greaterThan($attempt->getDeadline())) {
            $attempt->update([
                'status' => AttemptStatus::TimedOut,
                'submitted_at' => now(),
            ]);

            return redirect()->route('cbt.exam.result', $attempt->id);
        }

        $attempt->loadMissing('exam');

        $questions = $cbtEngine->getStudentQuestions($attempt->exam);
        $initialAnswers = $cbtEngine->getSyncedAnswersMap($attempt->id);

        return Inertia::render('Exam/Workspace', [
            'attempt' => [
                'id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'started_at' => $attempt->started_at->toIso8601String(),
                'extra_time_minutes' => $attempt->extra_time_minutes,
                'deadline' => $attempt->getDeadline()->toIso8601String(),
            ],
            'exam' => [
                'id' => $attempt->exam->id,
                'title' => $attempt->exam->title,
                'duration_minutes' => $attempt->exam->duration_minutes,
                'is_randomize' => $attempt->exam->is_randomize,
            ],
            'questions' => $questions,
            'initialAnswers' => $initialAnswers,
        ]);
    }

    /**
     * Background debounced sync endpoint for student answer (Atomic Upsert).
     */
    public function saveAnswer(
        SaveAnswerRequest $request,
        ExamAttempt $attempt,
        SaveStudentAnswerAction $saveAnswer
    ): JsonResponse {
        if ($attempt->student_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], HttpResponse::HTTP_FORBIDDEN);
        }

        $answer = $saveAnswer->execute(
            attemptId: $attempt->id,
            questionId: $request->validated('question_id'),
            answerContent: $request->validated('answer_content'),
            isFlagged: (bool) $request->validated('is_flagged', false)
        );

        return response()->json([
            'success' => true,
            'last_synced_at' => $answer->last_synced_at?->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    /**
     * Final submission of exam attempt.
     */
    public function submit(
        SubmitExamRequest $request,
        ExamAttempt $attempt,
        SubmitExamAction $submitExam
    ): RedirectResponse|JsonResponse {
        if ($attempt->student_id !== $request->user()->id) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Unauthorized access to this exam attempt.');
        }

        $submitted = $submitExam->execute($attempt);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect_url' => route('cbt.exam.result', $submitted->id),
            ]);
        }

        return redirect()->route('cbt.exam.result', $submitted->id);
    }

    /**
     * Show completion / score summary view.
     */
    public function result(Request $request, ExamAttempt $attempt): Response
    {
        if ($attempt->student_id !== $request->user()->id && ! $request->user()->can('exams.monitor')) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Unauthorized access to this exam attempt.');
        }

        $attempt->load(['exam', 'answers.question']);

        return Inertia::render('Exam/Result', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'total_score' => $attempt->total_score,
            ],
            'exam' => [
                'id' => $attempt->exam->id,
                'title' => $attempt->exam->title,
            ],
            'answersCount' => $attempt->answers->count(),
        ]);
    }
}

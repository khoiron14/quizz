<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\ExtendTimeAction;
use App\Actions\ResetStudentSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proctor\ExtendTimeRequest;
use App\Http\Requests\Proctor\ResetSessionRequest;
use App\Http\Requests\Teacher\GradeEssayRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Services\CbtEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExamMonitoringController extends Controller
{
    /**
     * Display the live student monitoring dashboard for an exam.
     */
    public function index(Request $request, Exam $exam, AttemptRepositoryInterface $attemptRepository): Response
    {
        $this->authorizeExamTeacher($request, $exam);

        $attempts = $attemptRepository->getByExamId($exam->id, ['student', 'answers']);

        return Inertia::render('Teacher/Exams/Monitoring', [
            'exam' => $exam,
            'attempts' => $attempts->map(function (ExamAttempt $attempt) {
                return [
                    'id' => $attempt->id,
                    'student' => [
                        'id' => $attempt->student->id,
                        'name' => $attempt->student->name,
                        'email' => $attempt->student->email,
                    ],
                    'started_at' => $attempt->started_at->toIso8601String(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'extra_time_minutes' => $attempt->extra_time_minutes,
                    'deadline' => $attempt->getDeadline()->toIso8601String(),
                    'status' => $attempt->status->value,
                    'total_score' => $attempt->total_score,
                    'answers_count' => $attempt->answers->count(),
                ];
            }),
        ]);
    }

    /**
     * JSON polling endpoint for live monitoring updates.
     */
    public function status(Request $request, Exam $exam, AttemptRepositoryInterface $attemptRepository): JsonResponse
    {
        $this->authorizeExamTeacher($request, $exam);

        $attempts = $attemptRepository->getByExamId($exam->id, ['student', 'answers']);

        return response()->json([
            'attempts' => $attempts->map(fn (ExamAttempt $a) => [
                'id' => $a->id,
                'student_name' => $a->student->name,
                'status' => $a->status->value,
                'deadline' => $a->getDeadline()->toIso8601String(),
                'extra_time_minutes' => $a->extra_time_minutes,
                'total_score' => $a->total_score,
                'answers_count' => $a->answers->count(),
            ]),
        ]);
    }

    /**
     * Emergency proctor tool: add minutes to a student's attempt.
     */
    public function extendTime(
        ExtendTimeRequest $request,
        ExamAttempt $attempt,
        ExtendTimeAction $extendTime
    ): JsonResponse|RedirectResponse {
        $minutes = (int) $request->validated('minutes');
        $updated = $extendTime->execute($attempt->id, $minutes);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'extra_time_minutes' => $updated->extra_time_minutes,
                'deadline' => $updated->getDeadline()->toIso8601String(),
            ]);
        }

        return back()->with('success', "Added {$minutes} minutes to student's exam time.");
    }

    /**
     * Emergency proctor tool: unlock crashed student session without losing answers.
     */
    public function resetSession(
        ResetSessionRequest $request,
        ExamAttempt $attempt,
        ResetStudentSessionAction $resetSession
    ): JsonResponse|RedirectResponse {
        $resetSession->execute($attempt->id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Student session unlocked successfully.');
    }

    /**
     * Manual grading view for essay answers.
     */
    public function grading(Request $request, Exam $exam, AttemptRepositoryInterface $attemptRepository): Response
    {
        $this->authorizeExamTeacher($request, $exam);

        $exam->load('questions');
        $attempts = $attemptRepository->getByExamId($exam->id, ['student', 'answers.question']);

        return Inertia::render('Teacher/Exams/Grading', [
            'exam' => $exam,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Score a single student essay answer.
     */
    public function gradeEssay(
        GradeEssayRequest $request,
        StudentAnswer $answer,
        CbtEngineService $cbtEngine
    ): JsonResponse|RedirectResponse {
        $score = (float) $request->validated('score_awarded');
        $cbtEngine->gradeEssayAnswer($answer->id, $score);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Essay answer scored successfully.');
    }

    /**
     * Helper to verify exam ownership.
     */
    protected function authorizeExamTeacher(Request $request, Exam $exam): void
    {
        if ($exam->teacher_id !== $request->user()->id && ! $request->user()->hasRole('admin')) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'You do not own this exam.');
        }
    }
}

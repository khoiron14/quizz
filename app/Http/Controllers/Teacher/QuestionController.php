<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReorderQuestionsRequest;
use App\Http\Requests\Teacher\StoreQuestionRequest;
use App\Http\Requests\Teacher\UpdateQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QuestionController extends Controller
{
    /**
     * Store a newly created question for an exam.
     */
    public function store(StoreQuestionRequest $request, Exam $exam, ExamService $examService): RedirectResponse
    {
        $this->authorizeExamTeacher($request, $exam);

        $examService->addQuestion($exam, $request->validated());

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Question added successfully.');
    }

    /**
     * Update the specified question.
     */
    public function update(
        UpdateQuestionRequest $request,
        Exam $exam,
        Question $question,
        ExamService $examService
    ): RedirectResponse {
        $this->authorizeExamTeacher($request, $exam);

        if ($question->exam_id !== $exam->id) {
            abort(HttpResponse::HTTP_NOT_FOUND, 'Question does not belong to this exam.');
        }

        $examService->updateQuestion($question, $request->validated());

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Question updated successfully.');
    }

    /**
     * Remove the specified question from the exam.
     */
    public function destroy(
        Request $request,
        Exam $exam,
        Question $question,
        ExamService $examService
    ): RedirectResponse {
        $this->authorizeExamTeacher($request, $exam);

        if ($question->exam_id !== $exam->id) {
            abort(HttpResponse::HTTP_NOT_FOUND, 'Question does not belong to this exam.');
        }

        $examService->deleteQuestion($question);

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * Reorder questions in an exam.
     */
    public function reorder(
        ReorderQuestionsRequest $request,
        Exam $exam,
        ExamService $examService
    ): JsonResponse|RedirectResponse {
        $this->authorizeExamTeacher($request, $exam);

        $examService->reorderQuestions($exam, $request->validated('question_ids'));

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Questions reordered successfully.');
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

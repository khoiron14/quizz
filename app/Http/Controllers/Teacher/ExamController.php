<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreExamRequest;
use App\Http\Requests\Teacher\UpdateExamRequest;
use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExamController extends Controller
{
    /**
     * Display a listing of exams authored by the authenticated teacher.
     */
    public function index(Request $request, ExamService $examService): Response
    {
        $exams = $examService->getTeacherExams($request->user()->id);

        return Inertia::render('Teacher/Exams/Index', [
            'exams' => $exams,
        ]);
    }

    /**
     * Show the form for creating a new exam.
     */
    public function create(ExamService $examService): Response
    {
        return Inertia::render('Teacher/Exams/Create', [
            'suggestedToken' => $examService->generateUniqueToken(),
        ]);
    }

    /**
     * Store a newly created exam in storage.
     */
    public function store(StoreExamRequest $request, ExamService $examService): RedirectResponse
    {
        $exam = $examService->createExam($request->user(), $request->validated());

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Exam created successfully.');
    }

    /**
     * Display the specified exam with its questions.
     */
    public function show(Request $request, Exam $exam): Response
    {
        $this->authorizeExamTeacher($request, $exam);

        $exam->load(['questions']);

        return Inertia::render('Teacher/Exams/Show', [
            'exam' => $exam,
            'questions' => $exam->questions,
        ]);
    }

    /**
     * Show the form for editing the specified exam.
     */
    public function edit(Request $request, Exam $exam): Response
    {
        $this->authorizeExamTeacher($request, $exam);

        return Inertia::render('Teacher/Exams/Edit', [
            'exam' => $exam,
        ]);
    }

    /**
     * Update the specified exam in storage.
     */
    public function update(UpdateExamRequest $request, Exam $exam, ExamService $examService): RedirectResponse
    {
        $this->authorizeExamTeacher($request, $exam);

        $examService->updateExam($exam, $request->validated());

        return redirect()->route('teacher.exams.show', $exam->id)
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified exam from storage.
     */
    public function destroy(Request $request, Exam $exam, ExamService $examService): RedirectResponse
    {
        $this->authorizeExamTeacher($request, $exam);

        $examService->deleteExam($exam);

        return redirect()->route('teacher.exams.index')
            ->with('success', 'Exam deleted successfully.');
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

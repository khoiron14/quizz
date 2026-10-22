<?php

use App\Http\Controllers\StudentExamController;
use App\Http\Controllers\Teacher\ExamController;
use App\Http\Controllers\Teacher\ExamMonitoringController;
use App\Http\Controllers\Teacher\QuestionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // ----------------------------------------------------
    // Student CBT Exam Taking Routes (permission: exams.take)
    // ----------------------------------------------------
    Route::middleware('permission:exams.take')->group(function () {
        Route::get('/exam/token', [StudentExamController::class, 'showTokenForm'])->name('cbt.exam.token');
        Route::post('/exam/start', [StudentExamController::class, 'start'])->name('cbt.exam.start');
        Route::get('/exam/{attempt}', [StudentExamController::class, 'workspace'])->name('cbt.exam.workspace');
        Route::post('/api/exams/{attempt}/answers', [StudentExamController::class, 'saveAnswer'])->name('cbt.exam.save-answer');
        Route::post('/exam/{attempt}/submit', [StudentExamController::class, 'submit'])->name('cbt.exam.submit');
        Route::get('/exam/{attempt}/result', [StudentExamController::class, 'result'])->name('cbt.exam.result');
    });

    // ----------------------------------------------------
    // Teacher Exam Management & Authoring
    // ----------------------------------------------------
    Route::prefix('teacher')->name('teacher.')->middleware('role:teacher|admin')->group(function () {
        // Exams CRUD
        Route::get('/exams', [ExamController::class, 'index'])->name('exams.index')->middleware('permission:exams.view');
        Route::get('/exams/create', [ExamController::class, 'create'])->name('exams.create')->middleware('permission:exams.create');
        Route::post('/exams', [ExamController::class, 'store'])->name('exams.store')->middleware('permission:exams.create');
        Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show')->middleware('permission:exams.view');
        Route::get('/exams/{exam}/edit', [ExamController::class, 'edit'])->name('exams.edit')->middleware('permission:exams.edit');
        Route::put('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update')->middleware('permission:exams.edit');
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy')->middleware('permission:exams.delete');

        // Questions Management
        Route::post('/exams/{exam}/questions', [QuestionController::class, 'store'])->name('questions.store')->middleware('permission:exams.create');
        Route::put('/exams/{exam}/questions/{question}', [QuestionController::class, 'update'])->name('questions.update')->middleware('permission:exams.edit');
        Route::delete('/exams/{exam}/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy')->middleware('permission:exams.delete');
        Route::post('/exams/{exam}/questions/reorder', [QuestionController::class, 'reorder'])->name('questions.reorder')->middleware('permission:exams.edit');

        // Monitoring & Proctor Emergency Tools
        Route::get('/exams/{exam}/monitoring', [ExamMonitoringController::class, 'index'])->name('exams.monitor')->middleware('permission:exams.monitor');
        Route::get('/exams/{exam}/monitoring/status', [ExamMonitoringController::class, 'status'])->name('exams.monitor.status')->middleware('permission:exams.monitor');
        Route::post('/attempts/{attempt}/extend-time', [ExamMonitoringController::class, 'extendTime'])->name('attempts.extend-time')->middleware('permission:exams.extend_time');
        Route::post('/attempts/{attempt}/reset-session', [ExamMonitoringController::class, 'resetSession'])->name('attempts.reset-session')->middleware('permission:exams.reset_session');

        // Manual Essay Grading
        Route::get('/exams/{exam}/grading', [ExamMonitoringController::class, 'grading'])->name('exams.grading')->middleware('permission:exams.grade');
        Route::post('/answers/{answer}/grade', [ExamMonitoringController::class, 'gradeEssay'])->name('answers.grade')->middleware('permission:exams.grade');
    });
});

require __DIR__.'/settings.php';

<?php

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('student sees token entry view and can initiate exam', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'BIO123',
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(5),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    // 1. Visit token entry page
    $this->actingAs($student)
        ->get('/exam/token')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Exam/TokenEntry'));

    // 2. Submit token
    $response = $this->actingAs($student)
        ->post('/exam/start', ['token' => 'BIO123']);

    $attempt = ExamAttempt::where('exam_id', $exam->id)
        ->where('student_id', $student->id)
        ->first();

    expect($attempt)->not->toBeNull();
    $response->assertRedirect("/exam/{$attempt->id}");
});

test('student renders workspace view with sanitized questions and initial answers', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $q1 = Question::factory()->multipleChoice('C')->create([
        'exam_id' => $exam->id,
        'order' => 1,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'started_at' => now(),
        'status' => AttemptStatus::InProgress,
    ]);

    $this->actingAs($student)
        ->get("/exam/{$attempt->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exam/Workspace')
            ->has('attempt')
            ->has('exam')
            ->has('questions', 1)
            ->has('initialAnswers')
            ->where('questions.0.id', $q1->id)
            ->missing('questions.0.answer_key') // Zero leak of answer key
        );
});

test('student submitting exam is redirected to result view', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'started_at' => now(),
        'status' => AttemptStatus::InProgress,
    ]);

    // Submit exam
    $this->actingAs($student)
        ->post("/exam/{$attempt->id}/submit")
        ->assertRedirect("/exam/{$attempt->id}/result");

    // Visit result view
    $this->actingAs($student)
        ->get("/exam/{$attempt->id}/result")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exam/Result')
            ->has('attempt')
            ->has('exam')
        );
});

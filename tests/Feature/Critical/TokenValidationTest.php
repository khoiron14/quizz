<?php

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('token validation requires exact 6 characters', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    // Too short
    $this->actingAs($student)
        ->postJson('/exam/start', ['token' => 'ABC'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);

    // Too long
    $this->actingAs($student)
        ->postJson('/exam/start', ['token' => 'TOOLONGTOKEN'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('token validation automatically handles case-insensitivity', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'ABC123',
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    // Student submits lowercase token
    $response = $this->actingAs($student)
        ->postJson('/exam/start', ['token' => 'abc123']);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(ExamAttempt::where('exam_id', $exam->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

test('draft exam token is rejected', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'DRAFT1',
        'status' => ExamStatus::Draft,
    ]);

    $this->actingAs($student)
        ->post('/exam/start', ['token' => 'DRAFT1'])
        ->assertSessionHasErrors();
});

test('exam outside schedule window is rejected', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    // Future exam (not started yet)
    Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'FUTURE',
        'start_time' => now()->addHours(2),
        'end_time' => now()->addHours(4),
        'status' => ExamStatus::Published,
    ]);

    $this->actingAs($student)
        ->post('/exam/start', ['token' => 'FUTURE'])
        ->assertSessionHasErrors();

    // Past exam (already ended)
    Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'PASSED',
        'start_time' => now()->subHours(4),
        'end_time' => now()->subHours(2),
        'status' => ExamStatus::Published,
    ]);

    $this->actingAs($student)
        ->post('/exam/start', ['token' => 'PASSED'])
        ->assertSessionHasErrors();
});

test('student cannot restart already submitted exam', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'SUBMIT',
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    ExamAttempt::factory()->submitted()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
    ]);

    $this->actingAs($student)
        ->post('/exam/start', ['token' => 'SUBMIT'])
        ->assertSessionHasErrors();
});

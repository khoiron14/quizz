<?php

use App\Models\Exam;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('unauthenticated users are redirected from protected CBT routes', function () {
    $this->get('/exam/token')->assertRedirect('/login');
    $this->post('/exam/start', ['token' => 'TEST12'])->assertRedirect('/login');
    $this->get('/teacher/exams')->assertRedirect('/login');
});

test('students cannot access teacher endpoints or perform teacher actions', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $exam = Exam::factory()->create(['teacher_id' => $teacher->id]);

    // Forbidden from viewing teacher exams list
    $this->actingAs($student)
        ->get('/teacher/exams')
        ->assertForbidden();

    // Forbidden from creating exams
    $this->actingAs($student)
        ->post('/teacher/exams', [
            'title' => 'Unauthorized Exam',
            'duration_minutes' => 60,
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addHours(2)->toDateTimeString(),
        ])
        ->assertForbidden();

    // Forbidden from adding questions
    $this->actingAs($student)
        ->post("/teacher/exams/{$exam->id}/questions", [
            'type' => 'essay',
            'question_text' => 'Unauthorized Question',
        ])
        ->assertForbidden();

    // Forbidden from accessing live monitoring board
    $this->actingAs($student)
        ->get("/teacher/exams/{$exam->id}/monitoring")
        ->assertForbidden();

    // Forbidden from essay grading view
    $this->actingAs($student)
        ->get("/teacher/exams/{$exam->id}/grading")
        ->assertForbidden();
});

test('teachers without exams.take permission cannot access student exam flow', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    // Forbidden from token entry
    $this->actingAs($teacher)
        ->get('/exam/token')
        ->assertForbidden();

    // Forbidden from starting exam
    $this->actingAs($teacher)
        ->postJson('/exam/start', ['token' => 'SOME99'])
        ->assertForbidden();
});

test('teachers can author exams and students can take exams when permissions match', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    // Teacher can view exams index
    $this->actingAs($teacher)
        ->get('/teacher/exams')
        ->assertOk();

    // Student can view token entry
    $this->actingAs($student)
        ->get('/exam/token')
        ->assertOk();
});

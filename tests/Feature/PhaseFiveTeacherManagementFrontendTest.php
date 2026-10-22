<?php

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('teacher can view exams list, create, edit, show, monitoring, and grading views', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'title' => 'Biology Semester 1',
        'status' => ExamStatus::Published,
    ]);

    Question::factory()->multipleChoice('A')->create([
        'exam_id' => $exam->id,
    ]);

    // 1. Index View
    $this->actingAs($teacher)
        ->get('/teacher/exams')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Index')
            ->has('exams', 1)
            ->where('exams.0.id', $exam->id)
        );

    // 2. Create View
    $this->actingAs($teacher)
        ->get('/teacher/exams/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Create')
            ->has('suggestedToken')
        );

    // 3. Show / Questions Authoring View
    $this->actingAs($teacher)
        ->get("/teacher/exams/{$exam->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Show')
            ->has('exam')
            ->has('questions', 1)
        );

    // 4. Edit View
    $this->actingAs($teacher)
        ->get("/teacher/exams/{$exam->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Edit')
            ->has('exam')
        );

    // 5. Live Monitoring View
    $this->actingAs($teacher)
        ->get("/teacher/exams/{$exam->id}/monitoring")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Monitoring')
            ->has('exam')
            ->has('attempts')
        );

    // 6. Essay Grading View
    $this->actingAs($teacher)
        ->get("/teacher/exams/{$exam->id}/grading")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Exams/Grading')
            ->has('exam')
            ->has('attempts')
        );
});

test('teacher cannot access exams owned by another teacher', function () {
    $teacherA = User::factory()->create();
    $teacherA->assignRole('teacher');

    $teacherB = User::factory()->create();
    $teacherB->assignRole('teacher');

    $examA = Exam::factory()->create([
        'teacher_id' => $teacherA->id,
    ]);

    // Teacher B attempts to view Teacher A's exam -> 403 Forbidden
    $this->actingAs($teacherB)
        ->get("/teacher/exams/{$examA->id}")
        ->assertForbidden();

    $this->actingAs($teacherB)
        ->get("/teacher/exams/{$examA->id}/edit")
        ->assertForbidden();

    $this->actingAs($teacherB)
        ->get("/teacher/exams/{$examA->id}/monitoring")
        ->assertForbidden();
});

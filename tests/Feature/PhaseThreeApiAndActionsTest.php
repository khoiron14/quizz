<?php

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('student cannot access teacher routes and teacher cannot take exams without permission', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    // Student trying to view teacher exams -> 403 Forbidden
    $this->actingAs($student)
        ->get('/teacher/exams')
        ->assertForbidden();

    // Teacher trying to take exam directly -> 403 Forbidden (unless granted exams.take)
    $this->actingAs($teacher)
        ->get('/exam/token')
        ->assertForbidden();

    // Student CAN view exam token page
    $this->actingAs($student)
        ->get('/exam/token')
        ->assertOk();
});

test('teacher can create exam, add questions, update, and reorder via endpoints', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    // 1. Create Exam
    $response = $this->actingAs($teacher)
        ->post('/teacher/exams', [
            'title' => 'Chemistry 101',
            'duration_minutes' => 60,
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addHours(2)->toDateTimeString(),
            'is_randomize' => false,
            'status' => 'published',
        ]);

    $response->assertRedirect();
    $exam = Exam::where('title', 'Chemistry 101')->first();
    expect($exam)->not->toBeNull()
        ->and($exam->token)->toHaveLength(6);

    // 2. Add Multiple Choice Question
    $qResponse = $this->actingAs($teacher)
        ->post("/teacher/exams/{$exam->id}/questions", [
            'type' => 'multiple_choice',
            'question_text' => 'What is H2O?',
            'options' => [
                ['key' => 'A', 'text' => 'Water'],
                ['key' => 'B', 'text' => 'Hydrogen peroxide'],
            ],
            'answer_key' => 'A',
            'score_weight' => 5.0,
        ]);

    $qResponse->assertRedirect();
    expect($exam->questions()->count())->toBe(1);

    // 3. Add Essay Question
    $this->actingAs($teacher)
        ->post("/teacher/exams/{$exam->id}/questions", [
            'type' => 'essay',
            'question_text' => 'Explain covalent bonding.',
            'score_weight' => 10.0,
        ])
        ->assertRedirect();

    expect($exam->questions()->count())->toBe(2);

    $questions = $exam->questions()->get();
    $q1 = $questions[0];
    $q2 = $questions[1];

    // 4. Reorder Questions
    $this->actingAs($teacher)
        ->postJson("/teacher/exams/{$exam->id}/questions/reorder", [
            'question_ids' => [$q2->id, $q1->id],
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($q2->fresh()->order)->toBe(1)
        ->and($q1->fresh()->order)->toBe(2);
});

test('student starts exam via token, saves answers via api, and submits with auto-grading', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'token' => 'MATH12',
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(5),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $q1 = Question::factory()->multipleChoice('A')->create([
        'exam_id' => $exam->id,
        'score_weight' => 10.0,
    ]);

    // 1. Start Exam via POST /exam/start
    $startResponse = $this->actingAs($student)
        ->postJson('/exam/start', ['token' => 'MATH12']);

    $startResponse->assertOk()
        ->assertJsonStructure(['success', 'attempt_id', 'redirect_url']);

    $attemptId = $startResponse->json('attempt_id');
    $attempt = ExamAttempt::find($attemptId);
    expect($attempt)->not->toBeNull()
        ->and($attempt->status)->toBe(AttemptStatus::InProgress);

    // 2. Save Answer via POST /api/exams/{attempt}/answers (Background Atomic Sync)
    $saveResponse = $this->actingAs($student)
        ->postJson("/api/exams/{$attempt->id}/answers", [
            'question_id' => $q1->id,
            'answer_content' => 'A',
            'is_flagged' => true,
        ]);

    $saveResponse->assertOk()
        ->assertJsonStructure(['success', 'last_synced_at']);

    $savedAnswer = StudentAnswer::where('attempt_id', $attempt->id)
        ->where('question_id', $q1->id)
        ->first();

    expect($savedAnswer)->not->toBeNull()
        ->and($savedAnswer->answer_content)->toBe('A')
        ->and($savedAnswer->is_flagged)->toBeTrue();

    // 3. Submit Exam via POST /exam/{attempt}/submit
    $submitResponse = $this->actingAs($student)
        ->postJson("/exam/{$attempt->id}/submit");

    $submitResponse->assertOk()
        ->assertJson(['success' => true]);

    $freshAttempt = $attempt->fresh();
    expect($freshAttempt->status)->toBe(AttemptStatus::Submitted)
        ->and($freshAttempt->total_score)->toBe(10.0);
});

test('proctor can extend time, reset student session, and manually grade essay', function () {
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

    $essayQ = Question::factory()->essay()->create([
        'exam_id' => $exam->id,
        'score_weight' => 20.0,
    ]);

    $attempt = ExamAttempt::factory()->submitted(0.0)->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
    ]);

    $answer = StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $essayQ->id,
        'answer_content' => 'This is a well written essay response.',
    ]);

    // 1. Extend Time
    $this->actingAs($teacher)
        ->postJson("/teacher/attempts/{$attempt->id}/extend-time", [
            'minutes' => 20,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'extra_time_minutes' => 20,
        ]);

    expect($attempt->fresh()->extra_time_minutes)->toBe(20);

    // 2. Reset Session
    $this->actingAs($teacher)
        ->postJson("/teacher/attempts/{$attempt->id}/reset-session")
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($attempt->fresh()->status)->toBe(AttemptStatus::InProgress);

    // 3. Manually Grade Essay
    $this->actingAs($teacher)
        ->postJson("/teacher/answers/{$answer->id}/grade", [
            'score_awarded' => 18.5,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($answer->fresh()->score_awarded)->toBe(18.5)
        ->and($attempt->fresh()->total_score)->toBe(18.5);
});

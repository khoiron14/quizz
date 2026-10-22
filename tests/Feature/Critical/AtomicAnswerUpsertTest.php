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

test('answers are saved atomically without duplicate rows upon rapid updates', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(5),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $question = Question::factory()->multipleChoice('C')->create([
        'exam_id' => $exam->id,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'status' => AttemptStatus::InProgress,
    ]);

    // 1. Initial choice: 'A'
    $this->actingAs($student)
        ->postJson("/api/exams/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'answer_content' => 'A',
            'is_flagged' => false,
        ])
        ->assertOk();

    expect(StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->count())->toBe(1);

    // 2. Rapid revision: 'B'
    $this->actingAs($student)
        ->postJson("/api/exams/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'answer_content' => 'B',
            'is_flagged' => false,
        ])
        ->assertOk();

    expect(StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->count())->toBe(1);

    // 3. Mark flagged without changing choice: 'B', flagged = true
    $this->actingAs($student)
        ->postJson("/api/exams/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'answer_content' => 'B',
            'is_flagged' => true,
        ])
        ->assertOk();

    $answer = StudentAnswer::where('attempt_id', $attempt->id)
        ->where('question_id', $question->id)
        ->first();

    expect($answer->answer_content)->toBe('B')
        ->and($answer->is_flagged)->toBeTrue()
        ->and($answer->last_synced_at)->not->toBeNull();
});

test('student cannot save answer to another students attempt', function () {
    $teacher = User::factory()->create();
    $studentA = User::factory()->create();
    $studentA->assignRole('student');

    $studentB = User::factory()->create();
    $studentB->assignRole('student');

    $exam = Exam::factory()->create(['teacher_id' => $teacher->id]);
    $question = Question::factory()->create(['exam_id' => $exam->id]);

    $attemptA = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $studentA->id,
    ]);

    // Student B attempts to save answer to Student A's attempt -> 403 Forbidden
    $this->actingAs($studentB)
        ->postJson("/api/exams/{$attemptA->id}/answers", [
            'question_id' => $question->id,
            'answer_content' => 'C',
        ])
        ->assertForbidden();
});

test('saving answer is rejected when deadline has expired', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 30,
        'start_time' => now()->subHours(2),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $question = Question::factory()->create(['exam_id' => $exam->id]);

    // Attempt started 50 minutes ago with 30 minute duration -> already expired
    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'started_at' => now()->subMinutes(50),
        'extra_time_minutes' => 0,
        'status' => AttemptStatus::InProgress,
    ]);

    $this->actingAs($student)
        ->postJson("/api/exams/{$attempt->id}/answers", [
            'question_id' => $question->id,
            'answer_content' => 'A',
        ])
        ->assertServerError(); // RuntimeException thrown for expired deadline

    expect($attempt->fresh()->status)->toBe(AttemptStatus::TimedOut);
});

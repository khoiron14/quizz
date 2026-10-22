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

test('proctor time extension adds extra minutes and recalculates deadline', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHours(2),
        'status' => ExamStatus::Published,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'started_at' => now(),
        'extra_time_minutes' => 0,
        'status' => AttemptStatus::InProgress,
    ]);

    $initialDeadline = $attempt->getDeadline();

    // Proctor adds 25 minutes
    $response = $this->actingAs($teacher)
        ->postJson("/teacher/attempts/{$attempt->id}/extend-time", [
            'minutes' => 25,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'extra_time_minutes' => 25,
        ]);

    $attempt->refresh();
    expect($attempt->extra_time_minutes)->toBe(25);

    $newDeadline = $attempt->getDeadline();
    expect($newDeadline->greaterThan($initialDeadline))->toBeTrue();
});

test('proctor reset session unlocks crashed workstation and preserves all student answers', function () {
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

    $q1 = Question::factory()->multipleChoice('A')->create(['exam_id' => $exam->id]);
    $q2 = Question::factory()->essay()->create(['exam_id' => $exam->id]);

    // Student has completed partial work then workstation crashed or accidentally timed out
    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'status' => AttemptStatus::TimedOut,
        'submitted_at' => now(),
    ]);

    // Previously saved answers
    StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer_content' => 'A',
    ]);

    StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q2->id,
        'answer_content' => 'Comprehensive essay drafted before laptop crash.',
    ]);

    // Proctor unlocks student session
    $response = $this->actingAs($teacher)
        ->postJson("/teacher/attempts/{$attempt->id}/reset-session");

    $response->assertOk()
        ->assertJson(['success' => true]);

    $attempt->refresh();

    // Verify session unlocked
    expect($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->submitted_at)->toBeNull();

    // Verify ZERO data loss: all answers remain intact!
    $answers = StudentAnswer::where('attempt_id', $attempt->id)->get();
    expect($answers)->toHaveCount(2)
        ->and($answers->firstWhere('question_id', $q1->id)->answer_content)->toBe('A')
        ->and($answers->firstWhere('question_id', $q2->id)->answer_content)->toBe('Comprehensive essay drafted before laptop crash.');
});

test('manual essay grading updates answer score and recalculates attempt total score', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'status' => ExamStatus::Published,
    ]);

    $essayQ = Question::factory()->essay()->create([
        'exam_id' => $exam->id,
        'score_weight' => 25.0,
    ]);

    $attempt = ExamAttempt::factory()->submitted(10.0)->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
    ]);

    $answer = StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $essayQ->id,
        'answer_content' => 'Essay explaining photosynthesis process.',
        'score_awarded' => null,
    ]);

    // Teacher scores essay answer with 22.5 points
    $response = $this->actingAs($teacher)
        ->postJson("/teacher/answers/{$answer->id}/grade", [
            'score_awarded' => 22.5,
        ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect($answer->fresh()->score_awarded)->toBe(22.5)
        ->and($attempt->fresh()->total_score)->toBe(22.5);
});

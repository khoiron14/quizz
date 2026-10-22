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

test('auto-grading correctly scores multiple choice answers and handles partial completion', function () {
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

    // Create 4 questions with custom weights
    $q1 = Question::factory()->multipleChoice('A')->create([
        'exam_id' => $exam->id,
        'score_weight' => 5.0,
        'order' => 1,
    ]);

    $q2 = Question::factory()->multipleChoice('B')->create([
        'exam_id' => $exam->id,
        'score_weight' => 10.0,
        'order' => 2,
    ]);

    $q3 = Question::factory()->multipleChoice('C')->create([
        'exam_id' => $exam->id,
        'score_weight' => 15.0,
        'order' => 3,
    ]);

    $q4 = Question::factory()->multipleChoice('D')->create([
        'exam_id' => $exam->id,
        'score_weight' => 20.0,
        'order' => 4,
    ]);

    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'started_at' => now(),
        'status' => AttemptStatus::InProgress,
    ]);

    // Student Answers:
    // Q1: 'A' -> correct (5.0)
    StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q1->id,
        'answer_content' => 'A',
    ]);

    // Q2: 'C' -> incorrect (0.0)
    StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q2->id,
        'answer_content' => 'C',
    ]);

    // Q3: 'c' -> lowercase match, correct (15.0)
    StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $q3->id,
        'answer_content' => 'c',
    ]);

    // Q4: unanswered (0.0)

    // Submit Exam
    $response = $this->actingAs($student)
        ->postJson("/exam/{$attempt->id}/submit");

    $response->assertOk()
        ->assertJson(['success' => true]);

    $attempt->refresh();

    // Verify Attempt State
    expect($attempt->status)->toBe(AttemptStatus::Submitted)
        ->and($attempt->submitted_at)->not->toBeNull()
        ->and($attempt->total_score)->toBe(20.0); // 5 + 0 + 15 + 0 = 20

    // Verify Individual Scores
    $ans1 = StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $q1->id)->first();
    $ans2 = StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $q2->id)->first();
    $ans3 = StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $q3->id)->first();
    $ans4 = StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $q4->id)->first();

    expect($ans1->score_awarded)->toBe(5.0)
        ->and($ans2->score_awarded)->toBe(0.0)
        ->and($ans3->score_awarded)->toBe(15.0)
        ->and($ans4->score_awarded)->toBe(0.0);
});

<?php

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Repositories\Contracts\AttemptRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\QuestionRepositoryInterface;
use App\Repositories\Eloquent\AttemptRepository;
use App\Repositories\Eloquent\ExamRepository;
use App\Repositories\Eloquent\QuestionRepository;
use App\Services\CbtEngineService;
use App\Services\ExamService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('service container resolves repository contracts to eloquent implementations', function () {
    expect(app(ExamRepositoryInterface::class))->toBeInstanceOf(ExamRepository::class)
        ->and(app(QuestionRepositoryInterface::class))->toBeInstanceOf(QuestionRepository::class)
        ->and(app(AttemptRepositoryInterface::class))->toBeInstanceOf(AttemptRepository::class);
});

test('exam service generates collision-free 6-char tokens and handles exam lifecycle', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $examService = app(ExamService::class);

    $token = $examService->generateUniqueToken();
    expect(strlen($token))->toBe(6);

    $exam = $examService->createExam($teacher, [
        'title' => 'Physics Midterm',
        'duration_minutes' => 90,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHours(2),
        'status' => ExamStatus::Published,
    ]);

    expect($exam->teacher_id)->toBe($teacher->id)
        ->and($exam->token)->toHaveLength(6);

    // Add question
    $question = $examService->addQuestion($exam, [
        'type' => QuestionType::MultipleChoice,
        'question_text' => 'What is the speed of light?',
        'options' => [
            ['key' => 'A', 'text' => '3x10^8 m/s'],
            ['key' => 'B', 'text' => '1.5x10^8 m/s'],
        ],
        'answer_key' => 'A',
        'score_weight' => 2.0,
    ]);

    expect($question->order)->toBe(1)
        ->and($question->score_weight)->toBe(2.0);

    // Update question
    $updatedQuestion = $examService->updateQuestion($question, ['score_weight' => 3.0]);
    expect($updatedQuestion->score_weight)->toBe(3.0);
});

test('cbt engine delivers sanitized questions to student without leaking answer_key', function () {
    $teacher = User::factory()->create();
    $exam = Exam::factory()->create(['teacher_id' => $teacher->id]);

    Question::factory()->multipleChoice('C')->create([
        'exam_id' => $exam->id,
        'question_text' => 'Capital of France?',
    ]);

    $cbtEngine = app(CbtEngineService::class);
    $questions = $cbtEngine->getStudentQuestions($exam);

    expect($questions)->toHaveCount(1)
        ->and($questions[0])->not->toHaveKey('answer_key')
        ->and($questions[0]['question_text'])->toBe('Capital of France?');
});

test('cbt engine starts or resumes exam, saves answers atomically, and auto-grades multiple choice', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();
    $student->assignRole('student');

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHours(2),
        'status' => ExamStatus::Published,
        'token' => 'ABC999',
    ]);

    $q1 = Question::factory()->multipleChoice('B')->create([
        'exam_id' => $exam->id,
        'score_weight' => 10.0,
        'order' => 1,
    ]);

    $q2 = Question::factory()->multipleChoice('A')->create([
        'exam_id' => $exam->id,
        'score_weight' => 10.0,
        'order' => 2,
    ]);

    $cbtEngine = app(CbtEngineService::class);

    // 1. Start exam
    $session = $cbtEngine->startOrResumeExam('ABC999', $student);
    $attempt = $session['attempt'];

    expect($attempt)->toBeInstanceOf(ExamAttempt::class)
        ->and($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($session['questions'])->toHaveCount(2);

    // 2. Atomic Upsert answers (q1 correct 'B', q2 incorrect 'C')
    $cbtEngine->saveAnswer($attempt->id, $q1->id, 'B');
    $cbtEngine->saveAnswer($attempt->id, $q2->id, 'C');

    // Check answers exist in DB
    expect(StudentAnswer::where('attempt_id', $attempt->id)->count())->toBe(2);

    // Overwrite q1 (idempotent atomic update)
    $cbtEngine->saveAnswer($attempt->id, $q1->id, 'B', isFlagged: true);
    expect(StudentAnswer::where('attempt_id', $attempt->id)->count())->toBe(2)
        ->and(StudentAnswer::where('attempt_id', $attempt->id)->where('question_id', $q1->id)->first()->is_flagged)->toBeTrue();

    // 3. Submit and Auto-Grade
    $submittedAttempt = $cbtEngine->submitExam($attempt);

    expect($submittedAttempt->status)->toBe(AttemptStatus::Submitted)
        ->and($submittedAttempt->submitted_at)->not->toBeNull()
        ->and($submittedAttempt->total_score)->toBe(10.0); // 10 from Q1, 0 from Q2
});

test('cbt engine proctor actions extend time and reset crashed session', function () {
    $teacher = User::factory()->create();
    $student = User::factory()->create();

    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'duration_minutes' => 60,
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->addHour(),
        'status' => ExamStatus::Published,
    ]);

    $attempt = ExamAttempt::factory()->submitted()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
    ]);

    $cbtEngine = app(CbtEngineService::class);

    // Extend time
    $extended = $cbtEngine->extendTime($attempt->id, 15);
    expect($extended->extra_time_minutes)->toBe(15);

    // Reset session (e.g. computer crash recovery)
    $reset = $cbtEngine->resetSession($attempt->id);
    expect($reset->status)->toBe(AttemptStatus::InProgress)
        ->and($reset->submitted_at)->toBeNull();
});

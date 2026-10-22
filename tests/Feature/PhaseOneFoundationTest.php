<?php

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('user primary key is valid uuid and supports spatie roles and permissions', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue();

    $user->assignRole('student');
    expect($user->hasRole('student'))->toBeTrue()
        ->and($user->hasPermissionTo('exams.take'))->toBeTrue()
        ->and($user->hasPermissionTo('exams.create'))->toBeFalse();

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    expect($teacher->hasRole('teacher'))->toBeTrue()
        ->and($teacher->hasPermissionTo('exams.create'))->toBeTrue()
        ->and($teacher->hasPermissionTo('exams.monitor'))->toBeTrue();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->hasPermissionTo('exams.delete'))->toBeTrue();
});

test('domain models create successfully with uuids and relationships work', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $student = User::factory()->create();
    $student->assignRole('student');

    // Create Exam
    $exam = Exam::factory()->create([
        'teacher_id' => $teacher->id,
        'title' => 'Biology Final Exam',
        'status' => ExamStatus::Published,
    ]);

    expect(Str::isUuid($exam->id))->toBeTrue()
        ->and($exam->teacher->id)->toBe($teacher->id)
        ->and($exam->status)->toBe(ExamStatus::Published);

    // Create Question
    $question = Question::factory()->multipleChoice('B')->create([
        'exam_id' => $exam->id,
        'order' => 1,
    ]);

    expect(Str::isUuid($question->id))->toBeTrue()
        ->and($question->type)->toBe(QuestionType::MultipleChoice)
        ->and($question->exam->id)->toBe($exam->id);

    // Create Attempt
    $attempt = ExamAttempt::factory()->create([
        'exam_id' => $exam->id,
        'student_id' => $student->id,
        'status' => AttemptStatus::InProgress,
    ]);

    expect(Str::isUuid($attempt->id))->toBeTrue()
        ->and($attempt->student->id)->toBe($student->id)
        ->and($attempt->exam->id)->toBe($exam->id)
        ->and($attempt->status)->toBe(AttemptStatus::InProgress);

    // Create Student Answer
    $answer = StudentAnswer::factory()->create([
        'attempt_id' => $attempt->id,
        'question_id' => $question->id,
        'answer_content' => 'B',
    ]);

    expect(Str::isUuid($answer->id))->toBeTrue()
        ->and($answer->attempt->id)->toBe($attempt->id)
        ->and($answer->question->id)->toBe($question->id)
        ->and($answer->answer_content)->toBe('B');

    // Relationship collections
    expect($teacher->exams)->toHaveCount(1)
        ->and($exam->questions)->toHaveCount(1)
        ->and($exam->attempts)->toHaveCount(1)
        ->and($student->examAttempts)->toHaveCount(1)
        ->and($attempt->answers)->toHaveCount(1);
});

test('inertia request shares user roles and permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('teacher');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.user')
            ->where('auth.user.id', $user->id)
            ->where('auth.roles', ['teacher'])
            ->has('auth.permissions', 8)
        );
});

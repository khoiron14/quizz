<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $exam_id
 * @property string $student_id
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 * @property int $extra_time_minutes
 * @property float|null $total_score
 * @property AttemptStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'exam_id',
    'student_id',
    'started_at',
    'submitted_at',
    'extra_time_minutes',
    'total_score',
    'status',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'extra_time_minutes' => 'integer',
            'total_score' => 'float',
            'status' => AttemptStatus::class,
        ];
    }

    /**
     * The exam this attempt belongs to.
     *
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    /**
     * The student taking this attempt.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Answers submitted during this attempt.
     *
     * @return HasMany<StudentAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class, 'attempt_id');
    }

    /**
     * Calculate absolute deadline timestamp for this attempt.
     */
    public function getDeadline(): Carbon
    {
        $duration = ($this->exam?->duration_minutes ?? 0) + $this->extra_time_minutes;

        return $this->started_at->copy()->addMinutes($duration);
    }
}

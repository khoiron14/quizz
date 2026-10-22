<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $teacher_id
 * @property string $title
 * @property int $duration_minutes
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property string $token
 * @property bool $is_randomize
 * @property ExamStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'teacher_id',
    'title',
    'duration_minutes',
    'start_time',
    'end_time',
    'token',
    'is_randomize',
    'status',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'is_randomize' => 'boolean',
            'status' => ExamStatus::class,
        ];
    }

    /**
     * The teacher who owns and authored this exam.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Questions belonging to this exam.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'exam_id')->orderBy('order');
    }

    /**
     * Attempts taken by students on this exam.
     *
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'exam_id');
    }
}

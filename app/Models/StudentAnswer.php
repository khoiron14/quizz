<?php

namespace App\Models;

use Database\Factories\StudentAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $attempt_id
 * @property string $question_id
 * @property string|null $answer_content
 * @property bool $is_flagged
 * @property float|null $score_awarded
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'attempt_id',
    'question_id',
    'answer_content',
    'is_flagged',
    'score_awarded',
    'last_synced_at',
])]
class StudentAnswer extends Model
{
    /** @use HasFactory<StudentAnswerFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'score_awarded' => 'float',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The attempt this answer belongs to.
     *
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * The question being answered.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}

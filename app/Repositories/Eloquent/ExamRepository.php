<?php

namespace App\Repositories\Eloquent;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Repositories\Contracts\ExamRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ExamRepository implements ExamRepositoryInterface
{
    /**
     * Find an exam by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?Exam
    {
        return Exam::query()
            ->with($with)
            ->find($id);
    }

    /**
     * Find an exam by its 6-character access token.
     *
     * @param  array<int, string>  $with
     */
    public function findByToken(string $token, array $with = []): ?Exam
    {
        return Exam::query()
            ->with($with)
            ->where('token', strtoupper($token))
            ->first();
    }

    /**
     * Find an active published exam by its token valid for current time.
     *
     * @param  array<int, string>  $with
     */
    public function findActiveByToken(string $token, array $with = []): ?Exam
    {
        $now = now();

        return Exam::query()
            ->with($with)
            ->where('token', strtoupper($token))
            ->where('status', ExamStatus::Published)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->first();
    }

    /**
     * Get all exams created by a specific teacher.
     *
     * @return Collection<int, Exam>
     */
    public function getByTeacherId(string $teacherId): Collection
    {
        return Exam::query()
            ->where('teacher_id', $teacherId)
            ->withCount(['questions', 'attempts'])
            ->latest()
            ->get();
    }

    /**
     * Create a new exam record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Exam
    {
        if (isset($data['token'])) {
            $data['token'] = strtoupper($data['token']);
        }

        return Exam::create($data);
    }

    /**
     * Update an exam record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Exam|string $exam, array $data): Exam
    {
        $model = $exam instanceof Exam ? $exam : Exam::findOrFail($exam);

        if (isset($data['token'])) {
            $data['token'] = strtoupper($data['token']);
        }

        $model->update($data);

        return $model->fresh();
    }

    /**
     * Delete an exam record.
     */
    public function delete(Exam|string $exam): bool
    {
        $model = $exam instanceof Exam ? $exam : Exam::findOrFail($exam);

        return (bool) $model->delete();
    }

    /**
     * Check if a 6-character token already exists among active or draft exams.
     */
    public function tokenExists(string $token, ?string $excludeExamId = null): bool
    {
        $query = Exam::query()
            ->where('token', strtoupper($token))
            ->where('status', '!=', ExamStatus::Archived);

        if ($excludeExamId !== null) {
            $query->where('id', '!=', $excludeExamId);
        }

        return $query->exists();
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\Exam;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Exam persistence operations.
 */
interface ExamRepositoryInterface
{
    /**
     * Find an exam by its UUID.
     *
     * @param  array<int, string>  $with
     */
    public function findById(string $id, array $with = []): ?Exam;

    /**
     * Find an exam by its 6-character access token.
     *
     * @param  array<int, string>  $with
     */
    public function findByToken(string $token, array $with = []): ?Exam;

    /**
     * Find an active published exam by its token valid for the current time.
     *
     * @param  array<int, string>  $with
     */
    public function findActiveByToken(string $token, array $with = []): ?Exam;

    /**
     * Get all exams created by a specific teacher.
     *
     * @return Collection<int, Exam>
     */
    public function getByTeacherId(string $teacherId): Collection;

    /**
     * Create a new exam record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Exam;

    /**
     * Update an exam record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Exam|string $exam, array $data): Exam;

    /**
     * Delete an exam record.
     */
    public function delete(Exam|string $exam): bool;

    /**
     * Check if a 6-character token already exists among active or draft exams.
     */
    public function tokenExists(string $token, ?string $excludeExamId = null): bool;
}

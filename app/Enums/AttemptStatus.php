<?php

namespace App\Enums;

/**
 * Status of a student's exam attempt.
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case TimedOut = 'timed_out';
    case Reset = 'reset';

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::TimedOut => 'Timed Out',
            self::Reset => 'Reset',
        };
    }

    /**
     * Check if the attempt is active and can receive answers.
     */
    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Get all possible string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

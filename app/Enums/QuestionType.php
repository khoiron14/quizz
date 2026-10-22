<?php

namespace App\Enums;

/**
 * Supported CBT question types.
 */
enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case Essay = 'essay';

    /**
     * Get human-readable label for the question type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::Essay => 'Essay',
        };
    }

    /**
     * Check if this question type is automatically gradable.
     */
    public function isAutoGradable(): bool
    {
        return $this === self::MultipleChoice;
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

<?php

namespace App\Http\Requests\Teacher;

use App\Enums\ExamStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateExamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('exams.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:600'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date', 'after:start_time'],
            'token' => ['sometimes', 'required', 'string', 'size:6'],
            'is_randomize' => ['nullable', 'boolean'],
            'status' => ['sometimes', 'required', new Enum(ExamStatus::class)],
        ];
    }
}

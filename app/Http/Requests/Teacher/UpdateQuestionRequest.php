<?php

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateQuestionRequest extends FormRequest
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
            'type' => ['sometimes', 'required', new Enum(QuestionType::class)],
            'question_text' => ['sometimes', 'required', 'string'],
            'options' => ['nullable', 'array'],
            'options.*.key' => ['required_with:options', 'string', 'max:5'],
            'options.*.text' => ['required_with:options', 'string'],
            'answer_key' => ['nullable'],
            'score_weight' => ['sometimes', 'required', 'numeric', 'min:0.1', 'max:100'],
            'order' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}

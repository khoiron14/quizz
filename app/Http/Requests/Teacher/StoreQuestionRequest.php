<?php

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('exams.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(QuestionType::class)],
            'question_text' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'options.*.key' => ['required_with:options', 'string', 'max:5'],
            'options.*.text' => ['required_with:options', 'string'],
            'answer_key' => ['nullable'],
            'score_weight' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

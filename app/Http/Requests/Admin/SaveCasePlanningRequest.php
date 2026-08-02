<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCasePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'services' => ['required', 'array', 'min:1'],
            'services.*.decision' => ['required', Rule::in(['approved', 'rejected', 'under_review'])],
            'services.*.decision_notes' => ['nullable', 'string', 'max:2000'],
            'services.*.customer_decision_message' => ['nullable', 'string', 'max:500'],
            'services.*.work_scope_ids' => ['nullable', 'array', 'max:30'],
            'services.*.work_scope_ids.*' => ['integer', 'distinct', 'exists:work_scope_items,id'],
            'services.*.custom_work_item' => ['nullable', 'string', 'max:150'],
            'services.*.internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

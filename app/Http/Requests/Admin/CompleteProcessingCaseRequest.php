<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProcessingCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['completion_date' => ['required', 'date', 'before_or_equal:today'], 'customer_remark' => ['nullable', 'string', 'max:2000'], 'internal_note' => ['nullable', 'string', 'max:2000'], 'revenue_entry_override' => ['nullable', 'boolean'], 'revenue_entry_override_reason' => ['nullable', 'string', 'max:2000', 'required_if:revenue_entry_override,1']];
    }
}

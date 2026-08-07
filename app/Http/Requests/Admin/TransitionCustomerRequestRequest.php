<?php

namespace App\Http\Requests\Admin;

use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(RequestWorkflowService::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => in_array($this->input('status'), ['need_documents', 'rejected'], true))], 'is_visible_to_customer' => ['nullable', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('status'), ['need_documents', 'rejected'], true)) {
            $this->merge(['is_visible_to_customer' => true]);
        }
    }
}

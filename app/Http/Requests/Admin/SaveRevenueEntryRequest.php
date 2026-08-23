<?php

namespace App\Http\Requests\Admin;

use App\Models\RequestRevenueEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRevenueEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $values = preg_split('/[\r\n,]+/', (string) $this->input('survey_numbers_text', ''), -1, PREG_SPLIT_NO_EMPTY);
        $this->merge(['survey_numbers' => array_values(array_unique(array_map('trim', $values ?: [])))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('requests.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'entry_number' => ['nullable', 'string', 'max:255'], 'entry_date' => ['nullable', 'date'],
            'application_number' => ['nullable', 'string', 'max:255'], 'application_date' => ['nullable', 'date'],
            'village' => ['nullable', 'string', 'max:255'], 'taluka' => ['nullable', 'string', 'max:255'], 'district' => ['nullable', 'string', 'max:255'],
            'survey_numbers_text' => ['nullable', 'string', 'max:5000'], 'survey_numbers' => ['nullable', 'array', 'max:50'], 'survey_numbers.*' => ['required', 'string', 'max:100', 'distinct'],
            'status' => ['required', Rule::in(RequestRevenueEntry::STATUSES)],
            'submitted_at' => ['nullable', 'date'], 'certified_at' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->input('status') === 'rejected')],
            'admin_notes' => ['nullable', 'string', 'max:5000'], 'customer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

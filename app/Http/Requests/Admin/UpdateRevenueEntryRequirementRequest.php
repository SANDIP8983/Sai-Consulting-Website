<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRevenueEntryRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requests.manage') ?? false;
    }

    public function rules(): array
    {
        return ['revenue_entry_required' => ['required', 'boolean']];
    }
}

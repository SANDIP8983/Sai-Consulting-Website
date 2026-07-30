<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holiday_date' => ['required', 'date', Rule::unique('holidays', 'holiday_date')->ignore($this->route('holiday'))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_recurring' => ['required', 'boolean'],
            'is_closed' => ['required', 'boolean'],
        ];
    }
}

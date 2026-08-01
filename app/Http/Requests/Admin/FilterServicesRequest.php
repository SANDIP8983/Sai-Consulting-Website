<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', Rule::in(['1', '0'])],
            'availability' => ['nullable', Rule::in(['online', 'offline'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->query('q')) ?: null]);
    }
}

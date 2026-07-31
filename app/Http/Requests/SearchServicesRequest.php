<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => trim((string) $this->query('q')) ?: null]);
    }
}

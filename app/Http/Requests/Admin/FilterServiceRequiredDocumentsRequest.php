<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterServiceRequiredDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}

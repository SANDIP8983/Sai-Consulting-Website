<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:150'],
            'name_gu' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'documents' => ['nullable', 'array', 'max:30'],
            'documents.*.name_en' => ['required_with:documents', 'string', 'max:150'],
            'documents.*.name_gu' => ['required_with:documents', 'string', 'max:150'],
            'documents.*.sort_order' => ['required_with:documents', 'integer', 'min:0'],
        ];
    }
}

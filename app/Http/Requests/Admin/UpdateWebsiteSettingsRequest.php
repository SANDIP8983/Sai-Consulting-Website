<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'website_name' => ['required', 'string', 'max:150'],
            'website_status' => ['required', Rule::in(['active', 'maintenance'])],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

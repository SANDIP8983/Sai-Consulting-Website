<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'public_email' => ['nullable', 'email:rfc,dns', 'max:150'],
            'public_phone' => ['nullable', 'string', 'max:25'],
            'whatsapp_number' => ['nullable', 'regex:/^[1-9][0-9]{7,14}$/'],
        ];
    }
}

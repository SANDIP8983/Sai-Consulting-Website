<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['required', 'string', 'max:30', 'regex:/^SC\/\d{4}\/\d{6}$/i'],
            'mobile' => ['required', 'digits:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_no' => strtoupper(trim((string) $this->input('reference_no'))),
            'mobile' => preg_replace('/\D/', '', (string) $this->input('mobile')),
        ]);
    }
}

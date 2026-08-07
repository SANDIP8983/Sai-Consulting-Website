<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestServiceFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'professional_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideRequestServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_notes' => ['nullable', 'string', 'max:2000']];
    }
}

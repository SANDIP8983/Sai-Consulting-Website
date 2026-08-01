<?php

namespace App\Http\Requests\Admin;

use App\Models\RequestServiceWorkScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkScopeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(RequestServiceWorkScope::STATUSES)], 'internal_note' => ['nullable', 'string', 'max:2000']];
    }
}

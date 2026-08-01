<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRequestServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['service_id' => ['required', 'integer', Rule::exists('services', 'id')->where(fn ($q) => $q->where('is_active', true))]];
    }
}

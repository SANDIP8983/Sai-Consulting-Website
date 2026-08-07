<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestPostRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['certified_copy_status' => ['nullable', Rule::in(['not_required', 'pending', 'received'])], 'certified_copy_received_date' => ['nullable', 'date'], 'ready_for_dispatch_date' => ['nullable', 'date']];
    }
}

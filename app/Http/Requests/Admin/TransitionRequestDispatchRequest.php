<?php

namespace App\Http\Requests\Admin;

use App\Services\DispatchManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRequestDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'dispatch_status' => ['required', Rule::in(DispatchManagementService::STATUSES)],
            'delivered_at' => ['nullable', 'date', 'before_or_equal:now'],
            'collected_at' => ['nullable', 'date', 'before_or_equal:now'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'customer_remark' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

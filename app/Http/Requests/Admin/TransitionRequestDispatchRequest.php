<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ConvertsLocalDateTimes;
use App\Services\DispatchManagementService;
use App\Support\IndiaDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionRequestDispatchRequest extends FormRequest
{
    use ConvertsLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'dispatch_status' => ['required', Rule::in(DispatchManagementService::STATUSES)],
            'delivered_at' => ['nullable', 'date', IndiaDateTime::notFutureRule()],
            'collected_at' => ['nullable', 'date', IndiaDateTime::notFutureRule()],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'customer_remark' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function localDateTimeFields(): array
    {
        return ['delivered_at', 'collected_at'];
    }
}

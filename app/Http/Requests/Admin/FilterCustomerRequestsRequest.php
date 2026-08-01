<?php

namespace App\Http\Requests\Admin;

use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCustomerRequestsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'source' => ['nullable', Rule::in(['online', 'offline'])], 'status' => ['nullable', Rule::in(RequestWorkflowService::STATUSES)], 'payment_status' => ['nullable', Rule::in(['not_required', 'pending', 'received', 'failed', 'refunded'])], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']];
    }
}

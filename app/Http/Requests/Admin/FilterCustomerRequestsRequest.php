<?php

namespace App\Http\Requests\Admin;

use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCustomerRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'village' => ['nullable', 'string', 'max:100'], 'survey_number' => ['nullable', 'string', 'max:100'], 'khata_number' => ['nullable', 'string', 'max:100'], 'processing_stage' => ['nullable', 'string', 'max:100'], 'overdue' => ['nullable', 'boolean'], 'queue' => ['nullable', Rule::in(['pending_approval'])], 'source' => ['nullable', Rule::in(['online', 'offline'])], 'status' => ['nullable', Rule::in(RequestWorkflowService::STATUSES)], 'processing_state' => ['nullable', Rule::in(['not_started', 'in_progress', 'ready', 'completed'])], 'dispatch_state' => ['nullable', Rule::in(['pending', 'dispatched', 'in_transit', 'delivered', 'ready_to_close', 'closed', 'failed_returned'])], 'service_id' => ['nullable', 'integer', 'exists:services,id'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ConvertsLocalDateTimes;
use App\Services\DispatchManagementService;
use App\Support\IndiaDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestDispatchRequest extends FormRequest
{
    use ConvertsLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'dispatch_status' => ['required', Rule::in(['prepared', 'dispatched', 'collected'])],
            'dispatch_method' => ['required', Rule::in(DispatchManagementService::METHODS)],
            'dispatch_date' => ['required', 'date', IndiaDateTime::notFutureRule()],
            'document_description' => ['required', 'string', 'max:2000'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_mobile' => ['nullable', 'regex:/^[0-9+() -]{7,20}$/'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'carrier_name' => ['nullable', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:2048'],
            'method_description' => ['nullable', 'string', 'max:255'],
            'collected_at' => ['nullable', 'date', IndiaDateTime::notFutureRule()],
            'customer_remark' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'proof_type' => [Rule::requiredIf($this->hasFile('proof')), 'nullable', Rule::in(DispatchManagementService::PROOF_TYPES)],
        ];
    }

    protected function localDateTimeFields(): array
    {
        return ['dispatch_date', 'collected_at'];
    }
}

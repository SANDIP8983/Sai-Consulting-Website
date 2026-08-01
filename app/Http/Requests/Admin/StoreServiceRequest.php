<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use App\Support\PublicDocumentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:150', Rule::unique(Service::class)->ignore($this->route('service'))],
            'name_gu' => ['required', 'string', 'max:150', Rule::unique(Service::class)->ignore($this->route('service'))],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'description_gu' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'customer_instructions' => ['nullable', 'string', 'max:5000'],
            'important_notes' => ['nullable', 'string', 'max:5000'],
            'disclaimer' => ['nullable', 'string', 'max:5000'],
            'processing_time_label' => ['nullable', 'string', 'max:100'],
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'government_charges' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'government_charge_items' => ['nullable', 'array', 'max:20'],
            'government_charge_items.*.id' => ['nullable', 'integer'],
            'government_charge_items.*.name' => ['required_with:government_charge_items', 'string', 'max:150'],
            'government_charge_items.*.amount' => ['required_with:government_charge_items', 'numeric', 'min:0', 'max:99999999.99'],
            'government_charge_items.*.description' => ['nullable', 'string', 'max:1000'],
            'government_charge_items.*.sort_order' => ['required_with:government_charge_items', 'integer', 'min:0'],
            'government_charge_items.*.is_active' => ['nullable', 'boolean'],
            'estimated_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'available_online' => ['nullable', 'boolean'],
            'available_offline' => ['nullable', 'boolean'],
            'requires_property_documents' => ['nullable', 'boolean'],
            'requires_dispatch' => ['nullable', 'boolean'],
            'requires_payment_before_processing' => ['nullable', 'boolean'],
            'uses_drafting_workflow' => ['nullable', 'boolean'],
            'requires_token_booking' => ['nullable', 'boolean'],
            'requires_registration' => ['nullable', 'boolean'],
            'requires_certified_copy' => ['nullable', 'boolean'],
            'work_scope_item_ids' => ['nullable', 'array', 'max:30'],
            'work_scope_item_ids.*' => ['integer', 'distinct', 'exists:work_scope_items,id'],
            'documents' => ['nullable', 'array', 'max:30'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.name_en' => ['required_with:documents', 'string', 'max:150', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! PublicDocumentPolicy::isSafe((string) $value)) {
                    $fail('Personal KYC documents cannot be added to the public document library.');
                }
            }],
            'documents.*.name_gu' => ['required_with:documents', 'string', 'max:150'],
            'documents.*.is_mandatory' => ['nullable', 'boolean'],
            'documents.*.allowed_file_types' => ['nullable', 'array', 'max:10'],
            'documents.*.allowed_file_types.*' => ['string', Rule::in(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])],
            'documents.*.max_upload_size_kb' => ['nullable', 'integer', 'min:1', 'max:51200'],
            'documents.*.sort_order' => ['required_with:documents', 'integer', 'min:0'],
        ];
    }
}

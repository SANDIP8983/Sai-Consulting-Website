<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizeRequestBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', Rule::in(['regular_customer', 'family', 'special_discount', 'festival', 'management_approval', 'other']), Rule::requiredIf(fn () => $this->input('discount_type') !== 'none')],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'gst_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'government_charges' => ['nullable', 'array', 'max:20'],
            'government_charges.*.government_charge_type_id' => ['nullable', 'integer', 'exists:government_charge_types,id'],
            'government_charges.*.name' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => collect($this->input('government_charges', []))->contains(fn ($charge) => empty($charge['government_charge_type_id']) && empty($charge['name'])))],
            'government_charges.*.amount' => ['required_with:government_charges', 'numeric', 'min:0', 'max:99999999.99'],
            'government_charges.*.note' => ['nullable', 'string', 'max:500'],
            'government_charges.*.display_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadRevenueEntryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requests.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['application_receipt', 'mutation_entry_copy', 'certification_order', 'rejection_order', 'other'])],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}

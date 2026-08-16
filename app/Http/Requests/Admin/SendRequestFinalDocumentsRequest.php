<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendRequestFinalDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requests.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1', 'max:'.config('final-documents.max_files_per_upload')],
            'document_ids.*' => ['required', 'integer', 'distinct'],
            'channel' => ['required', 'in:email'],
        ];
    }
}

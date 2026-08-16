<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequestFinalDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requests.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:'.config('final-documents.max_files_per_upload')],
            'documents.*' => ['required', 'file', 'mimes:'.implode(',', config('final-documents.allowed_extensions')), 'max:'.config('final-documents.max_file_size_kilobytes')],
        ];
    }
}

<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
class StoreRegisteredDocumentScanRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['registered_document' => ['required','file','mimes:pdf,jpg,jpeg,png','max:10240']]; }
}

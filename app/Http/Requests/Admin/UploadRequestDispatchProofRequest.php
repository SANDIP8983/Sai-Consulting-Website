<?php

namespace App\Http\Requests\Admin;

use App\Services\DispatchManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadRequestDispatchProofRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'proof_type' => ['required', Rule::in(DispatchManagementService::PROOF_TYPES)]]; }
}

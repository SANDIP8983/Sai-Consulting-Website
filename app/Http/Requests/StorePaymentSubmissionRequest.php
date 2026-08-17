<?php

namespace App\Http\Requests;

use App\Services\PaymentSettingsService;
use App\Services\PaymentSubmissionService;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customerRequest = $this->route('customerRequest');
        $verifiedAt = $customerRequest ? $this->session()->get('public_tracking.verified_requests.'.$customerRequest->id) : null;

        return is_int($verifiedAt) && now()->timestamp - $verifiedAt <= 1800;
    }

    public function rules(): array
    {
        $proofAllowed = app(PaymentSettingsService::class)->settings()['proof_upload_allowed'];

        return [
            'utr_reference' => ['required', 'string', 'min:6', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._\/-]*$/'],
            'proof' => [$proofAllowed ? 'nullable' : 'prohibited', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.PaymentSubmissionService::MAX_PROOF_KB],
            'declaration' => ['accepted'],
        ];
    }
}

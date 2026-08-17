<?php

namespace App\Http\Requests\Admin;

use App\Services\PaymentSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $enabled = $this->boolean('enabled');
        $hasQr = app(PaymentSettingsService::class)->settings()['qr_path'] !== null;

        return [
            'enabled' => ['nullable', 'boolean'],
            'upi_id' => [$enabled ? 'required' : 'nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/'],
            'payee_name' => [$enabled ? 'required' : 'nullable', 'string', 'max:150'],
            'qr_code' => [$enabled && ! $hasQr ? 'required' : 'nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_qr_code' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'proof_upload_allowed' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('enabled') && $this->boolean('remove_qr_code') && ! $this->hasFile('qr_code')) {
                $validator->errors()->add('qr_code', 'A QR code is required while UPI payments are enabled.');
            }
        }];
    }
}

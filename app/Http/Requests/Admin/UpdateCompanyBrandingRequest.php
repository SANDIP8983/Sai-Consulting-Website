<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

        return [
            'business_name' => ['nullable', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:250'],
            'address' => ['nullable', 'string', 'max:1000'],
            'mobile' => ['nullable', 'regex:/^(?:\+?91)?[6-9][0-9]{9}$/'],
            'whatsapp' => ['nullable', 'regex:/^(?:\+?91)?[6-9][0-9]{9}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'primary_logo' => $image, 'dark_logo' => $image, 'favicon' => $image,
            'pdf_logo' => $image, 'stamp' => $image, 'signature' => $image,
            'remove_primary_logo' => ['nullable', 'boolean'], 'remove_dark_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'], 'remove_pdf_logo' => ['nullable', 'boolean'],
            'remove_stamp' => ['nullable', 'boolean'], 'remove_signature' => ['nullable', 'boolean'],
        ];
    }
}

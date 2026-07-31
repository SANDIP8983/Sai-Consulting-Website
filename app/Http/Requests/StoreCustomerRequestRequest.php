<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', Rule::exists('services', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'survey_numbers' => ['required', 'string', 'max:1000'],
            'khata_number' => ['required', 'string', 'max:100'],
            'details' => ['required', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'declaration' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.digits' => 'Mobile number must contain exactly 10 digits. / મોબાઇલ નંબર બરાબર 10 અંકનો હોવો જોઈએ.',
            'documents.required' => 'Please upload at least one document. / ઓછામાં ઓછો એક દસ્તાવેજ અપલોડ કરો.',
            'documents.max' => 'You may upload a maximum of 10 files. / વધુમાં વધુ 10 ફાઇલ અપલોડ કરી શકો.',
            'documents.*.mimes' => 'Only PDF, JPG, JPEG and PNG files are allowed. / માત્ર PDF, JPG, JPEG અને PNG ફાઇલ માન્ય છે.',
            'documents.*.max' => 'Each file may be no larger than 10 MB. / દરેક ફાઇલ મહત્તમ 10 MB હોવી જોઈએ.',
            'declaration.accepted' => 'You must accept the declaration. / કૃપા કરીને ઘોષણા સ્વીકારો.',
        ];
    }
}

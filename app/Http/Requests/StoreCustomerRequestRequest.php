<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'service_id' => 'required|exists:services,id',

            'name' => 'required|string|max:100',

            'mobile' => 'required|digits:10',

            'email' => 'nullable|email',

            'village' => 'nullable|string|max:100',

            'taluka' => 'nullable|string|max:100',

            'district' => 'nullable|string|max:100',

            'survey_numbers' => 'nullable|string',

            'khata_number' => 'nullable|string|max:100',

            'details' => 'nullable|string|max:2000',

            'documents' => 'required|array|min:1|max:10',

            'documents.*' =>
                'file|mimes:pdf,jpg,jpeg,png|max:10240',

            'declaration' => 'accepted',

        ];
    }

    public function messages(): array
    {
        return [

            'mobile.digits' =>
                'મોબાઇલ નંબર 10 અંકનો હોવો જોઈએ.',

            'documents.required' =>
                'ઓછામાં ઓછો એક દસ્તાવેજ અપલોડ કરો.',

            'documents.max' =>
                'વધુમાં વધુ 10 ફાઇલ અપલોડ કરી શકો.',

            'documents.*.mimes' =>
                'માત્ર PDF, JPG, JPEG અને PNG ફાઇલ માન્ય છે.',

            'documents.*.max' =>
                'દરેક ફાઇલ મહત્તમ 10 MB હોવી જોઈએ.',

            'declaration.accepted' =>
                'કૃપા કરીને Declaration પસંદ કરો.',

        ];
    }
}
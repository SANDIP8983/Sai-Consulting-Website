<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Support\PublicDocumentPolicy;

class StoreCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $services = Service::query()->with('activeRequiredDocuments')->whereIn('id', $this->input('service_ids', []))->get();
        $requiresProperty = $services->contains('requires_property_documents', true);
        $types = $services->flatMap->activeRequiredDocuments->flatMap(fn ($document) => $document->allowed_file_types ?? [])->unique()->values()->all() ?: ['pdf', 'jpg', 'jpeg', 'png'];
        $maximumSize = $services->flatMap->activeRequiredDocuments->max('max_upload_size_kb') ?: 10240;

        return [
            'service_id' => ['required', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->where($this->availabilityColumn(), true))],
            'service_ids' => ['required', 'array', 'min:1', 'max:20'],
            'service_ids.*' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->where($this->availabilityColumn(), true))],
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'digits:10'],
            'whatsapp' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'property_village' => [Rule::requiredIf($requiresProperty), 'nullable', 'string', 'max:150'],
            'property_taluka' => [Rule::requiredIf($requiresProperty), 'nullable', 'string', 'max:150'],
            'property_district' => [Rule::requiredIf($requiresProperty), 'nullable', 'string', 'max:150'],
            'property_address_remarks' => ['nullable', 'string', 'max:2000'],
            'survey_numbers' => ['nullable', 'string', 'max:1000'],
            'khata_number' => ['nullable', 'string', 'max:100'],
            'tp_number' => ['nullable', 'string', 'max:100'],
            'final_plot_number' => ['nullable', 'string', 'max:100'],
            'revenue_village' => ['nullable', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['required', 'file', 'mimes:'.implode(',', $types), 'max:'.$maximumSize, function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->availabilityColumn() !== 'available_online' || ! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }
                if (! PublicDocumentPolicy::isSafe($value->getClientOriginalName())) {
                    $fail('Personal identity, address, financial, and KYC documents must not be uploaded on the public website.');
                }
            }],
            'declaration' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $serviceIds = array_values(array_filter((array) $this->input('service_ids', [])));
        if ($serviceIds === [] && $this->filled('service_id')) {
            $serviceIds = [$this->input('service_id')];
        }
        $this->merge(['service_ids' => $serviceIds, 'service_id' => $serviceIds[0] ?? $this->input('service_id')]);
    }

    protected function availabilityColumn(): string
    {
        return 'available_online';
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

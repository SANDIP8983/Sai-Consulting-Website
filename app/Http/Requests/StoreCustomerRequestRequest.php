<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\ServiceRequiredDocument;
use App\Support\PublicDocumentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreCustomerRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $services = $this->eligibleServicesQuery()->with('activeRequiredDocuments')->whereIn('id', $this->input('service_ids', []))->get();
        $requiresProperty = $services->contains('requires_property_documents', true);
        $publicRestrictions = PublicDocumentPolicy::restrictionsForServices($services);
        $configuredTypes = $services->flatMap->activeRequiredDocuments->flatMap(fn ($document) => $document->allowed_file_types ?? [])->unique()->values()->all() ?: PublicDocumentPolicy::ALLOWED_EXTENSIONS;
        $configuredMaximumSize = $services->flatMap->activeRequiredDocuments->max('max_upload_size_kb') ?: PublicDocumentPolicy::MAX_SIZE_KILOBYTES;
        $online = $this->availabilityColumn() === 'available_online';
        $fileRules = $this->availabilityColumn() === 'available_online'
            ? ['mimetypes:'.implode(',', PublicDocumentPolicy::ALLOWED_MIME_TYPES), 'max:'.$publicRestrictions['max_kilobytes']]
            : ['mimes:'.implode(',', $configuredTypes), 'max:'.$configuredMaximumSize];

        return [
            'service_id' => ['required', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->where($this->availabilityColumn(), true)->when($online, fn ($query) => $query->where('show_on_public_website', true)))],
            'service_ids' => ['required', 'array', 'min:1', 'max:20'],
            'service_ids.*' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where(fn ($query) => $query->where('is_active', true)->where($this->availabilityColumn(), true)->when($online, fn ($query) => $query->where('show_on_public_website', true)))],
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
            $online ? 'document_uploads' : 'documents' => ['nullable', 'array', 'max:10'],
            ($online ? 'document_uploads' : 'documents').'.*' => ['required', 'file', ...$fileRules, function (string $attribute, mixed $value, \Closure $fail) use ($publicRestrictions): void {
                if ($this->availabilityColumn() !== 'available_online' || ! $value instanceof UploadedFile) {
                    return;
                }

                if ($violation = PublicDocumentPolicy::violation($value, $publicRestrictions)) {
                    Log::warning('Public document upload rejected.', [
                        'reason' => $violation,
                        'filename_fingerprint' => hash('sha256', strtolower($value->getClientOriginalName())),
                        'request_ip_fingerprint' => hash('sha256', (string) $this->ip()),
                    ]);
                    $fail('This document cannot be accepted. Upload only PDF, JPG, JPEG, or PNG property documents up to 10 MB. Do not upload identity, address, financial, or KYC documents.');
                }
            }],
            'declaration' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        if ($this->availabilityColumn() !== 'available_online') {
            return [];
        }

        return [function ($validator): void {
            $services = $this->selectedServices();
            $applicable = $services->flatMap->activeRequiredDocuments;
            $byId = $applicable->keyBy(fn ($document) => (string) $document->id);
            $submitted = collect(array_keys((array) $this->file('document_uploads', [])))->map(fn ($id) => (string) $id);

            if ($submitted->contains(fn ($id) => ! $byId->has($id))) {
                $validator->errors()->add('document_uploads', 'One or more selected document types are not valid for the selected services.');

                return;
            }

            $uploadedKeys = $submitted->map(fn ($id) => $this->documentKey($byId[$id]))->unique();
            $requiredKeys = $applicable->where('requirement_type', 'required')->map(fn ($document) => $this->documentKey($document))->unique();
            if ($requiredKeys->diff($uploadedKeys)->isNotEmpty()) {
                $validator->errors()->add('document_uploads', 'Please upload a file for every required document type.');
            }

            $missingAnyOneGroup = $services->contains(function (Service $service) use ($uploadedKeys): bool {
                $keys = $service->activeRequiredDocuments
                    ->where('requirement_type', 'any_one_required')
                    ->map(fn ($document) => $this->documentKey($document))
                    ->unique();

                return $keys->isNotEmpty() && $uploadedKeys->intersect($keys)->isEmpty();
            });
            if ($missingAnyOneGroup) {
                $validator->errors()->add('document_uploads', 'Please upload at least one document from the Any One Required group.');
            }
        }];
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

    private function selectedServices()
    {
        return $this->eligibleServicesQuery()->with('activeRequiredDocuments')->whereIn('id', $this->input('service_ids', []))->get();
    }

    private function eligibleServicesQuery()
    {
        return Service::query()
            ->where('is_active', true)
            ->where($this->availabilityColumn(), true)
            ->when($this->availabilityColumn() === 'available_online', fn ($query) => $query->where('show_on_public_website', true));
    }

    private function documentKey(ServiceRequiredDocument $document): string
    {
        return $document->common_required_document_id
            ? 'common:'.$document->common_required_document_id
            : 'mapping:'.$document->id;
    }

    public function messages(): array
    {
        return [
            'mobile.digits' => 'Mobile number must contain exactly 10 digits. / મોબાઇલ નંબર બરાબર 10 અંકનો હોવો જોઈએ.',
            'document_uploads.max' => 'You may upload a maximum of 10 files. / વધુમાં વધુ 10 ફાઇલ અપલોડ કરી શકો.',
            'document_uploads.*.mimetypes' => 'Only valid PDF, JPG, JPEG and PNG files are allowed. / માત્ર માન્ય PDF, JPG, JPEG અને PNG ફાઇલ માન્ય છે.',
            'document_uploads.*.max' => 'Each file may be no larger than 10 MB. / દરેક ફાઇલ મહત્તમ 10 MB હોવી જોઈએ.',
            'declaration.accepted' => 'You must accept the declaration. / કૃપા કરીને ઘોષણા સ્વીકારો.',
        ];
    }
}

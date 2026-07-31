@extends('layouts.app')

@section('title', 'Submit Customer Request | ગ્રાહક વિનંતી')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
                <div>
                    <h1 class="h2 mb-1">Customer Request <span class="text-muted">/ ગ્રાહક વિનંતી</span></h1>
                    <p class="text-muted mb-0">Submit land-record documents securely for review.</p>
                </div>
                <a href="{{ route('request.track') }}" class="btn btn-outline-primary">Track Request / સ્થિતિ તપાસો</a>
            </div>

            <div class="alert alert-danger" role="alert">
                <strong>Identity documents are not accepted.</strong> Do not upload Aadhaar, PAN, passport, voter ID, bank documents, or any other identity proof.
                <div class="mt-1">ઓળખના પુરાવા અપલોડ કરશો નહીં: આધાર, PAN, પાસપોર્ટ, મતદાર કાર્ડ અથવા બેંક દસ્તાવેજ.</div>
            </div>
            <div class="alert alert-info" role="alert">
                This upload is intended only for records such as <strong>7/12, 8-A, Hak Patrak and Property Card</strong>.
            </div>

            @if($errors->any())
                <div class="alert alert-danger">Please correct the highlighted fields. / કૃપા કરીને દર્શાવેલ વિગતો સુધારો.</div>
            @endif

            <form method="POST" action="{{ route('request.store') }}" enctype="multipart/form-data" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4 p-lg-5">
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="service_id" class="form-label">Service / સેવા <span class="text-danger">*</span></label>
                            <select id="service_id" name="service_id" class="form-select @error('service_id') is-invalid @enderror" required>
                                <option value="">Select Service / સેવા પસંદ કરો</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected(old('service_id', request('service')) == $service->id)>{{ $service->name_en }} / {{ $service->name_gu }}</option>
                                @endforeach
                            </select>
                            @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label">Customer Name / ગ્રાહકનું નામ <span class="text-danger">*</span></label>
                            <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required maxlength="100">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number / મોબાઇલ નંબર <span class="text-danger">*</span></label>
                            <input id="mobile" name="mobile" value="{{ old('mobile') }}" class="form-control @error('mobile') is-invalid @enderror" inputmode="numeric" autocomplete="tel" maxlength="10" required>
                            @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email / ઇમેઇલ <span class="text-muted">(optional)</span></label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="village" class="form-label">Village / ગામ <span class="text-danger">*</span></label>
                            <input id="village" name="village" value="{{ old('village') }}" class="form-control @error('village') is-invalid @enderror" required maxlength="100">
                            @error('village')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="taluka" class="form-label">Taluka / તાલુકો <span class="text-danger">*</span></label>
                            <input id="taluka" name="taluka" value="{{ old('taluka') }}" class="form-control @error('taluka') is-invalid @enderror" required maxlength="100">
                            @error('taluka')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="district" class="form-label">District / જિલ્લો <span class="text-danger">*</span></label>
                            <input id="district" name="district" value="{{ old('district') }}" class="form-control @error('district') is-invalid @enderror" required maxlength="100">
                            @error('district')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="survey_numbers" class="form-label">Survey / Block Numbers <span class="text-danger">*</span></label>
                            <input id="survey_numbers" name="survey_numbers" value="{{ old('survey_numbers') }}" class="form-control @error('survey_numbers') is-invalid @enderror" required>
                            @error('survey_numbers')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="khata_number" class="form-label">Khata Number / ખાતા નંબર <span class="text-danger">*</span></label>
                            <input id="khata_number" name="khata_number" value="{{ old('khata_number') }}" class="form-control @error('khata_number') is-invalid @enderror" required maxlength="100">
                            @error('khata_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="details" class="form-label">Request Details / વિનંતીની વિગત <span class="text-danger">*</span></label>
                            <textarea id="details" name="details" rows="4" class="form-control @error('details') is-invalid @enderror" required maxlength="2000">{{ old('details') }}</textarea>
                            @error('details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-none" id="required-documents">
                            <div class="alert alert-secondary mb-0">
                                <strong>Documents for selected service / પસંદ કરેલી સેવા માટે દસ્તાવેજો</strong>
                                <ul class="mb-0 mt-2" id="required-documents-list"></ul>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="documents" class="form-label">Documents / દસ્તાવેજો <span class="text-danger">*</span></label>
                            <input id="documents" type="file" name="documents[]" class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" multiple accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                            @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">PDF, JPG, JPEG or PNG only. Maximum 10 files and 10 MB per file.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="declaration" value="1" class="form-check-input @error('declaration') is-invalid @enderror" id="declaration" required @checked(old('declaration'))>
                                <label class="form-check-label" for="declaration">I declare that the information is accurate and that I have not uploaded identity proofs. / આપેલી માહિતી સાચી છે અને ઓળખના પુરાવા અપલોડ કર્યા નથી.</label>
                                @error('declaration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-lg mt-4" type="submit">Submit Request / વિનંતી મોકલો</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const serviceDocuments = @json($services->mapWithKeys(fn ($service) => [$service->id => $service->requiredDocuments->map(fn ($document) => [$document->name_en, $document->name_gu])->values()]));
    const serviceSelect = document.getElementById('service_id');
    const documentPanel = document.getElementById('required-documents');
    const documentList = document.getElementById('required-documents-list');

    function renderRequiredDocuments() {
        const documents = serviceDocuments[serviceSelect.value] || [];
        documentList.replaceChildren();
        documents.forEach(([english, gujarati]) => {
            const item = document.createElement('li');
            item.textContent = `${english} / ${gujarati}`;
            documentList.append(item);
        });
        documentPanel.classList.toggle('d-none', documents.length === 0);
    }

    serviceSelect.addEventListener('change', renderRequiredDocuments);
    renderRequiredDocuments();
</script>
@endpush

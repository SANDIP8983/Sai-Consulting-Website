@php
    $documentRows = old('documents', $service?->requiredDocuments->map(fn ($document) => [
        'id' => $document->id,
        'name_en' => $document->name_en,
        'name_gu' => $document->name_gu,
        'is_mandatory' => $document->is_mandatory,
        'allowed_file_types' => $document->allowed_file_types,
        'max_upload_size_kb' => $document->max_upload_size_kb,
        'sort_order' => $document->sort_order,
    ])->all() ?? []);
@endphp


@include('admin.services._configuration')
<div><h2 class="h5 mb-1">Internal Processing Capabilities</h2><p class="text-muted small">Controls which drafting and registration sections apply to this service.</p></div>
<div class="row g-3">@foreach(['uses_drafting_workflow'=>'Drafting workflow','requires_token_booking'=>'Token booking','requires_registration'=>'Registration','requires_certified_copy'=>'Certified copy'] as $field=>$label)<div class="col-sm-6 col-lg-3"><input type="hidden" name="{{ $field }}" value="0"><div class="form-check"><input id="{{ $field }}" name="{{ $field }}" value="1" type="checkbox" class="form-check-input" @checked(old($field,$service?->{$field} ?? false))><label for="{{ $field }}" class="form-check-label">{{ $label }}</label></div></div>@endforeach</div>

<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Required Documents</h2><p class="text-muted small mb-0">Add only the documents required for this service.</p></div><button id="add-document" class="btn btn-outline-primary btn-sm" type="button">Add Document</button></div>
@error('documents')<div class="alert alert-danger">{{ $message }}</div>@enderror
<div id="document-rows" class="vstack gap-3">
    @foreach($documentRows as $index => $document)
        <div class="document-row border rounded p-3"><input type="hidden" name="documents[{{ $index }}][id]" value="{{ $document['id'] ?? '' }}"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">English Name</label><input name="documents[{{ $index }}][name_en]" value="{{ $document['name_en'] ?? '' }}" class="form-control @error("documents.{$index}.name_en") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.name_en"){{ $message }}@enderror</div></div><div class="col-md-5"><label class="form-label">Gujarati Name</label><input name="documents[{{ $index }}][name_gu]" value="{{ $document['name_gu'] ?? '' }}" class="form-control @error("documents.{$index}.name_gu") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.name_gu"){{ $message }}@enderror</div></div><div class="col-md-1"><label class="form-label">Order</label><input type="number" min="0" name="documents[{{ $index }}][sort_order]" value="{{ $document['sort_order'] ?? 0 }}" class="form-control @error("documents.{$index}.sort_order") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.sort_order"){{ $message }}@enderror</div></div><div class="col-md-3"><input type="hidden" name="documents[{{ $index }}][is_mandatory]" value="0"><label><input type="checkbox" name="documents[{{ $index }}][is_mandatory]" value="1" @checked($document['is_mandatory'] ?? true)> Mandatory</label></div><div class="col-md-5"><label class="form-label">Allowed Types</label><select multiple name="documents[{{ $index }}][allowed_file_types][]" class="form-select">@foreach(['pdf','jpg','jpeg','png','doc','docx'] as $type)<option value="{{ $type }}" @selected(in_array($type,$document['allowed_file_types'] ?? ['pdf','jpg','jpeg','png'],true))>{{ strtoupper($type) }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Max Size (KB)</label><input type="number" name="documents[{{ $index }}][max_upload_size_kb]" value="{{ $document['max_upload_size_kb'] ?? 10240 }}" class="form-control"></div><div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-document" type="button">Remove</button></div></div></div>
    @endforeach
</div>

<template id="document-template"><div class="document-row border rounded p-3"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">English Name</label><input data-name="name_en" class="form-control"></div><div class="col-md-5"><label class="form-label">Gujarati Name</label><input data-name="name_gu" class="form-control"></div><div class="col-md-1"><label class="form-label">Order</label><input data-name="sort_order" type="number" min="0" value="0" class="form-control"></div><div class="col-md-3"><input data-name="is_mandatory" type="hidden" value="0"><label class="form-check"><input data-name="is_mandatory" type="checkbox" value="1" checked> Mandatory</label></div><div class="col-md-5"><label class="form-label">Allowed Types</label><select data-name="allowed_file_types" multiple class="form-select">@foreach(['pdf','jpg','jpeg','png','doc','docx'] as $type)<option value="{{ $type }}" selected>{{ strtoupper($type) }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Max Size (KB)</label><input data-name="max_upload_size_kb" type="number" value="10240" class="form-control"></div><div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-document" type="button">Remove</button></div></div></div></template>

<div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit">{{ $submitLabel }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">Cancel</a></div>

@push('scripts')
<script>
    (() => {
        const rows = document.getElementById('document-rows');
        const template = document.getElementById('document-template');
        let documentIndex = {{ count($documentRows) }};

        document.getElementById('add-document').addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[data-name]').forEach((input) => {
                input.name = `documents[${documentIndex}][${input.dataset.name}]${input.dataset.name === 'allowed_file_types' ? '[]' : ''}`;
            });
            documentIndex++;
            rows.appendChild(fragment);
        });

        rows.addEventListener('click', (event) => {
            if (event.target.classList.contains('remove-document')) {
                event.target.closest('.document-row').remove();
            }
        });
    })();
</script>
@endpush

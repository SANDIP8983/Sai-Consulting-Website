@php
    $documentRows = old('documents', $service?->requiredDocuments->map(fn ($document) => [
        'name_en' => $document->name_en,
        'name_gu' => $document->name_gu,
        'sort_order' => $document->sort_order,
    ])->all() ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name_en" class="form-label">English Name</label>
        <input id="name_en" name="name_en" value="{{ old('name_en', $service?->name_en) }}" class="form-control @error('name_en') is-invalid @enderror" required>
        @error('name_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="name_gu" class="form-label">Gujarati Name</label>
        <input id="name_gu" name="name_gu" value="{{ old('name_gu', $service?->name_gu) }}" class="form-control @error('name_gu') is-invalid @enderror" required>
        @error('name_gu')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description', $service?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="sort_order" class="form-label">Display Order</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $service?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8 d-flex align-items-end">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check mb-2"><input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" @checked(old('is_active', $service?->is_active ?? true))><label class="form-check-label" for="is_active">Service is active and available for selection</label></div>
    </div>
</div>

<hr class="my-4">
<div><h2 class="h5 mb-1">Internal Processing Capabilities</h2><p class="text-muted small">Controls which drafting and registration sections apply to this service.</p></div>
<div class="row g-3">@foreach(['uses_drafting_workflow'=>'Drafting workflow','requires_token_booking'=>'Token booking','requires_registration'=>'Registration','requires_certified_copy'=>'Certified copy'] as $field=>$label)<div class="col-sm-6 col-lg-3"><input type="hidden" name="{{ $field }}" value="0"><div class="form-check"><input id="{{ $field }}" name="{{ $field }}" value="1" type="checkbox" class="form-check-input" @checked(old($field,$service?->{$field} ?? false))><label for="{{ $field }}" class="form-check-label">{{ $label }}</label></div></div>@endforeach</div>

<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Required Documents</h2><p class="text-muted small mb-0">Add only the documents required for this service.</p></div><button id="add-document" class="btn btn-outline-primary btn-sm" type="button">Add Document</button></div>
@error('documents')<div class="alert alert-danger">{{ $message }}</div>@enderror
<div id="document-rows" class="vstack gap-3">
    @foreach($documentRows as $index => $document)
        <div class="document-row border rounded p-3"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">English Name</label><input name="documents[{{ $index }}][name_en]" value="{{ $document['name_en'] ?? '' }}" class="form-control @error("documents.{$index}.name_en") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.name_en"){{ $message }}@enderror</div></div><div class="col-md-5"><label class="form-label">Gujarati Name</label><input name="documents[{{ $index }}][name_gu]" value="{{ $document['name_gu'] ?? '' }}" class="form-control @error("documents.{$index}.name_gu") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.name_gu"){{ $message }}@enderror</div></div><div class="col-md-1"><label class="form-label">Order</label><input type="number" min="0" name="documents[{{ $index }}][sort_order]" value="{{ $document['sort_order'] ?? 0 }}" class="form-control @error("documents.{$index}.sort_order") is-invalid @enderror"><div class="invalid-feedback">@error("documents.{$index}.sort_order"){{ $message }}@enderror</div></div><div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-document" type="button">Remove</button></div></div></div>
    @endforeach
</div>

<template id="document-template"><div class="document-row border rounded p-3"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">English Name</label><input data-name="name_en" class="form-control"></div><div class="col-md-5"><label class="form-label">Gujarati Name</label><input data-name="name_gu" class="form-control"></div><div class="col-md-1"><label class="form-label">Order</label><input data-name="sort_order" type="number" min="0" value="0" class="form-control"></div><div class="col-md-1"><button class="btn btn-outline-danger w-100 remove-document" type="button">Remove</button></div></div></div></template>

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
                input.name = `documents[${documentIndex}][${input.dataset.name}]`;
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

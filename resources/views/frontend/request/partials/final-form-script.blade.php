@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const services = {{ Illuminate\Support\Js::from($services->mapWithKeys(fn ($service) => [(string) $service->id => [
        'gu' => $service->name_gu,
        'en' => $service->name_en,
        'fee' => (float) $service->service_fee,
        'gst' => (float) $service->gst_rate,
        'government' => (float) ($service->activeGovernmentChargeItems->sum('amount') ?: $service->government_charges),
        'property' => (bool) $service->requires_property_documents,
        'days' => $service->estimated_days,
        'docs' => $service->activeRequiredDocuments->map(fn ($document) => [
            'id' => $document->id,
            'common' => $document->common_required_document_id,
            'gu' => $document->name_gu,
            'en' => $document->name_en,
            'type' => $document->requirement_type,
        ])->values(),
    ]])) }};
    const safe = value => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
    const panels = [...document.querySelectorAll('[data-step]')];
    const steps = [...document.querySelectorAll('.request-step')];
    const checks = [...document.querySelectorAll('.service-choice-input')];
    const money = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
    const selected = () => checks.filter(check => check.checked).map(check => services[check.value]);
    const strength = { optional: 1, any_one_required: 2, required: 3 };
    let step = 1;

    function mergedDocuments() {
        const merged = new Map();
        selected().flatMap(service => service.docs).forEach(document => {
            const key = document.common ? `common:${document.common}` : `mapping:${document.id}`;
            if (!merged.has(key) || strength[document.type] > strength[merged.get(key).type]) merged.set(key, document);
        });
        return [...merged.values()];
    }

    function documentRow(item, label) {
        const li = document.createElement('li');
        li.className = 'border rounded p-3 mb-2';
        li.innerHTML = `<div class="d-flex justify-content-between gap-2 mb-2"><span><strong>${safe(item.gu)}</strong><small class="d-block text-muted">${safe(item.en)}</small></span><span class="badge ${label === 'Required' ? 'text-bg-danger' : label === 'Any One Required' ? 'text-bg-warning' : 'text-bg-secondary'} align-self-start">${label}</span></div><input class="form-control document-upload-input" type="file" name="document_uploads[${item.id}]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-document-name="${safe(item.en)}" data-requirement-type="${item.type}" ${item.type === 'required' ? 'required' : ''}><div class="document-upload-status small text-muted mt-2">No file selected</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2 d-none remove-document-upload">Remove</button>`;
        return li;
    }

    function renderDocuments() {
        const groups = { required: new Map(), any_one_required: new Map(), optional: new Map() };
        mergedDocuments().forEach(document => groups[document.type]?.set(document.id, document));
        [['required-documents', groups.required, 'Required'], ['any-one-required-documents', groups.any_one_required, 'Any One Required'], ['optional-documents', groups.optional, 'Optional']]
            .forEach(([id, documents, label]) => {
                const list = document.getElementById(id);
                list.replaceChildren();
                documents.forEach(item => list.append(documentRow(item, label)));
                if (!documents.size) list.innerHTML = '<li class="text-muted">None configured</li>';
            });
    }

    function totals() {
        const items = selected();
        const professional = items.reduce((total, service) => total + service.fee, 0);
        const gst = items.reduce((total, service) => total + service.fee * service.gst / 100, 0);
        const government = items.reduce((total, service) => total + service.government, 0);
        document.getElementById('professional-total').textContent = money(professional);
        document.getElementById('gst-total').textContent = money(gst);
        document.getElementById('government-total').textContent = money(government);
        document.getElementById('grand-total').textContent = money(professional + gst + government);
        renderDocuments();
    }

    function mappedFiles() {
        return [...document.querySelectorAll('.document-upload-input')]
            .filter(input => input.files.length)
            .map(input => ({ name: input.dataset.documentName, file: input.files[0] }));
    }

    function review() {
        const items = selected();
        const files = mappedFiles();
        const days = Math.max(0, ...items.map(service => service.days || 0));
        const propertyCard = items.some(service => service.property) ? `<div class="col-md-6"><div class="review-card"><h3>Property Details</h3><div>${safe(document.getElementById('property_village').value)}, ${safe(document.getElementById('property_taluka').value)}, ${safe(document.getElementById('property_district').value)}</div></div></div>` : '';
        document.getElementById('request-review').innerHTML = `<div class="col-md-6"><div class="review-card"><h3>Customer Information</h3><strong>${safe(document.getElementById('name').value)}</strong><div>${safe(document.getElementById('mobile').value)}</div></div></div>${propertyCard}<div class="col-md-6"><div class="review-card"><h3>Selected Services</h3><ul>${items.map(service => `<li>${safe(service.gu)} / ${safe(service.en)}</li>`).join('')}</ul><div>Estimated: ${days || 'After review'} day(s)</div></div></div><div class="col-md-6"><div class="review-card"><h3>Fee Summary</h3><div>${document.getElementById('professional-total').textContent} Professional</div><div>${document.getElementById('gst-total').textContent} GST Extra</div><div>${document.getElementById('government-total').textContent} Government</div><strong>${document.getElementById('grand-total').textContent}</strong></div></div><div class="col-md-6"><div class="review-card"><h3>Documents Selected</h3>${files.length ? `<ul>${files.map(item => `<li><strong>${safe(item.name)}</strong> — ${safe(item.file.name)}</li>`).join('')}</ul>` : 'No documents selected'}</div></div>`;
    }

    function render() {
        panels.forEach(panel => panel.classList.toggle('d-none', +panel.dataset.step !== step));
        steps.forEach((item, index) => item.classList.toggle('active', index + 1 === step));
        document.getElementById('previous-step').classList.toggle('d-none', step === 1);
        document.getElementById('next-step').classList.toggle('d-none', step === 4);
        document.getElementById('submit-request').classList.toggle('d-none', step !== 4);
        if (step === 4) review();
    }

    const propertyInputs = ['property_village', 'property_taluka', 'property_district'].map(id => document.getElementById(id));
    function propertyRules() {
        const required = selected().some(service => service.property);
        document.getElementById('property-details-section').classList.toggle('d-none', !required);
        propertyInputs.forEach(input => input.required = required);
    }

    checks.forEach(check => check.addEventListener('change', () => { totals(); propertyRules(); }));
    document.querySelector('[data-step="3"]').addEventListener('change', event => {
        if (!event.target.matches('.document-upload-input')) return;
        const file = event.target.files[0];
        const row = event.target.closest('li');
        row.querySelector('.document-upload-status').innerHTML = file ? `<span class="text-success fw-semibold">✓ Selected</span> ${safe(file.name)}` : 'No file selected';
        row.querySelector('.remove-document-upload').classList.toggle('d-none', !file);
    });
    document.querySelector('[data-step="3"]').addEventListener('click', event => {
        if (!event.target.matches('.remove-document-upload')) return;
        const row = event.target.closest('li');
        const input = row.querySelector('.document-upload-input');
        input.value = '';
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.getElementById('next-step').addEventListener('click', () => {
        if (step === 1) {
            const invalid = panels[0].querySelector(':invalid');
            if (invalid) { invalid.reportValidity(); return; }
        }
        if (step === 2 && !selected().length) { alert('Please select at least one service.'); return; }
        if (step === 3) {
            const invalid = panels[2].querySelector(':invalid');
            if (invalid) { invalid.reportValidity(); return; }
            const anyOne = [...panels[2].querySelectorAll('[data-requirement-type="any_one_required"]')];
            if (anyOne.length && !anyOne.some(input => input.files.length)) { alert('Upload at least one document from the Any One Required group.'); return; }
            if (mappedFiles().length > 10) { alert('You may upload a maximum of 10 files.'); return; }
        }
        step++; render();
    });
    document.getElementById('previous-step').addEventListener('click', () => { step--; render(); });
    document.getElementById('final-request-form').addEventListener('submit', () => { const button = document.getElementById('submit-request'); button.disabled = true; button.textContent = 'Submitting securely...'; });
    propertyRules(); totals(); render();
});
</script>
@endpush

<div class="alert alert-info">
    Upload only finalized, customer-ready output documents here. Multiple files are allowed and remain in private storage. Email delivery is available now; WhatsApp delivery stays unavailable until provider activation.
</div>

@can('requests.manage')
<form method="POST" action="{{ route('admin.requests.final-documents.store', $customerRequest) }}" enctype="multipart/form-data" class="border rounded p-3 mb-4">
    @csrf
    <label for="final-documents" class="form-label fw-semibold">Upload Final Documents</label>
    <input id="final-documents" type="file" name="documents[]" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple required>
    <div class="form-text">PDF, DOC, DOCX, JPG, JPEG or PNG. Maximum {{ number_format(config('final-documents.max_file_size_kilobytes') / 1024) }} MB per file; up to {{ config('final-documents.max_files_per_upload') }} files per upload.</div>
    <button class="btn btn-primary mt-3" type="submit">Upload Privately</button>
</form>
@endcan

<form id="send-final-documents" method="POST" action="{{ route('admin.requests.final-documents.send', $customerRequest) }}">@csrf<input type="hidden" name="channel" value="email"></form>
<div class="table-responsive mb-3"><table class="table align-middle">
    <thead><tr><th scope="col">Send</th><th scope="col">Document</th><th scope="col">Type / Size</th><th scope="col">Uploaded</th><th scope="col">Actions</th></tr></thead>
    <tbody>@forelse($customerRequest->finalDocuments as $document)<tr>
        <td>@can('requests.manage')<input class="form-check-input" type="checkbox" name="document_ids[]" value="{{ $document->id }}" form="send-final-documents" aria-label="Select {{ $document->original_name }}">@endcan</td>
        <td><strong>{{ $document->original_name }}</strong>@if($document->deliveries_count)<div class="small text-success">Included in delivery audit</div>@else<div class="small text-muted">Not sent</div>@endif</td>
        <td>{{ $document->mime_type }}<br><span class="text-muted">{{ number_format($document->file_size / 1024, 1) }} KB</span></td>
        <td>{{ \App\Support\IndiaDateTime::format($document->created_at) }} IST<br><span class="text-muted">{{ $document->uploadedBy?->name ?? 'Former user' }}</span></td>
        <td><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.requests.final-documents.download', [$customerRequest, $document]) }}">Secure Download</a>@can('requests.manage')@if(!$document->deliveries_count)<form method="POST" action="{{ route('admin.requests.final-documents.destroy', [$customerRequest, $document]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form>@endif @endcan</div></td>
    </tr>@empty<tr><td colspan="5" class="text-muted text-center py-4">No final customer documents uploaded.</td></tr>@endforelse</tbody>
</table></div>

@can('requests.manage')<div class="d-flex flex-wrap gap-2 mb-4"><button class="btn btn-success" type="submit" form="send-final-documents" @disabled($customerRequest->finalDocuments->isEmpty())>Send Selected by Email</button><button class="btn btn-outline-secondary" type="button" disabled title="Available after MSG91/WhatsApp activation">Send by WhatsApp (Unavailable)</button></div>@endcan

<h3 class="h6">Delivery Audit</h3>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Channel</th><th>Documents</th><th>Recipient</th><th>Status</th><th>Initiated</th><th>Failure</th></tr></thead><tbody>
@forelse($customerRequest->finalDocumentDeliveries as $delivery)<tr><td>{{ str($delivery->channel)->title() }}</td><td>{{ $delivery->documents->pluck('original_name')->implode(', ') }}</td><td>{{ $delivery->recipient_masked }}</td><td>{{ str($delivery->status)->title() }}</td><td>{{ \App\Support\IndiaDateTime::format($delivery->created_at) }} IST<br><span class="text-muted">{{ $delivery->initiatedBy?->name ?? 'Former user' }}</span></td><td>{{ $delivery->failure_category ? str($delivery->failure_category)->replace('_', ' ')->title() : '—' }}</td></tr>
@empty<tr><td colspan="6" class="text-muted text-center">No final-document deliveries recorded.</td></tr>@endforelse
</tbody></table></div>

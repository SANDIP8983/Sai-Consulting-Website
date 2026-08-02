<div class="request-action-bar sticky-top bg-white border rounded shadow-sm p-3 mb-4" aria-label="Request actions">
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <strong>{{ $customerRequest->reference_no }}</strong>
            <span class="text-muted">{{ $customerRequest->file_number ?: 'File not assigned' }}</span>
            @include('admin.requests.partials.status-badge',['status'=>$customerRequest->status])
            @include('admin.requests.partials.payment-badge',['status'=>$customerRequest->payment_status])
            <span class="badge text-bg-light border">{{ $processingSummary['percentage'] }}% processed</span>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Workflow shortcuts">
            <a href="#section-file" class="btn btn-sm btn-outline-primary {{ $stickyActions['save']?'':'disabled' }}" @if(!$stickyActions['save']) aria-disabled="true" tabindex="-1" @endif>Save</a>
            <a href="#section-billing" class="btn btn-sm btn-success {{ $stickyActions['approve']?'':'disabled' }}" @if(!$stickyActions['approve']) aria-disabled="true" tabindex="-1" @endif>Approve</a>
            <a href="#payment-management" class="btn btn-sm btn-outline-success {{ $stickyActions['mark_paid']?'':'disabled' }}" @if(!$stickyActions['mark_paid']) aria-disabled="true" tabindex="-1" @endif>Mark Paid</a>
            <a href="#processing-checklist" class="btn btn-sm btn-outline-primary {{ $stickyActions['complete']?'':'disabled' }}" @if(!$stickyActions['complete']) aria-disabled="true" tabindex="-1" @endif>Complete Case</a>
            <a href="#dispatch-delivery" class="btn btn-sm btn-outline-secondary {{ $stickyActions['dispatch']?'':'disabled' }}" @if(!$stickyActions['dispatch']) aria-disabled="true" tabindex="-1" @endif>Dispatch</a>
            <a href="#workflow-actions" class="btn btn-sm btn-outline-dark {{ $stickyActions['archive']?'':'disabled' }}" @if(!$stickyActions['archive']) aria-disabled="true" tabindex="-1" @endif>Archive</a>
        </div>
    </div>
</div>

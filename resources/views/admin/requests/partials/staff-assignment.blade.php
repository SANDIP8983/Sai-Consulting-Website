<section class="border rounded p-3 mt-4" id="staff-assignment">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
        <div><h3 class="h6 mb-1">Request Staff Assignment</h3><div class="text-muted small">Processing access is limited to the assigned operational user.</div></div>
        <span class="badge text-bg-{{ $customerRequest->assignedUser?->is_active ? 'success' : 'warning' }}">{{ $customerRequest->assignedUser?->name ?? 'Unassigned' }}</span>
    </div>
    @if($customerRequest->assignedUser && !$customerRequest->assignedUser->is_active)<div class="alert alert-warning">The assigned user is inactive. Reassign this request before processing continues.</div>@endif
    @can('requests.assign')
        <form method="POST" action="{{ route('admin.requests.assignment.update',$customerRequest) }}" class="row g-2 align-items-end">@csrf @method('PUT')
            <div class="col-md-9"><label class="form-label" for="assigned_user_id">Active Processing User</label><select id="assigned_user_id" name="assigned_user_id" class="form-select" required><option value="">Select user</option>@foreach($assignableUsers as $user)<option value="{{ $user->id }}" @selected((string)old('assigned_user_id',$customerRequest->assigned_user_id)===(string)$user->id)>{{ $user->name }} ({{ str($user->role)->replace('_',' ')->title() }})</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-primary w-100">{{ $customerRequest->assigned_user_id ? 'Reassign Staff' : 'Assign Staff' }}</button></div>
        </form>
    @endcan
    @if($customerRequest->assignmentHistory->isNotEmpty())<div class="table-responsive mt-3"><table class="table table-sm mb-0"><thead><tr><th>Assigned At</th><th>Previous</th><th>Assigned Staff</th><th>Assigned By</th></tr></thead><tbody>@foreach($customerRequest->assignmentHistory as $history)<tr><td>{{ $history->assigned_at->format('d M Y, g:i A') }}</td><td>{{ $history->previousAssignee?->name ?? 'Unassigned' }}</td><td>{{ $history->assignee?->name ?? 'Unavailable' }}</td><td>{{ $history->assignedBy?->name ?? 'Unavailable' }}</td></tr>@endforeach</tbody></table></div>@endif
</section>

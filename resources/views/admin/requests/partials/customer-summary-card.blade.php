<div class="row g-3">
@foreach([
    ['Customer Name',$customerRequest->name],['Mobile',$customerRequest->mobile],['WhatsApp',$customerRequest->whatsapp ?: 'Uses Mobile fallback'],['Email',$customerRequest->email ?: 'Not provided'],
    ['Reference Number',$customerRequest->reference_no],['File Number',$customerRequest->file_number ?: 'Not assigned'],
    ['Submission Date',$customerRequest->created_at->format('d M Y, g:i A')],['Request Source',$customerRequest->isOffline()?'Offline':'Online'],
    ['Village',$customerRequest->property_village ?: $customerRequest->village ?: 'Not provided'],['Survey Number',$customerRequest->survey_numbers ?: 'Not provided'],
    ['Current Status',str($customerRequest->status)->headline()],
] as [$label,$value])
<div class="col-sm-6 col-xl-4"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $value }}</strong></div>
@endforeach
</div>
@can('requests.manage')
<hr class="my-4"><h3 class="h5">Customer Contact / ગ્રાહક સંપર્ક</h3><p class="small text-muted">Updates apply only to future notification milestones. Existing notification history is not resent.</p>
<form method="POST" action="{{ route('admin.requests.contact.update',$customerRequest) }}" class="row g-3">@csrf @method('PATCH')
<div class="col-md-4"><label class="form-label" for="contact_mobile">Mobile Number</label><input id="contact_mobile" name="mobile" value="{{ old('mobile',$customerRequest->mobile) }}" maxlength="10" inputmode="numeric" class="form-control @error('mobile') is-invalid @enderror" required>@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label" for="contact_whatsapp">WhatsApp Number <span class="text-muted">(Optional)</span></label><input id="contact_whatsapp" name="whatsapp" value="{{ old('whatsapp',$customerRequest->whatsapp) }}" maxlength="10" inputmode="numeric" class="form-control @error('whatsapp') is-invalid @enderror">@error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-4"><label class="form-label" for="contact_email">Email Address <span class="text-muted">(Optional)</span></label><input id="contact_email" type="email" name="email" value="{{ old('email',$customerRequest->email) }}" maxlength="255" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-12"><button class="btn btn-primary">Update Customer Contact</button></div></form>
@if($customerRequest->contactChangeHistory->isNotEmpty())<div class="mt-4"><h4 class="h6">Contact Change History</h4><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Changed At</th><th>Fields</th><th>Previous</th><th>Updated</th><th>Changed By</th></tr></thead><tbody>@foreach($customerRequest->contactChangeHistory as $change)<tr><td>{{ $change->changed_at->format('d M Y, g:i A') }}</td><td>{{ collect($change->changed_fields)->map(fn($field)=>str($field)->headline())->implode(', ') }}</td><td>@foreach($change->masked_old_values as $field=>$value)<div>{{ str($field)->headline() }}: {{ $value ?: 'Not provided' }}</div>@endforeach</td><td>@foreach($change->masked_new_values as $field=>$value)<div>{{ str($field)->headline() }}: {{ $value ?: 'Not provided' }}</div>@endforeach</td><td>{{ $change->changedBy->name }}</td></tr>@endforeach</tbody></table></div></div>@endif
@endcan

@php
    $anyOne = $documents->where('requirement_type', 'any_one_required');
    $required = $documents->where('requirement_type', 'required');
    $optional = $documents->where('requirement_type', 'optional');
@endphp
@if($anyOne->isNotEmpty())
<div class="mb-4"><h3 class="h6">આમાંથી કોઈ એક જરૂરી <small class="text-muted">Any One Required</small></h3><ul class="required-document-grid service-document-list list-unstyled mb-0">@foreach($anyOne as $document)<li class="required-document document-required"><span aria-hidden="true"><i class="bi bi-files"></i></span><div><strong>{{ $document->name_gu }}</strong><small>{{ $document->name_en }} <span class="badge text-bg-warning">Any One Required</span></small></div></li>@endforeach</ul></div>
@endif
@if($required->isNotEmpty())
<div class="mb-4"><h3 class="h6">ફરજિયાત દસ્તાવેજો <small class="text-muted">Required Documents</small></h3><ul class="required-document-grid service-document-list list-unstyled mb-0">@foreach($required as $document)<li class="required-document document-required"><span aria-hidden="true"><i class="bi bi-file-earmark-check"></i></span><div><strong>{{ $document->name_gu }}</strong><small>{{ $document->name_en }} <span class="badge text-bg-danger">Required</span></small></div></li>@endforeach</ul></div>
@endif
@if($optional->isNotEmpty())
<div><h3 class="h6">વૈકલ્પિક દસ્તાવેજો <small class="text-muted">Optional Documents</small></h3><ul class="required-document-grid service-document-list list-unstyled mb-0">@foreach($optional as $document)<li class="required-document document-optional"><span aria-hidden="true"><i class="bi bi-file-earmark-plus"></i></span><div><strong>{{ $document->name_gu }}</strong><small>{{ $document->name_en }} <span class="badge text-bg-secondary">Optional</span></small></div></li>@endforeach</ul></div>
@endif

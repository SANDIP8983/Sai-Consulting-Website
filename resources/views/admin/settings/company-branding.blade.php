@extends('layouts.admin')

@section('title', 'Company Information & Branding')
@section('breadcrumbs')<li class="breadcrumb-item">Settings</li><li class="breadcrumb-item active">Company & Branding</li>@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h2 mb-1">Company Information &amp; Branding</h1><p class="text-muted mb-0">કંપનીની માહિતી અને બ્રાન્ડિંગ</p></div></div>
    @include('admin.settings._navigation')
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">Please correct the highlighted settings fields.</div>@endif

    <form method="POST" action="{{ route('admin.settings.company-branding.update') }}" enctype="multipart/form-data" data-confirm-remove>@csrf @method('PUT')
        <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Company Information / કંપની માહિતી</h2></div><div class="card-body"><div class="row g-3">
            @foreach([
                ['business_name','Business Name / વ્યવસાયનું નામ','text','Sai Consulting'],
                ['tagline','Tagline / ટૅગલાઇન','text','Documentation & Consulting'],
                ['mobile','Mobile / મોબાઇલ','tel',''],
                ['whatsapp','WhatsApp / વોટ્સએપ','tel',''],
                ['email','Email / ઇમેઇલ','email',''],
                ['website_url','Website / વેબસાઇટ','url','https://'],
                ['gstin','GSTIN','text','24ABCDE1234F1Z5'],
            ] as [$field,$label,$type,$placeholder])
                <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field,$settings[$field]) }}" placeholder="{{ $placeholder }}" class="form-control @error($field) is-invalid @enderror">@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endforeach
            <div class="col-12"><label class="form-label" for="address">Address / સરનામું</label><textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address',$settings['address']) }}</textarea>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><div class="form-text">GSTIN is retained as private configuration and is not added to ordinary public pages.</div></div>
        </div></div></section>

        <section class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Branding Assets / બ્રાન્ડિંગ</h2></div><div class="card-body"><p class="text-muted">PNG, JPG, JPEG or WEBP. Maximum 2 MB per image. Uploading a replacement creates a new file and preserves the previous stored file.</p><div class="row g-4">
            @foreach([
                'primary_logo'=>['Primary Logo','primary-logo',false], 'dark_logo'=>['Dark Logo','dark-logo',false],
                'favicon'=>['Favicon','favicon',false], 'pdf_logo'=>['PDF Logo','pdf-logo',true],
                'stamp'=>['Stamp Image','stamp',true], 'signature'=>['Signature Image','signature',true],
            ] as $upload => [$label,$asset,$private])
                @php($pathField=$upload.'_path')
                <div class="col-md-6 col-xl-4"><div class="border rounded-3 p-3 h-100">
                    <h3 class="h6">{{ $label }}</h3>
                    <div class="bg-light border rounded d-flex align-items-center justify-content-center mb-3" style="height:130px"><img id="preview-{{ $upload }}" @if($settings[$pathField]) src="{{ route('admin.settings.branding.asset',$asset) }}?v={{ urlencode($settings[$pathField]) }}" @endif alt="{{ $label }} preview" class="img-fluid p-2 {{ $settings[$pathField] ? '' : 'd-none' }}" style="max-height:125px"><span id="empty-{{ $upload }}" class="text-muted small {{ $settings[$pathField] ? 'd-none' : '' }}">No image configured</span></div>
                    <label class="form-label" for="{{ $upload }}">Replace {{ $label }}</label><input id="{{ $upload }}" name="{{ $upload }}" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" class="form-control branding-file @error($upload) is-invalid @enderror" data-preview="preview-{{ $upload }}" data-empty="empty-{{ $upload }}">@error($upload)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($settings[$pathField])<div class="form-check mt-3"><input type="hidden" name="remove_{{ $upload }}" value="0"><input id="remove_{{ $upload }}" name="remove_{{ $upload }}" value="1" type="checkbox" class="form-check-input remove-asset"><label for="remove_{{ $upload }}" class="form-check-label text-danger">Remove configured image</label></div>@endif
                </div></div>
            @endforeach
        </div></div></section>

        <div class="sticky-bottom bg-white border rounded shadow-sm p-3 d-flex justify-content-end"><button class="btn btn-primary px-4">Save Company &amp; Branding</button></div>
    </form>
</div>
@endsection

@push('scripts')<script>document.querySelectorAll('.branding-file').forEach(input=>input.addEventListener('change',()=>{const file=input.files?.[0],preview=document.getElementById(input.dataset.preview),empty=document.getElementById(input.dataset.empty);if(!file)return;const reader=new FileReader();reader.onload=event=>{preview.src=event.target.result;preview.classList.remove('d-none');empty.classList.add('d-none')};reader.readAsDataURL(file)}));document.querySelector('[data-confirm-remove]')?.addEventListener('submit',event=>{if(document.querySelectorAll('.remove-asset:checked').length&&!confirm('Remove the selected branding image references?'))event.preventDefault()});</script>@endpush

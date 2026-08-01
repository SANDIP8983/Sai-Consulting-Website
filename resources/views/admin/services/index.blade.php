@extends('layouts.admin')

@section('title', 'Services')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Services</li>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Services</h1>
        <p class="text-muted mb-0">Manage service information and required documents.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.services.create') }}">Add Service</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
<form method='GET' class='card card-body mb-4'><div class='row g-2'><div class='col-md-5'><input name='q' value='{{ request('q') }}' class='form-control' placeholder='Search services'></div><div class='col-md-3'><select name='active' class='form-select'><option value=''>All statuses</option><option value='1'>Active</option><option value='0'>Inactive</option></select></div><div class='col-md-3'><select name='availability' class='form-select'><option value=''>All channels</option><option value='online'>Online</option><option value='offline'>Offline</option></select></div><div class='col-md-1'><button class='btn btn-primary'>Go</button></div></div></form>
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Service</th>
                    <th>Slug</th>
                    <th>Fee / Time</th>
                    <th>Availability</th>
                    <th>Order</th>
                    <th>Required Documents</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
                        <td><strong>{{ $service->name_en }}</strong><div class="small text-muted">{{ $service->name_gu }}</div></td>
                        <td><code>{{ $service->slug }}</code></td>
                        <td>{{ $service->service_fee !== null ? '₹'.number_format((float) $service->service_fee, 2) : '—' }}<div class='small text-muted'>{{ $service->estimated_days !== null ? $service->estimated_days.' day(s)' : 'No estimate' }}</div></td>
                        <td><span class='badge text-bg-primary'>{{ $service->available_online ? 'Online' : 'Not online' }}</span> <span class='badge text-bg-info'>{{ $service->available_offline ? 'Offline' : 'Not offline' }}</span></td>
                        <td>{{ $service->sort_order }}</td>
                        <td>{{ $service->required_documents_count }}</td>
                        <td><span class="badge text-bg-{{ $service->is_active ? 'success' : 'secondary' }}">{{ $service->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.services.edit', $service) }}">Edit</a>
                            <form class="d-inline" method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirm('Delete this service and its required documents?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No services have been added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($services->hasPages())
        <div class="card-body border-top">{{ $services->links() }}</div>
    @endif
</div>
@endsection

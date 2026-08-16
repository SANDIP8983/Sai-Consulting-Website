<!DOCTYPE html>
<html lang="en">
<body style="font-family:Arial,sans-serif;color:#1f2937">
    <h1 style="font-size:20px">New Customer Request</h1>
    <p>A new online customer request was submitted successfully.</p>
    <dl>
        <dt><strong>Reference Number</strong></dt><dd>{{ $customerRequest->reference_no }}</dd>
        <dt><strong>Customer Name</strong></dt><dd>{{ $customerRequest->name }}</dd>
        <dt><strong>Registered Mobile Number</strong></dt><dd>{{ $customerRequest->mobile }}</dd>
        @if($customerRequest->email)<dt><strong>Customer Email</strong></dt><dd>{{ $customerRequest->email }}</dd>@endif
        <dt><strong>Selected Services</strong></dt><dd>{{ $customerRequest->requestServices->pluck('service_name_en_snapshot')->filter()->implode(', ') }}</dd>
        @if($customerRequest->property_village)<dt><strong>Village</strong></dt><dd>{{ $customerRequest->property_village }}</dd>@endif
        @if($customerRequest->property_taluka)<dt><strong>Taluka</strong></dt><dd>{{ $customerRequest->property_taluka }}</dd>@endif
        @if($customerRequest->property_district)<dt><strong>District</strong></dt><dd>{{ $customerRequest->property_district }}</dd>@endif
        <dt><strong>Submitted</strong></dt><dd>{{ $customerRequest->created_at->timezone('Asia/Kolkata')->format('d M Y, g:i A') }} IST</dd>
    </dl>
    <p><a href="{{ route('admin.requests.show', $customerRequest) }}">Open this request in the Admin Panel</a></p>
    <p>No customer-uploaded documents are attached to this email.</p>
</body>
</html>

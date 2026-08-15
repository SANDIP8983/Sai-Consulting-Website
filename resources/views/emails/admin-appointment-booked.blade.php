<!DOCTYPE html>
<html lang="en">
<body style="font-family:Arial,sans-serif;color:#1f2937">
    <h1 style="font-size:20px">New appointment request</h1>
    <p>A customer submitted a new appointment request.</p>
    <dl>
        <dt><strong>Reference</strong></dt><dd>{{ $appointment->reference_no }}</dd>
        <dt><strong>Customer</strong></dt><dd>{{ $appointment->customer_name }}</dd>
        <dt><strong>Mobile</strong></dt><dd>{{ $appointment->mobile }}</dd>
        <dt><strong>Service</strong></dt><dd>{{ $appointment->service->name_en }}</dd>
        <dt><strong>Requested time</strong></dt><dd>{{ $appointment->scheduled_at->format('d M Y, g:i A') }} (Asia/Kolkata)</dd>
    </dl>
    <p>Sign in to the admin panel to review and confirm the appointment.</p>
</body>
</html>

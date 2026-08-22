<!doctype html>
<html lang="gu">
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
<div style="max-width:620px;margin:auto;padding:24px">
    <h1 style="font-size:20px;color:#0b3b82">Sai Consulting</h1>
    @foreach(explode("\n", $messageData['body']) as $line)
        <p>{{ $line }}</p>
    @endforeach

    @if(($messageData['accepted_payment_required'] ?? null) === true)
        <div style="margin:24px 0;padding:18px;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb">
            <h2 style="margin:0 0 8px;font-size:18px;color:#92400e">ચૂકવણી બાકી છે / Payment Pending</h2>
            <p style="margin:0 0 8px">તમારી અરજીની આગળની પ્રક્રિયા માટે કૃપા કરીને બાકી રકમની ચૂકવણી કરો.</p>
            <p style="margin:0 0 8px">Payment is required before normal processing proceeds.</p>
            <p style="margin:0;font-size:18px"><strong>ચૂકવવાની બાકી રકમ: ₹{{ number_format((float) $messageData['outstanding_amount'], 2) }}</strong></p>
            <p style="margin:8px 0 0">ચૂકવણી કરવા તથા વિગતો જોવા માટે નીચેની Tracking Link નો ઉપયોગ કરો.</p>
        </div>
    @elseif(($messageData['accepted_payment_required'] ?? null) === false)
        <div style="margin:24px 0;padding:18px;border:1px solid #22c55e;border-radius:8px;background:#f0fdf4">
            <h2 style="margin:0 0 8px;font-size:18px;color:#166534">ચૂકવણી જરૂરી નથી.</h2>
            <p style="margin:0">તમારી અરજી આગળની પ્રક્રિયા માટે સ્વીકારવામાં આવી છે.</p>
            <p style="margin:8px 0 0">No payment is required. Your request can proceed to processing.</p>
        </div>
    @endif

    @if(!empty($messageData['tracking_url']))
        <p><a href="{{ $messageData['tracking_url'] }}" style="display:inline-block;padding:11px 18px;border-radius:6px;background:#0b3b82;color:#fff;text-decoration:none">Track Request / અરજી ટ્રેક કરો</a></p>
    @endif

    <p style="font-size:12px;color:#6b7280">આ સ્વચાલિત સ્થિતિ સૂચના છે. કૃપા કરીને આ ઈમેલમાં સંવેદનશીલ દસ્તાવેજો મોકલશો નહીં.</p>
</div>
</body>
</html>

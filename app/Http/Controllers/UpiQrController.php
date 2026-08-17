<?php

namespace App\Http\Controllers;

use App\Services\PaymentSettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpiQrController extends Controller
{
    public function __invoke(PaymentSettingsService $settings): BinaryFileResponse|Response
    {
        $path = $settings->qrPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}

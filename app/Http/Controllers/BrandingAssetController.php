<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingAssetController extends Controller
{
    private const KEYS = [
        'primary-logo' => 'branding.primary_logo_path', 'dark-logo' => 'branding.dark_logo_path',
        'favicon' => 'branding.favicon_path', 'pdf-logo' => 'branding.pdf_logo_path',
        'stamp' => 'branding.stamp_path', 'signature' => 'branding.signature_path',
    ];

    public function publicAsset(string $asset): BinaryFileResponse|Response
    {
        abort_unless(in_array($asset, ['primary-logo', 'dark-logo', 'favicon'], true), 404);

        return $this->serve($asset);
    }

    public function privateAsset(string $asset): BinaryFileResponse|Response
    {
        return $this->serve($asset);
    }

    private function serve(string $asset): BinaryFileResponse|Response
    {
        $key = self::KEYS[$asset] ?? null;
        abort_unless($key, 404);
        $path = Setting::query()->where('setting_key', $key)->value('setting_value');
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

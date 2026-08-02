<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class BrandingAssetService
{
    /** @var array<string, string> */
    public const ASSETS = [
        'primary_logo' => 'primary_logo_path', 'dark_logo' => 'dark_logo_path',
        'favicon' => 'favicon_path', 'pdf_logo' => 'pdf_logo_path',
        'stamp' => 'stamp_path', 'signature' => 'signature_path',
    ];

    /** @param array<string, mixed> $validated @return array<string, string|null> */
    public function updatedPaths(array $validated, array $current): array
    {
        $paths = [];
        foreach (self::ASSETS as $upload => $setting) {
            $file = $validated[$upload] ?? null;
            if ($file instanceof UploadedFile) {
                $extension = strtolower($file->guessExtension() ?: $file->extension());
                $paths[$setting] = $file->storeAs('settings/branding', Str::uuid().'.'.$extension, 'local');
            } elseif ((bool) ($validated['remove_'.$upload] ?? false)) {
                $paths[$setting] = null;
            } else {
                $paths[$setting] = $current[$setting] ?? null;
            }
        }

        return $paths;
    }
}

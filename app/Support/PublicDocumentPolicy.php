<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PublicDocumentPolicy
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public const MAX_SIZE_KILOBYTES = 10240;

    public const PROHIBITED_TERMS = ['aadhaar', 'aadhar', 'pan card', 'passport', 'voter id', 'driving licence', 'driving license', 'bank passbook', 'bank statement', 'cheque book', 'check book', 'atm card', 'credit card', 'debit card', 'income proof', 'salary slip', 'identity proof', 'address proof', 'kyc'];

    /**
     * This is a layered upload safeguard, not content-level identity-document detection.
     * Renamed arbitrary documents cannot be identified reliably without OCR/manual review,
     * so public warnings, declarations, private storage, and operational review remain required.
     *
     * @param  Collection<int, mixed>  $services
     * @return array{extensions: array<int, string>, max_kilobytes: int}
     */
    public static function restrictionsForServices(Collection $services): array
    {
        $documents = $services->flatMap->activeRequiredDocuments;
        $configuredExtensions = $documents
            ->flatMap(fn ($document) => $document->allowed_file_types ?? [])
            ->map(fn ($extension) => strtolower((string) $extension))
            ->unique()
            ->values();
        $configuredSizes = $documents->pluck('max_upload_size_kb')
            ->filter(fn ($size) => is_numeric($size) && (int) $size > 0)
            ->map(fn ($size) => (int) $size);

        $extensions = $configuredExtensions->isEmpty()
            ? self::ALLOWED_EXTENSIONS
            : $configuredExtensions->intersect(self::ALLOWED_EXTENSIONS)->values()->all();

        return [
            'extensions' => $extensions,
            'max_kilobytes' => min(self::MAX_SIZE_KILOBYTES, $configuredSizes->min() ?? self::MAX_SIZE_KILOBYTES),
        ];
    }

    /** @param array{extensions: array<int, string>, max_kilobytes: int} $restrictions */
    public static function violation(UploadedFile $file, array $restrictions): ?string
    {
        $originalName = $file->getClientOriginalName();
        if (self::hasUnsafePathLikeName($file->getClientOriginalPath())) {
            return 'unsafe_filename';
        }
        if (! self::isSafe($originalName)) {
            return 'prohibited_filename';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || ! in_array($extension, $restrictions['extensions'], true)) {
            return 'extension_not_allowed';
        }
        if (($file->getSize() ?: 0) > $restrictions['max_kilobytes'] * 1024) {
            return 'file_too_large';
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return 'mime_not_allowed';
        }
        if (! self::extensionMatchesMime($extension, $mime)) {
            return 'extension_mime_mismatch';
        }
        if (! self::signatureMatchesMime($file, $mime)) {
            return 'content_signature_mismatch';
        }
        if ($mime === 'application/pdf' && self::hasPotentiallyActivePdfContent($file)) {
            return 'potentially_active_pdf';
        }

        return null;
    }

    /** @param array{extensions: array<int, string>, max_kilobytes: int} $restrictions */
    public static function assertAcceptable(UploadedFile $file, array $restrictions): void
    {
        if ($violation = self::violation($file, $restrictions)) {
            throw ValidationException::withMessages([
                'documents' => 'One or more documents do not meet the public upload safety policy.',
            ]);
        }
    }

    public static function storageExtension(UploadedFile $file): string
    {
        return match (strtolower((string) $file->getMimeType())) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw ValidationException::withMessages([
                'documents' => 'One or more documents do not meet the public upload safety policy.',
            ]),
        };
    }

    public static function safeDisplayName(UploadedFile $file): string
    {
        return str($file->getClientOriginalName())
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->limit(255, '')
            ->value();
    }

    public static function isSafe(string $name): bool
    {
        $normalized = str($name)->lower()->replace(['-', '_'], ' ')->squish()->value();

        return collect(self::PROHIBITED_TERMS)->doesntContain(fn (string $term) => str_contains($normalized, $term));
    }

    private static function hasUnsafePathLikeName(string $name): bool
    {
        return str_contains($name, '/')
            || str_contains($name, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || in_array($name, ['.', '..'], true);
    }

    private static function extensionMatchesMime(string $extension, string $mime): bool
    {
        return match ($mime) {
            'application/pdf' => $extension === 'pdf',
            'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true),
            'image/png' => $extension === 'png',
            default => false,
        };
    }

    private static function signatureMatchesMime(UploadedFile $file, string $mime): bool
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            return false;
        }
        try {
            $header = fread($stream, 8);
        } finally {
            fclose($stream);
        }

        return match ($mime) {
            'application/pdf' => str_starts_with((string) $header, '%PDF-'),
            'image/jpeg' => str_starts_with((string) $header, "\xFF\xD8\xFF"),
            'image/png' => $header === "\x89PNG\r\n\x1A\n",
            default => false,
        };
    }

    private static function hasPotentiallyActivePdfContent(UploadedFile $file): bool
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            return true;
        }

        return preg_match('/\/(JavaScript|JS|Launch|EmbeddedFile)\b/i', $contents) === 1;
    }
}

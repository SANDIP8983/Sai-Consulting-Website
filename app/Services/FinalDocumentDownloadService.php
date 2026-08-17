<?php

namespace App\Services;

use App\Models\RequestFinalDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinalDocumentDownloadService
{
    private const ALLOWED_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    public function download(RequestFinalDocument $document): BinaryFileResponse
    {
        $relativePath = str_replace('\\', '/', trim($document->storage_path));
        abort_if($relativePath === '' || str_starts_with($relativePath, '/') || preg_match('/(^|\/)\.\.($|\/)/', $relativePath), 404);

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $absolutePath = realpath($disk->path($relativePath));
        abort_unless($root !== false && $absolutePath !== false && $this->isWithinDisk($absolutePath, $root), 404);
        abort_unless(is_file($absolutePath) && is_readable($absolutePath), 404);

        $mimeType = File::mimeType($absolutePath);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        abort_unless(in_array($mimeType, self::ALLOWED_MIME_TYPES[$extension] ?? [], true), 404);

        return response()->download($absolutePath, $this->safeFilename($document), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function isWithinDisk(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $root = rtrim(strtolower(str_replace('\\', '/', $root)), '/');

        return str_starts_with($path, $root.'/');
    }

    private function safeFilename(RequestFinalDocument $document): string
    {
        $filename = basename(str_replace('\\', '/', $document->original_name));
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $filename));

        return $filename !== '' && ! in_array($filename, ['.', '..'], true)
            ? $filename
            : 'final-document-'.$document->id;
    }
}

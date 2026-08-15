<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\RequestDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RequestDocumentController extends Controller
{
    public function __invoke(CustomerRequest $customerRequest, RequestDocument $document): BinaryFileResponse
    {
        abort_unless($document->request_id === $customerRequest->id, 404);

        $relativePath = str_replace('\\', '/', trim($document->file_path));
        abort_if($relativePath === '' || str_starts_with($relativePath, '/') || preg_match('/(^|\/)\.\.($|\/)/', $relativePath), 404);

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $absolutePath = realpath($disk->path($relativePath));
        abort_unless($root !== false && $absolutePath !== false && $this->isWithinDisk($absolutePath, $root), 404);
        abort_unless(is_file($absolutePath) && is_readable($absolutePath), 404);

        $mimeType = File::mimeType($absolutePath);
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return response()->download($absolutePath, $this->safeFilename($document, $absolutePath), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isWithinDisk(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $root = rtrim(strtolower(str_replace('\\', '/', $root)), '/');

        return str_starts_with($path, $root.'/');
    }

    private function safeFilename(RequestDocument $document, string $absolutePath): string
    {
        $filename = basename(str_replace('\\', '/', $document->file_name));
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $filename));

        return $filename !== '' && ! in_array($filename, ['.', '..'], true)
            ? $filename
            : 'document-'.$document->id.'.'.strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    }
}

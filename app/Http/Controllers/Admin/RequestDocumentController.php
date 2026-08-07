<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\RequestDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestDocumentController extends Controller
{
    public function __invoke(CustomerRequest $customerRequest, RequestDocument $document): StreamedResponse
    {
        abort_unless($document->request_id === $customerRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}

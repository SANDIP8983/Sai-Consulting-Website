<?php

namespace App\Services;

use App\Jobs\SendFinalDocumentDeliveryJob;
use App\Models\CustomerRequest;
use App\Models\RequestFinalDocument;
use App\Models\RequestFinalDocumentDelivery;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinalDocumentDeliveryService
{
    /** @param array<int, UploadedFile> $files */
    public function upload(CustomerRequest $request, array $files, User $user): Collection
    {
        $storedPaths = [];
        try {
            return DB::transaction(function () use ($request, $files, $user, &$storedPaths): Collection {
                return collect($files)->map(function (UploadedFile $file) use ($request, $user, &$storedPaths): RequestFinalDocument {
                    $extension = strtolower($file->getClientOriginalExtension());
                    if (! in_array($extension, config('final-documents.allowed_extensions'), true)) {
                        throw ValidationException::withMessages(['documents' => 'One or more final documents have an unsupported extension.']);
                    }
                    $storedName = Str::uuid().'.'.$extension;
                    $path = $file->storeAs("customer-requests/{$request->id}/final-documents", $storedName, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('A final document could not be stored.');
                    }
                    $storedPaths[] = $path;

                    return $request->finalDocuments()->create([
                        'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                        'storage_path' => $path,
                        'mime_type' => (string) $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $user->id,
                    ]);
                });
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    public function delete(CustomerRequest $request, RequestFinalDocument $document): void
    {
        abort_unless($document->request_id === $request->id, 404);
        if ($document->deliveries()->exists()) {
            throw ValidationException::withMessages(['final_document' => 'A document included in a delivery audit cannot be removed.']);
        }

        $path = $document->storage_path;
        DB::transaction(function () use ($document, $path): void {
            $document->delete();
            DB::afterCommit(function () use ($path): void {
                if (! Storage::disk('local')->delete($path)) {
                    Log::warning('Deleted final document record left an orphaned private file.', ['path_hash' => hash('sha256', $path)]);
                }
            });
        });
    }

    /** @param array<int, int|string> $documentIds */
    public function queueEmail(CustomerRequest $request, array $documentIds, User $user): RequestFinalDocumentDelivery
    {
        $recipient = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? strtolower($request->email) : null;
        if (! $recipient) {
            throw ValidationException::withMessages(['customer_email' => 'Add a valid customer email address before sending final documents.']);
        }

        $ids = collect($documentIds)->map(fn ($id): int => (int) $id)->unique()->sort()->values();

        return DB::transaction(function () use ($request, $ids, $recipient, $user): RequestFinalDocumentDelivery {
            $documents = RequestFinalDocument::query()->where('request_id', $request->id)->whereIn('id', $ids)->lockForUpdate()->get();
            if ($documents->count() !== $ids->count()) {
                throw ValidationException::withMessages(['document_ids' => 'Select only final documents belonging to this request.']);
            }

            $idempotencyKey = hash('sha256', implode('|', [$request->id, 'email', strtolower($recipient), $ids->implode(',')]));
            try {
                $delivery = RequestFinalDocumentDelivery::query()->create([
                    'request_id' => $request->id,
                    'channel' => 'email',
                    'status' => 'pending',
                    'recipient_masked' => $this->maskEmail($recipient),
                    'recipient_hash' => hash('sha256', strtolower($recipient)),
                    'idempotency_key' => $idempotencyKey,
                    'initiated_by' => $user->id,
                    'queued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['document_ids' => 'This exact document delivery has already been queued.']);
            }
            $delivery->documents()->attach($ids->all());
            DB::afterCommit(fn () => SendFinalDocumentDeliveryJob::dispatch($delivery->id)->onQueue(config('customer-notifications.queue')));

            return $delivery->load('documents');
        });
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        return Str::limit($name !== '' && ! in_array($name, ['.', '..'], true) ? $name : 'final-document', 255, '');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 1).'***@'.$domain;
    }
}

<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\FileNumberSequence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FileNumberService
{
    public function assign(CustomerRequest $request): string
    {
        if ($request->file_number) {
            return $request->file_number;
        }

        $year = (int) now()->format('Y');

        return Cache::lock("requests:file-number:{$year}", 10)->block(5, function () use ($request, $year): string {
            return DB::transaction(function () use ($request, $year): string {
                $lockedRequest = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);

                if ($lockedRequest->file_number) {
                    $request->setAttribute('file_number', $lockedRequest->file_number);

                    return $lockedRequest->file_number;
                }

                FileNumberSequence::query()->firstOrCreate(['year' => $year], ['last_number' => 0]);
                $sequence = FileNumberSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
                $sequence->increment('last_number');
                $fileNumber = sprintf('SC/%d/F%06d', $year, $sequence->last_number);
                $lockedRequest->update(['file_number' => $fileNumber]);
                $request->setAttribute('file_number', $fileNumber);

                return $fileNumber;
            });
        });
    }
}

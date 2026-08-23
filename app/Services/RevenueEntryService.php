<?php

namespace App\Services;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Models\RequestRevenueEntry;
use App\Models\RequestRevenueEntryDocument;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RevenueEntryService
{
    public function __construct(private readonly CustomerNotificationService $notifications) {}

    private const TRANSITIONS = [
        'not_started' => ['application_preparation', 'cancelled'],
        'application_preparation' => ['submitted', 'cancelled'],
        'submitted' => ['entry_number_received', 'certification_pending', 'rejected', 'cancelled'],
        'entry_number_received' => ['certification_pending', 'certified', 'rejected', 'cancelled'],
        'certification_pending' => ['certified', 'rejected', 'cancelled'],
        'rejected' => ['application_preparation', 'submitted', 'cancelled'],
        'certified' => [], 'cancelled' => [],
    ];

    public function setRequired(CustomerRequest $request, bool $required, User $user): void
    {
        DB::transaction(function () use ($request, $required, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (in_array($locked->status, ['closed', 'archived'], true)) {
                throw ValidationException::withMessages(['revenue_entry_required' => 'Closed or archived requests cannot be changed.']);
            }
            if (! $required && $locked->revenueEntries()->exists()) {
                throw ValidationException::withMessages(['revenue_entry_required' => 'Revenue Entry cannot be disabled after records exist. Cancel the records and use an authorized closure override if needed.']);
            }
            $old = $locked->revenue_entry_required;
            $locked->update(['revenue_entry_required' => $required]);
            $locked->revenueEntryHistory()->create(['action' => 'requirement_changed', 'old_values' => ['required' => $old], 'new_values' => ['required' => $required], 'changed_by' => $user->id]);
        });
    }

    public function create(CustomerRequest $request, array $attributes, User $user): RequestRevenueEntry
    {
        return DB::transaction(function () use ($request, $attributes, $user): RequestRevenueEntry {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertEditable($locked);
            if (! $locked->revenue_entry_required) {
                throw ValidationException::withMessages(['revenue_entry' => 'Enable Revenue Entry Required before adding an entry.']);
            }
            $attributes = $this->timestampsForStatus($attributes, null);
            $entry = $locked->revenueEntries()->create([...$attributes, 'created_by' => $user->id, 'updated_by' => $user->id]);
            $entry->history()->create(['request_id' => $locked->id, 'action' => 'created', 'to_status' => $entry->status, 'new_values' => $this->auditValues($entry), 'changed_by' => $user->id]);
            $this->notifyMilestone($locked, $entry);

            return $entry;
        });
    }

    public function update(CustomerRequest $request, RequestRevenueEntry $entry, array $attributes, User $user): RequestRevenueEntry
    {
        return DB::transaction(function () use ($request, $entry, $attributes, $user): RequestRevenueEntry {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertEditable($locked);
            $row = $this->owned($locked, $entry)->lockForUpdate()->firstOrFail();
            $from = $row->status;
            $to = $attributes['status'];
            if ($to !== $from && ! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This Revenue Entry status transition is not allowed.']);
            }
            $old = $this->auditValues($row);
            $row->update([...$this->timestampsForStatus($attributes, $from), 'updated_by' => $user->id]);
            $row->history()->create(['request_id' => $locked->id, 'action' => $to === $from ? 'updated' : 'status_changed', 'from_status' => $from, 'to_status' => $to, 'old_values' => $old, 'new_values' => $this->auditValues($row->fresh()), 'reason' => $to === 'rejected' ? $attributes['rejection_reason'] : null, 'changed_by' => $user->id]);
            if ($to !== $from) {
                $this->notifyMilestone($locked, $row->fresh());
            }

            return $row->refresh();
        });
    }

    public function upload(CustomerRequest $request, RequestRevenueEntry $entry, UploadedFile $file, string $type, User $user): RequestRevenueEntryDocument
    {
        $this->assertEditable($request);
        $this->owned($request, $entry)->firstOrFail();
        $path = $file->store("customer-requests/{$request->id}/revenue-entries/{$entry->id}", 'local');
        if ($path === false) {
            throw new \RuntimeException('Revenue Entry document could not be stored.');
        }
        try {
            $document = $entry->documents()->create(['document_type' => $type, 'original_name' => $file->getClientOriginalName(), 'file_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => $user->id]);
            $entry->history()->create(['request_id' => $request->id, 'action' => 'document_uploaded', 'from_status' => $entry->status, 'to_status' => $entry->status, 'new_values' => ['document_type' => $type, 'original_name' => $document->original_name], 'changed_by' => $user->id]);

            return $document;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    private function assertEditable(CustomerRequest $request): void
    {
        if (in_array($request->status, ['closed', 'archived'], true)) {
            throw ValidationException::withMessages(['revenue_entry' => 'Closed or archived requests are read-only.']);
        }
    }

    private function owned(CustomerRequest $request, RequestRevenueEntry $entry)
    {
        return RequestRevenueEntry::query()->whereKey($entry->id)->where('request_id', $request->id);
    }

    private function timestampsForStatus(array $attributes, ?string $from): array
    {
        if ($attributes['status'] === 'submitted' && empty($attributes['submitted_at'])) {
            $attributes['submitted_at'] = now();
        }
        if ($attributes['status'] === 'certified' && empty($attributes['certified_at'])) {
            $attributes['certified_at'] = now();
        }
        if ($attributes['status'] === 'rejected') {
            $attributes['rejected_at'] = now();
        }
        if ($from === 'rejected' && $attributes['status'] !== 'rejected') {
            $attributes['rejected_at'] = null;
            $attributes['rejection_reason'] = null;
        }

        return $attributes;
    }

    private function auditValues(RequestRevenueEntry $entry): array
    {
        return Arr::only($entry->toArray(), ['entry_number', 'entry_date', 'application_number', 'application_date', 'village', 'taluka', 'district', 'survey_numbers', 'status', 'submitted_at', 'certified_at', 'rejected_at', 'rejection_reason', 'admin_notes', 'customer_note']);
    }

    private function notifyMilestone(CustomerRequest $request, RequestRevenueEntry $entry): void
    {
        $milestone = match ($entry->status) {
            'submitted' => NotificationMilestone::RevenueEntrySubmitted,
            'entry_number_received' => NotificationMilestone::RevenueEntryNumberReceived,
            'certified' => NotificationMilestone::RevenueEntryCertified,
            'rejected' => NotificationMilestone::RevenueEntryRejected,
            default => null,
        };
        if (! $milestone) {
            return;
        }

        $serviceNames = $request->requestServices()->where('status', 'approved')->where('is_admin_added', false)
            ->pluck('service_name_gu_snapshot')->filter()->implode(', ');
        $serviceName = $serviceNames ?: collect([$request->service?->name_gu, $request->service?->name_en])->filter()->implode(' / ');
        $statusLabel = match ($entry->status) {
            'submitted' => 'ફેરફાર નોંધ માટે અરજી દાખલ થઈ / Submitted',
            'entry_number_received' => 'ફેરફાર નોંધ નંબર મળ્યો / Entry Number Received',
            'certified' => 'ફેરફાર નોંધ પ્રમાણિત થઈ / Certified',
            'rejected' => 'ફેરફાર નોંધ નામંજૂર / કાર્યવાહી જરૂરી / Action Required',
        };

        $this->notifications->afterCommit($request, $milestone, 'revenue_entry', $entry->id, [
            'service_name' => $serviceName ?: 'Document Service',
            'survey_numbers' => array_values($entry->survey_numbers ?? []),
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date?->format('d M Y'),
            'certified_date' => $entry->certified_at?->format('d M Y'),
            'status_label' => $statusLabel,
            'customer_note' => $entry->customer_note,
        ]);
    }
}

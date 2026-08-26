<?php

namespace App\Services\Notifications;

use App\Enums\NotificationMilestone;
use App\Models\CustomerNotificationDelivery;
use App\Models\CustomerRequest;
use App\Models\RequestPayment;
use App\Models\RequestRevenueEntry;
use App\Services\RequestBillingStateResolver;
use App\Support\IndiaDateTime;

final class Msg91WhatsAppTemplateFactory
{
    public function __construct(
        private readonly RequestBillingStateResolver $billing,
        private readonly WhatsAppLanguageResolver $languages,
    ) {}

    /** @return array{name:string,language:string,parameters:list<string>}|null */
    public function make(CustomerNotificationDelivery $delivery): ?array
    {
        $event = $delivery->event;
        $request = $event->customerRequest;
        if (! $request) {
            return null;
        }

        $language = $this->languages->resolve($request);
        $configuration = config("msg91-whatsapp.templates.{$event->milestone->value}");
        if (! is_array($configuration) || ! ($configuration['languages'][$language] ?? false)) {
            return null;
        }

        $parameters = $this->parameters($request, $event->milestone, $event->source_type, $event->source_id, $language, $event->safe_context ?? []);
        if ($parameters === null || collect($parameters)->contains(fn (string $value): bool => trim($value) === '')) {
            return null;
        }

        return ['name' => $configuration['name'], 'language' => $language, 'parameters' => $parameters];
    }

    /** @return list<string>|null */
    private function parameters(CustomerRequest $request, NotificationMilestone $milestone, ?string $sourceType, ?int $sourceId, string $language, array $context): ?array
    {
        $request->loadMissing('requestServices');
        $common = [$request->name, $this->serviceName($request, $language)];

        return match ($milestone) {
            NotificationMilestone::RequestReceived => [...$common, (string) $request->reference_no],
            NotificationMilestone::Accepted => $this->accepted($request, $common),
            NotificationMilestone::PaymentReceived => $this->paymentReceived($request, $sourceType, $sourceId, $common),
            NotificationMilestone::Rejected => [...$common, (string) $request->reference_no, $this->rejectionReason($request)],
            NotificationMilestone::Completed => [...$common, (string) $request->file_number],
            NotificationMilestone::FinalDraftReady => [...$common, (string) $request->file_number],
            NotificationMilestone::Dispatched => $this->dispatch($request, $common),
            NotificationMilestone::RevenueEntryNumberReceived => $this->entry($request, $sourceType, $sourceId, $common, false, false, $context),
            NotificationMilestone::RevenueEntryCertified => $this->entry($request, $sourceType, $sourceId, $common, true, false, $context),
            NotificationMilestone::RevenueEntryRejected => $this->entry($request, $sourceType, $sourceId, $common, false, true, $context),
            default => null,
        };
    }

    private function accepted(CustomerRequest $request, array $common): ?array
    {
        $state = $this->billing->resolve($request);
        if (! $state->paymentRequired || $state->balanceDue === null || $state->balanceDue <= 0) {
            return null;
        }

        return [...$common, (string) $request->reference_no, (string) $request->file_number, $this->money($state->balanceDue)];
    }

    private function paymentReceived(CustomerRequest $request, ?string $sourceType, ?int $sourceId, array $common): ?array
    {
        if ($sourceType !== 'payment' || ! $sourceId) {
            return null;
        }

        $payment = RequestPayment::query()->where('request_id', $request->id)->where('payment_status', 'received')->find($sourceId);

        return $payment ? [...$common, $this->money((float) $payment->amount), (string) $request->file_number] : null;
    }

    private function dispatch(CustomerRequest $request, array $common): ?array
    {
        $dispatch = $request->dispatches()->whereIn('dispatch_status', ['dispatched', 'in_transit', 'delivered'])->latest('dispatch_date')->first();
        if (! $dispatch) {
            return null;
        }

        $reference = $dispatch->tracking_number ?: $request->reference_no;

        return [...$common, (string) $request->file_number, str($dispatch->dispatch_method)->replace('_', ' ')->title()->toString(), (string) $reference, IndiaDateTime::format($dispatch->dispatch_date, 'd-m-Y')];
    }

    private function entry(CustomerRequest $request, ?string $sourceType, ?int $sourceId, array $common, bool $certified, bool $rejected, array $context): ?array
    {
        if ($sourceType !== 'revenue_entry' || ! $sourceId) {
            return null;
        }

        $entry = RequestRevenueEntry::query()->where('request_id', $request->id)->find($sourceId);
        if (! $entry) {
            return null;
        }

        if ($rejected) {
            $detail = trim((string) ($context['customer_note'] ?? $entry->customer_note));
            $detail = $detail !== '' ? $detail : 'Please contact Sai Consulting for the action required.';

            return [...$common, (string) $request->file_number, (string) ($entry->entry_number ?: $entry->application_number), $detail];
        }

        $values = [...$common, (string) $request->file_number, (string) $entry->entry_number, IndiaDateTime::format($entry->entry_date, 'd-m-Y')];

        return $certified
            ? [...$values, IndiaDateTime::format($entry->certified_at, 'd-m-Y')]
            : [...$values, (string) ($context['status_label'] ?? 'Entry number received')];
    }

    private function serviceName(CustomerRequest $request, string $language): string
    {
        $field = $language === 'en' ? 'service_name_en_snapshot' : 'service_name_gu_snapshot';
        $fallback = $language === 'en' ? 'service_name_gu_snapshot' : 'service_name_en_snapshot';
        $names = $request->requestServices->where('status', '!=', 'rejected')->pluck($field)->filter();
        if ($names->isEmpty()) {
            $names = $request->requestServices->pluck($fallback)->filter();
        }

        return $names->unique()->implode(', ');
    }

    private function rejectionReason(CustomerRequest $request): string
    {
        $reason = $request->requestServices->pluck('customer_decision_message')->filter()->unique()->implode(' ');

        return $reason !== '' ? $reason : 'Please contact Sai Consulting for more information.';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}

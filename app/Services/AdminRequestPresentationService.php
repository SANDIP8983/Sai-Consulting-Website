<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Support\Collection;

class AdminRequestPresentationService
{
    public function __construct(private readonly RequestBillingStateResolver $billingStateResolver) {}

    public function detail(CustomerRequest $request, array $transitions): array
    {
        $scopes = $request->requestServices
            ->where('status', 'approved')
            ->flatMap->workScopes
            ->values();
        $resolved = $scopes->whereIn('status', ProcessingChecklistService::RESOLVED)->count();
        $completed = $scopes->where('status', 'completed')->count();
        $remaining = $scopes->whereIn('status', ['pending', 'in_progress'])->count();
        $percentage = $scopes->isEmpty() ? 0 : (int) round($resolved / $scopes->count() * 100);
        $currentScope = $scopes->firstWhere('status', 'in_progress') ?? $scopes->firstWhere('status', 'pending');
        $approvedServices = $request->requestServices->where('status', 'approved');
        $allServicesDecided = $request->requestServices->every(fn ($item): bool => in_array($item->status, ['approved', 'rejected', 'under_review'], true)
            && ($request->case_planning_version === 0 || $item->decided_at));
        $billingState = $this->billingStateResolver->resolve($request);
        $paymentEligible = $request->file_number && in_array($request->status, ['approved', 'payment_pending', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'], true);

        return [
            'processingSummary' => [
                'percentage' => $percentage,
                'completed' => $completed,
                'resolved' => $resolved,
                'remaining' => $remaining,
                'total' => $scopes->count(),
                'current_stage' => $request->processing?->processing_stage
                    ? str($request->processing->processing_stage)->headline()->toString()
                    : ($currentScope?->name_en_snapshot ?? ($scopes->isNotEmpty() && $remaining === 0 ? 'Processing Resolved' : 'Not Started')),
            ],
            'checklistGroups' => $this->groupScopes($scopes),
            'billingSummary' => $this->billing($request),
            'billingState' => $billingState,
            'activityItems' => $this->activity($request),
            'stickyActions' => [
                'save' => ! in_array($request->status, ['closed', 'archived'], true),
                'approve' => $approvedServices->isNotEmpty() && $allServicesDecided && ! $billingState->pricingLocked && ! in_array($request->status, ['closed', 'archived'], true),
                'mark_paid' => $paymentEligible && $billingState->canRecordPayment(),
                'complete' => $scopes->isNotEmpty() && $remaining === 0 && ! in_array($request->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true),
                'dispatch' => in_array($request->status, ['completed', 'dispatched', 'delivered'], true),
                'archive' => in_array('archived', $transitions, true),
            ],
        ];
    }

    private function groupScopes(Collection $scopes): array
    {
        $groups = collect(['Documents', 'Drafting', 'Registration', 'Completion'])
            ->mapWithKeys(fn (string $group): array => [$group => collect()]);

        foreach ($scopes as $scope) {
            $name = str($scope->name_en_snapshot)->lower()->toString();
            $group = match (true) {
                str_contains($name, 'draft') => 'Drafting',
                str_contains($name, 'registration'), str_contains($name, 'registrar'), str_contains($name, 'stamp'), str_contains($name, 'token') => 'Registration',
                str_contains($name, 'document'), str_contains($name, 'title'), str_contains($name, 'copy'), str_contains($name, 'review') => 'Documents',
                default => 'Completion',
            };
            $groups[$group]->push($scope);
        }

        return $groups->all();
    }

    private function billing(CustomerRequest $request): array
    {
        $state = $this->billingStateResolver->resolve($request);

        return [
            'professional_fee' => $state->professionalFee,
            'discount' => $state->discountAmount,
            'gst' => $state->gstAmount,
            'government_charges' => $state->governmentChargesTotal,
            'grand_total' => $state->grandTotal,
            'paid' => $state->confirmedPaidAmount,
            'balance' => $state->balanceDue,
            'payment_status' => $state->paymentStatus,
            'locked' => $state->pricingLocked,
            'frozen' => $state->hasFrozenBilling || $state->legacy,
        ];
    }

    private function activity(CustomerRequest $request): Collection
    {
        $items = collect();
        foreach ($request->statusHistory as $history) {
            $items->push($this->activityRow($history->created_at, $history->changedBy?->name, 'Status changed', $history->to_status, $history->remarks, $this->highlight($history->to_status)));
        }
        foreach ($request->payments as $payment) {
            $items->push($this->activityRow($payment->created_at, $payment->receivedBy?->name, 'Payment recorded', $payment->payment_status, $payment->notes, 'payment'));
        }
        foreach ($request->processingHistory as $history) {
            $items->push($this->activityRow($history->created_at, $history->changedBy?->name, 'Processing stage changed', $history->to_stage, $history->remarks, 'processing'));
        }
        foreach ($request->requestServices->flatMap->workScopes->flatMap->history as $history) {
            $items->push($this->activityRow($history->created_at, $history->changedBy?->name, str($history->action)->headline()->toString(), $history->to_status, $history->reason ?: $history->internal_note, 'processing'));
        }
        foreach ($request->dispatches->flatMap->history as $history) {
            $items->push($this->activityRow($history->created_at, $history->changedBy?->name, str($history->action)->headline()->toString(), $history->to_status, $history->reason, 'dispatch'));
        }
        foreach ($request->billing?->history ?? collect() as $history) {
            $items->push($this->activityRow($history->created_at, $history->changedBy?->name, str($history->action)->headline()->toString(), 'Billing', $history->reason, 'billing'));
        }
        foreach ($request->caseActionHistory as $history) {
            $items->push($this->activityRow($history->created_at, $history->performedBy?->name, str($history->action)->headline()->toString(), $history->to_status, $history->reason ?: $history->internal_note, $this->highlight($history->action)));
        }

        return $items->sortByDesc('date')->values();
    }

    private function activityRow($date, ?string $admin, string $action, ?string $status, ?string $remark, string $highlight): array
    {
        return compact('date', 'admin', 'action', 'status', 'remark', 'highlight');
    }

    private function highlight(?string $value): string
    {
        $value = strtolower((string) $value);

        return match (true) {
            str_contains($value, 'approv') => 'approval',
            str_contains($value, 'payment'), str_contains($value, 'paid') => 'payment',
            str_contains($value, 'complete'), str_contains($value, 'clos') => 'completion',
            str_contains($value, 'dispatch'), str_contains($value, 'deliver') => 'dispatch',
            str_contains($value, 'archive') => 'archive',
            default => 'general',
        };
    }
}

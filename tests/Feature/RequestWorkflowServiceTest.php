<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\StatusHistory;
use App\Models\User;
use App\Models\WorkScopeItem;
use App\Services\RequestChecklistInitializer;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class RequestWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_submission_generates_unique_reference_numbers(): void
    {
        $service = $this->createService();
        $workflow = app(RequestWorkflowService::class);

        $first = $workflow->submit([
            'service_id' => $service->id,
            'name' => 'First Customer',
            'mobile' => '9999999999',
        ], []);
        $second = $workflow->submit([
            'service_id' => $service->id,
            'name' => 'Second Customer',
            'mobile' => '8888888888',
        ], []);

        $this->assertNotSame($first->reference_no, $second->reference_no);
        $this->assertSame(CustomerRequest::CURRENT_CASE_PLANNING_VERSION, $first->case_planning_version);
        $this->assertTrue($first->usesChecklistWorkflow());
    }

    public function test_service_acceptance_snapshots_configured_default_work_scopes(): void
    {
        $service = $this->createService();
        $scope = WorkScopeItem::query()->create(['name_en' => 'Drafting', 'name_gu' => 'Drafting', 'normalized_name' => 'drafting', 'is_active' => true]);
        $service->defaultWorkScopes()->attach($scope->id, ['is_default' => true, 'display_order' => 3]);
        $request = app(RequestWorkflowService::class)->submit(['service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999'], []);
        $selected = $request->requestServices()->firstOrFail();
        $admin = User::factory()->create();

        app(RequestWorkflowService::class)->decideService($request, $selected, ['decision' => 'approved'], $admin);

        $this->assertDatabaseHas('request_service_work_scopes', [
            'request_service_id' => $selected->id,
            'work_scope_item_id' => $scope->id,
            'name_en_snapshot' => 'Drafting',
            'status' => 'pending',
            'display_order' => 3,
            'selected_by' => $admin->id,
        ]);
    }

    public function test_billing_freeze_blocks_current_workflow_without_initialized_checklist(): void
    {
        $service = $this->createService();
        $request = app(RequestWorkflowService::class)->submit(['service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999'], []);
        $selected = $request->requestServices()->firstOrFail();
        $admin = User::factory()->create();
        app(RequestWorkflowService::class)->decideService($request, $selected, ['decision' => 'approved'], $admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Every accepted service requires at least one selected work-scope item.');
        app(RequestWorkflowService::class)->finalizeRequestBilling($request, ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 0], $admin);
    }

    public function test_repair_only_converts_editable_unpaid_post_cutoff_request(): void
    {
        $admin = User::factory()->create();
        $eligible = $this->createRequest('received');
        $eligible->forceFill(['created_at' => '2026-08-03 10:00:00'])->save();
        $historical = $this->createRequest('received', ['reference_no' => 'SC/2026/HISTORICAL']);
        $historical->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        app(RequestChecklistInitializer::class)->repairEligible($eligible, $admin);

        $this->assertSame(CustomerRequest::CURRENT_CASE_PLANNING_VERSION, $eligible->fresh()->case_planning_version);
        $this->assertSame(0, $historical->fresh()->case_planning_version);
        $this->assertDatabaseHas('request_case_action_histories', ['request_id' => $eligible->id, 'action' => 'checklist_initialized']);
    }

    public function test_repair_refuses_paid_or_historical_requests(): void
    {
        $admin = User::factory()->create();
        $paid = $this->createRequest('payment_received', ['amount_paid' => 100]);
        $paid->forceFill(['created_at' => '2026-08-03 10:00:00'])->save();
        $historical = $this->createRequest('received', ['reference_no' => 'SC/2026/HISTORICAL']);
        $historical->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        foreach ([$paid, $historical] as $request) {
            try {
                app(RequestChecklistInitializer::class)->repairEligible($request, $admin);
                $this->fail('Expected unsafe checklist repair to be rejected.');
            } catch (ValidationException) {
                $this->assertSame(0, $request->fresh()->case_planning_version);
            }
        }
    }

    public function test_permitted_transition_creates_status_history(): void
    {
        $request = $this->createRequest('received');

        app(RequestWorkflowService::class)->transition($request, [
            'status' => 'under_review',
            'remarks' => 'Review started.',
            'is_visible_to_customer' => true,
        ], User::factory()->create());

        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $request->id,
            'from_status' => 'received',
            'to_status' => 'under_review',
        ]);
    }

    public function test_approval_preserves_an_existing_file_number(): void
    {
        $request = $this->createRequest('under_review', ['file_number' => 'SC/2025/F000099']);
        app(RequestWorkflowService::class)->transition($request, ['status' => 'approved'], User::factory()->create());
        $this->assertSame('SC/2025/F000099', $request->fresh()->file_number);
    }

    public function test_forbidden_transition_does_not_change_the_request_or_create_history(): void
    {
        $request = $this->createRequest('received');

        try {
            app(RequestWorkflowService::class)->transition($request, [
                'status' => 'completed',
                'is_visible_to_customer' => false,
            ], User::factory()->create());
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            // Expected: received cannot transition directly to completed.
        }

        $this->assertSame('received', $request->fresh()->status);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_payment_records_belong_to_the_request_and_update_payment_state(): void
    {
        $request = $this->createRequest('payment_pending', ['payment_status' => 'pending', 'file_number' => 'SC/2026/F000001', 'request_origin' => 'offline']);
        $this->createFrozenBilling($request, 250);

        app(RequestWorkflowService::class)->recordPayment($request, [
            'amount' => 250,
            'payment_status' => 'received',
            'payment_method' => 'cash',
            'received_at' => now(),
            'notes' => 'Received.',
        ], User::factory()->create());

        $this->assertSame('awaiting_staff_assignment', $request->fresh()->status);
        $this->assertDatabaseHas('request_payments', [
            'request_id' => $request->id,
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $request->id,
            'to_status' => 'payment_received',
        ]);
    }

    public function test_payment_workflow_rolls_back_when_payment_creation_fails(): void
    {
        $request = $this->createRequest('payment_pending', ['payment_status' => 'pending', 'file_number' => 'SC/2026/F000001', 'request_origin' => 'offline']);
        $this->createFrozenBilling($request, 250);

        StatusHistory::creating(function (): void {
            throw new RuntimeException('Unable to create status history.');
        });

        try {
            app(RequestWorkflowService::class)->recordPayment($request, [
                'amount' => 250,
                'payment_status' => 'received',
                'payment_method' => 'cash',
                'received_at' => now(),
            ], User::factory()->create());
            $this->fail('Expected status history creation to fail.');
        } catch (RuntimeException) {
            // Expected: the transaction must roll back every database write.
        }

        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertDatabaseCount('request_payments', 0);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    private function createService(): Service
    {
        $suffix = fake()->unique()->numerify('######');

        return Service::query()->create([
            'name_en' => 'Sale Deed '.$suffix,
            'name_gu' => 'Sale Deed Gujarati '.$suffix,
            'slug' => 'sale-deed-'.$suffix,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createFrozenBilling(CustomerRequest $request, float $total): void
    {
        $request->billing()->create([
            'total_original_professional_fee' => $total,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_professional_fee' => $total,
            'gst_rate' => 0,
            'gst_amount' => 0,
            'government_charges_total' => 0,
            'grand_total' => $total,
            'pricing_locked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRequest(string $status, array $attributes = []): CustomerRequest
    {
        return CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000001',
            'service_id' => $this->createService()->id,
            'name' => 'Customer',
            'mobile' => '9999999999',
            'status' => $status,
            ...$attributes,
        ]);
    }
}

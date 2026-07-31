<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\StatusHistory;
use App\Models\User;
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
        $request = $this->createRequest('payment_pending', ['payment_status' => 'pending']);

        app(RequestWorkflowService::class)->recordPayment($request, [
            'amount' => 250,
            'payment_method' => 'cash',
            'received_at' => now(),
            'notes' => 'Received.',
        ], User::factory()->create());

        $this->assertSame('payment_received', $request->fresh()->status);
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
        $request = $this->createRequest('payment_pending', ['payment_status' => 'pending']);

        StatusHistory::creating(function (): void {
            throw new RuntimeException('Unable to create status history.');
        });

        try {
            app(RequestWorkflowService::class)->recordPayment($request, [
                'amount' => 250,
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
        return Service::query()->create([
            'name_en' => 'Sale Deed',
            'name_gu' => 'Sale Deed Gujarati',
            'slug' => 'sale-deed',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
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

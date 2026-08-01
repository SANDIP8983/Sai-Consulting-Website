<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\FileDocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FileDocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_is_rejected_before_approval_and_file_number(): void
    {
        $this->expectException(ValidationException::class);
        app(FileDocumentProcessingService::class)->open($this->request(), [], User::factory()->create());
    }

    public function test_file_opening_snapshots_service_capabilities_and_creates_history(): void
    {
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $admin = User::factory()->create();
        $processing = app(FileDocumentProcessingService::class)->open($request, ['priority' => 'urgent'], $admin);
        $this->assertSame('file_opened', $processing->processing_stage);
        $this->assertSame('urgent', $processing->priority);
        $this->assertTrue($processing->requires_registration);
        $this->assertDatabaseHas('request_processing_histories', ['request_id' => $request->id, 'to_stage' => 'file_opened', 'changed_by' => $admin->id]);
    }

    public function test_valid_and_invalid_stage_transitions_are_enforced(): void
    {
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $admin = User::factory()->create();
        $service = app(FileDocumentProcessingService::class);
        $service->open($request, [], $admin);
        $service->transition($request, 'documents_under_review', [], $admin);
        $this->assertSame('documents_under_review', $request->processing->fresh()->processing_stage);
        $this->expectException(ValidationException::class);
        $service->transition($request, 'registered', [], $admin);
    }

    public function test_registered_stage_requires_registration_details(): void
    {
        $request = $this->request(['status' => 'ready_for_registration', 'file_number' => 'SC/2026/F000001', 'payment_status' => 'received']);
        $admin = User::factory()->create();
        $service = app(FileDocumentProcessingService::class);
        $service->open($request, [], $admin);
        $service->transition($request, 'documents_under_review', [], $admin);
        $service->transition($request, 'registration_pending', [], $admin);
        try {
            $service->transition($request, 'registered', [], $admin);
            $this->fail('Registered should require details.');
        } catch (ValidationException) {
            $this->assertSame('registration_pending', $request->processing->fresh()->processing_stage);
        }
        $request->processing->update(['registration_date' => '2026-08-01', 'registration_number' => 'REG-100']);
        $service->transition($request, 'registered', [], $admin);
        $this->assertSame('registered', $request->processing->fresh()->processing_stage);
    }

    public function test_admin_file_and_drafting_actions_are_authenticated_and_validated(): void
    {
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $this->post(route('admin.requests.processing.open', $request), [])->assertRedirect(route('login'));
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.requests.processing.open', $request), ['file_opened_at' => '2026-08-01', 'priority' => 'high'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.drafting.update', $request), ['drafting_internal_note' => 'Private drafting note.', 'drafting_customer_remark' => 'Draft preparation is underway.'])->assertSessionHasNoErrors();
        $this->assertSame('high', $request->processing->fresh()->priority);
        $this->assertDatabaseHas('request_processing_histories', ['request_id' => $request->id, 'remarks' => 'Draft preparation is underway.', 'is_visible_to_customer' => true]);
    }

    public function test_drafting_stage_uses_the_existing_request_workflow(): void
    {
        $request = $this->request(['status' => 'payment_received', 'file_number' => 'SC/2026/F000001', 'payment_status' => 'received']);
        $admin = User::factory()->create();
        $service = app(FileDocumentProcessingService::class);
        $service->open($request, [], $admin);
        $service->transition($request, 'documents_under_review', [], $admin);
        $service->transition($request, 'drafting_started', [], $admin);
        $this->assertSame('draft_in_progress', $request->fresh()->status);
        $this->assertDatabaseHas('request_status_histories', ['request_id' => $request->id, 'from_status' => 'payment_received', 'to_status' => 'draft_in_progress']);
    }

    public function test_service_capabilities_remove_non_applicable_stage_paths(): void
    {
        $request=$this->request(['status'=>'payment_received','file_number'=>'SC/2026/F000001','payment_status'=>'received']);
        $request->service->update(['uses_drafting_workflow'=>false,'requires_token_booking'=>false,'requires_registration'=>false,'requires_certified_copy'=>false]);
        $processing=app(FileDocumentProcessingService::class)->open($request,[],User::factory()->create());
        app(FileDocumentProcessingService::class)->transition($request,'documents_under_review',[],User::factory()->create());
        $this->assertSame(['documents_incomplete','ready_for_dispatch'],app(FileDocumentProcessingService::class)->transitions($processing->fresh()));
    }

    public function test_processing_completion_preserves_existing_completed_status_and_history(): void
    {
        $request=$this->request(['status'=>'dispatched','file_number'=>'SC/2026/F000001','payment_status'=>'received']);
        $request->processing()->create(['processing_stage'=>'dispatched']); $admin=User::factory()->create();
        app(FileDocumentProcessingService::class)->transition($request,'completed',[], $admin);
        $this->assertSame('completed',$request->fresh()->status);
        $this->assertNotNull($request->processing->fresh()->actual_completion_date);
        $this->assertDatabaseHas('request_status_histories',['request_id'=>$request->id,'from_status'=>'dispatched','to_status'=>'completed']);
        $this->assertDatabaseHas('request_processing_histories',['request_id'=>$request->id,'from_stage'=>'dispatched','to_stage'=>'completed']);
    }

    public function test_service_can_process_without_advance_payment(): void
    {
        $request=$this->request(['status'=>'approved','file_number'=>'SC/2026/F000001']);
        $request->service->update(['requires_payment_before_processing'=>false]);
        $admin=User::factory()->create(); $workflow=app(FileDocumentProcessingService::class);
        $workflow->open($request,[],$admin); $workflow->transition($request,'documents_under_review',[],$admin); $workflow->transition($request,'drafting_started',[],$admin);
        $this->assertSame('draft_in_progress',$request->fresh()->status);
        $this->assertSame('not_required',$request->payment_status);
    }

    public function test_service_without_dispatch_can_complete_without_dispatch_record(): void
    {
        $request=$this->request(['status'=>'approved','file_number'=>'SC/2026/F000001']);
        $request->service->update(['uses_drafting_workflow'=>false,'requires_token_booking'=>false,'requires_registration'=>false,'requires_certified_copy'=>false,'requires_dispatch'=>false,'requires_payment_before_processing'=>false]);
        $admin=User::factory()->create(); $workflow=app(FileDocumentProcessingService::class);
        $processing=$workflow->open($request,[],$admin); $workflow->transition($request,'documents_under_review',[],$admin);
        $this->assertContains('completed',$workflow->transitions($processing->fresh()));
        $workflow->transition($request,'completed',[],$admin);
        $this->assertSame('completed',$request->fresh()->status);
        $this->assertDatabaseCount('request_dispatches',0);
    }
    private function request(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->create(['name_en' => 'Processing Service', 'name_gu' => 'Processing Service', 'slug' => 'processing-service', 'is_active' => true, 'sort_order' => 1, 'uses_drafting_workflow' => true, 'requires_registration' => true]);
        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/000001', 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'status' => 'under_review', 'payment_status' => 'not_required', ...$attributes]);
    }
}

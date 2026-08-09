<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestStaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_request_is_awaiting_assignment_and_unassigned_is_hidden_and_forbidden_for_staff(): void
    {
        [$request, $scope] = $this->paidRequest();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertSame('awaiting_staff_assignment', $request->status);
        $this->actingAs($staff)->get(route('admin.requests.index'))->assertOk()->assertDontSee($request->reference_no);
        $this->actingAs($staff)->get(route('admin.requests.show', $request))->assertForbidden();
        $this->actingAs($staff)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_admin_assigns_and_reassigns_with_staff_isolation_and_history(): void
    {
        [$request, $scope] = $this->paidRequest();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff01 = User::factory()->create(['role' => 'staff', 'name' => 'Staff 01']);
        $staff02 = User::factory()->create(['role' => 'staff', 'name' => 'Staff 02']);

        $this->actingAs($admin)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff01->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('requests', ['id' => $request->id, 'assigned_user_id' => $staff01->id, 'assigned_by' => $admin->id]);
        $this->assertDatabaseHas('request_assignment_histories', ['request_id' => $request->id, 'previous_assigned_user_id' => null, 'assigned_user_id' => $staff01->id, 'assigned_by' => $admin->id]);

        $this->actingAs($staff01)->get(route('admin.requests.index'))->assertOk()->assertSee($request->reference_no);
        $this->actingAs($staff01)->get(route('admin.requests.show', $request))->assertOk();
        $this->actingAs($staff02)->get(route('admin.requests.show', $request))->assertForbidden();
        $this->actingAs($staff02)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff02->id])->assertForbidden();
        $this->actingAs($staff02)->post(route('admin.requests.dispatches.store', $request), [])->assertForbidden();
        $this->actingAs($staff01)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $scope->fresh()->status);

        $this->actingAs($admin)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff02->id])->assertSessionHasNoErrors();
        $this->actingAs($staff01)->get(route('admin.requests.show', $request))->assertForbidden();
        $this->actingAs($staff02)->get(route('admin.requests.show', $request))->assertOk();
        $this->assertDatabaseHas('request_assignment_histories', ['request_id' => $request->id, 'previous_assigned_user_id' => $staff01->id, 'assigned_user_id' => $staff02->id, 'assigned_by' => $admin->id]);
        $this->assertSame(2, $request->assignmentHistory()->count());
    }

    public function test_inactive_staff_cannot_be_assigned_and_deactivation_does_not_expose_request(): void
    {
        [$request] = $this->paidRequest();
        $super = User::factory()->create(['role' => 'super_admin']);
        $staff01 = User::factory()->create(['role' => 'staff']);
        $staff02 = User::factory()->create(['role' => 'staff']);
        $inactive = User::factory()->create(['role' => 'staff', 'is_active' => false]);

        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $inactive->id])->assertSessionHasErrors('assigned_user_id');
        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff01->id])->assertSessionHasNoErrors();
        $staff01->update(['is_active' => false]);

        $this->actingAs($staff02)->get(route('admin.requests.show', $request))->assertForbidden();
        $this->actingAs($super)->get(route('admin.requests.show', $request))->assertOk()->assertSee('The assigned user is inactive');
        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff02->id])->assertSessionHasNoErrors();
        $this->actingAs($staff02)->get(route('admin.requests.show', $request))->assertOk();
        $this->assertSame(2, $request->assignmentHistory()->count());
    }

    public function test_super_admin_and_admin_keep_visibility_of_all_requests(): void
    {
        [$request] = $this->paidRequest();
        foreach (['super_admin', 'admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('admin.requests.index'))->assertOk()->assertSee($request->reference_no);
            $this->actingAs($actor)->get(route('admin.requests.show', $request))->assertOk();
        }
    }

    public function test_active_processing_users_of_every_role_are_assignable_but_ineligible_users_are_not(): void
    {
        [$request] = $this->paidRequest();
        $actor = User::factory()->create(['role' => 'super_admin']);
        $super = User::factory()->create(['role' => 'super_admin', 'name' => 'Assignable Super']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Assignable Admin']);
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Assignable Staff']);
        $inactive = User::factory()->create(['role' => 'staff', 'is_active' => false, 'name' => 'Inactive Staff']);

        $this->actingAs($actor)->get(route('admin.requests.show', $request))->assertOk()
            ->assertSee('Assignable Super')->assertSee('Assignable Admin')->assertSee('Assignable Staff')->assertDontSee('Inactive Staff');
        foreach ([$staff, $admin, $super] as $assignee) {
            $this->actingAs($actor)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $assignee->id])->assertSessionHasNoErrors();
            $this->assertSame($assignee->id, $request->fresh()->assigned_user_id);
        }
        $this->actingAs($actor)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $inactive->id])->assertSessionHasErrors('assigned_user_id');

        config(['permissions.roles.staff' => ['dashboard.view', 'requests.view']]);
        $unauthorized = User::factory()->create(['role' => 'staff']);
        $this->actingAs($actor)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $unauthorized->id])->assertSessionHasErrors('assigned_user_id');
    }

    public function test_admin_assignee_can_process_and_staff_admin_reassignments_preserve_ownership(): void
    {
        [$request, $scope] = $this->paidRequest();
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin01 = User::factory()->create(['role' => 'admin']);
        $admin02 = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff->id])->assertSessionHasNoErrors();
        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $admin01->id])->assertSessionHasNoErrors();
        $this->actingAs($staff)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($admin02)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($admin01)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress'])->assertSessionHasNoErrors();

        $this->actingAs($super)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff->id])->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $request->fresh()->assigned_user_id);
        $this->assertDatabaseHas('request_assignment_histories', ['request_id' => $request->id, 'previous_assigned_user_id' => $staff->id, 'assigned_user_id' => $admin01->id]);
        $this->assertDatabaseHas('request_assignment_histories', ['request_id' => $request->id, 'previous_assigned_user_id' => $admin01->id, 'assigned_user_id' => $staff->id]);
        $this->assertSame(3, $request->assignmentHistory()->count());
    }

    public function test_complete_customer_to_assignment_processing_dispatch_and_tracking_workflow(): void
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'E2E Service '.$suffix, 'name_gu' => 'E2E Service '.$suffix, 'slug' => 'e2e-'.$suffix, 'service_fee' => 1000, 'gst_rate' => 18, 'is_active' => true, 'available_online' => true, 'requires_payment_before_processing' => true, 'requires_dispatch' => true]);
        $work = WorkScopeItem::query()->create(['name_en' => 'E2E Work '.$suffix, 'name_gu' => 'E2E Work '.$suffix, 'normalized_name' => 'e2e-work-'.$suffix, 'is_active' => true]);
        $request = app(RequestWorkflowService::class)->submit(['service_id' => $service->id, 'service_ids' => [$service->id], 'name' => 'E2E Customer', 'mobile' => '9999999999'], []);
        $selected = $request->requestServices()->sole();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [$selected->id => ['decision' => 'approved', 'work_scope_ids' => [$work->id]]]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => []])->assertSessionHasNoErrors();
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 1180, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertSame('awaiting_staff_assignment', $request->fresh()->status);

        $this->actingAs($admin)->put(route('admin.requests.assignment.update', $request), ['assigned_user_id' => $staff->id])->assertSessionHasNoErrors();
        $scope = $selected->workScopes()->sole();
        $this->actingAs($staff)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'in_progress', 'customer_remark' => 'Processing underway.'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->patch(route('admin.requests.processing.work-items.update', [$request, $scope]), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->patch(route('admin.requests.processing.complete', $request), ['completion_date' => now()->toDateString(), 'customer_remark' => 'Case completed.'])->assertSessionHasNoErrors();
        $dispatch = ['dispatch_status' => 'dispatched', 'dispatch_method' => 'courier', 'dispatch_date' => now()->format('Y-m-d H:i:s'), 'document_description' => 'Completed document package', 'recipient_name' => 'Customer', 'recipient_mobile' => '9999999999', 'delivery_address' => 'Patan, Gujarat', 'carrier_name' => 'E2E Courier', 'tracking_number' => 'E2E-TRACK-1', 'internal_note' => 'Private dispatch note'];
        $this->actingAs($staff)->post(route('admin.requests.dispatches.store', $request), $dispatch)->assertSessionHasNoErrors();
        $dispatchRecord = $request->dispatches()->sole();
        $this->actingAs($staff)->patch(route('admin.requests.dispatches.status', [$request, $dispatchRecord]), ['dispatch_status' => 'delivered', 'delivered_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $request), ['closure_date' => now()->format('Y-m-d H:i:s'), 'customer_remark' => 'Case closed.', 'confirmed' => '1'])->assertSessionHasNoErrors();

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()
            ->assertSee('Payment Received')->assertSee('Awaiting Staff Assignment')->assertSee('In Progress')->assertSee('Completed')->assertSee('E2E Courier')->assertSee('E2E-TRACK-1')->assertSee('Delivered')->assertSee('Closed')->assertDontSee($staff->name)->assertDontSee('Private dispatch note');
    }

    private function paidRequest(): array
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'Assigned Service '.$suffix, 'name_gu' => 'Assigned Service', 'slug' => 'assigned-'.$suffix, 'service_fee' => 1000, 'gst_rate' => 18, 'is_active' => true, 'requires_payment_before_processing' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.$suffix, 'file_number' => 'SC/2026/F'.$suffix, 'case_planning_version' => 1, 'case_approved_at' => now(), 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'status' => 'awaiting_staff_assignment', 'payment_status' => 'received', 'amount_due' => 1180, 'amount_paid' => 1180]);
        $selected = $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'professional_fee' => 1000, 'gst_rate' => 18, 'status' => 'approved']);
        $request->billing()->create(['total_original_professional_fee' => 1000, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 1000, 'gst_rate' => 18, 'gst_amount' => 180, 'government_charges_total' => 0, 'grand_total' => 1180, 'pricing_locked_at' => now()]);
        $request->payments()->create(['amount' => 1180, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()]);
        $item = WorkScopeItem::query()->create(['name_en' => 'Assigned Work '.$suffix, 'name_gu' => 'Assigned Work', 'normalized_name' => 'assigned-work-'.$suffix, 'is_active' => true]);
        $scope = $selected->workScopes()->create(['work_scope_item_id' => $item->id, 'name_en_snapshot' => $item->name_en, 'status' => 'pending']);

        return [$request, $scope];
    }
}

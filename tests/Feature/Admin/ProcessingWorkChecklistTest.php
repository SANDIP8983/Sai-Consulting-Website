<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessingWorkChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_requires_approved_planning_file_and_payment_when_configured(): void
    {
        [$admin,$request,$service,$scope] = $this->planned(true, false);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasErrors('case');
        $request->update(['payment_status'=>'received']);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress',$request->fresh()->status);
    }

    public function test_only_selected_items_are_shown_and_internal_notes_stay_private(): void
    {
        [$admin,$request,$service,$scope] = $this->planned(false, true);
        $scope->update(['internal_note'=>'private processing note','customer_remark'=>'Customer update']);
        $this->actingAs($admin)->get(route('admin.requests.show',$request))->assertOk()->assertSee('Processing &amp; Work Checklist', false)->assertSee('Drafting');
        $this->post(route('request.track.lookup'),['reference_no'=>$request->reference_no,'mobile'=>$request->mobile])->assertOk()->assertSee('Drafting')->assertSee('Customer update')->assertDontSee('private processing note')->assertDontSee('Unselected Review');
    }

    public function test_not_required_requires_reason_and_completed_item_requires_audited_reopen(): void
    {
        [$admin,$request,$service,$scope] = $this->planned(false, true);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'cancelled'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'completed'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'completed'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.reopen',[$request,$scope]),['reason'=>'Correction required'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_service_work_scope_histories',['request_id'=>$request->id,'action'=>'reopened','reason'=>'Correction required']);
    }

    public function test_complete_case_is_explicit_locked_and_reopen_is_audited(): void
    {
        [$admin,$request,$service,$scope] = $this->planned(false, true);
        $this->actingAs($admin)->patch(route('admin.requests.processing.complete',$request),['completion_date'=>now()->toDateString()])->assertSessionHasErrors('work_scopes');
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'completed'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.complete',$request),['completion_date'=>now()->toDateString(),'customer_remark'=>'Work complete','internal_note'=>'Private completion'])->assertSessionHasNoErrors();
        $this->assertSame('completed',$request->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'in_progress'])->assertSessionHasErrors('case');
        $this->actingAs($admin)->patch(route('admin.requests.processing.reopen',$request),[])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.requests.processing.reopen',$request),['reason'=>'Additional work'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_case_action_histories',['request_id'=>$request->id,'action'=>'reopened','reason'=>'Additional work']);
    }

    public function test_legacy_request_remains_available_as_legacy_workflow(): void
    {
        $admin=User::factory()->create(); $service=$this->service(false);
        $request=CustomerRequest::create(['reference_no'=>'SC/2026/LEGACY','service_id'=>$service->id,'name'=>'Legacy','mobile'=>'9999999999','status'=>'approved','payment_status'=>'not_required','file_number'=>'SC/2026/F999999']);
        $this->actingAs($admin)->get(route('admin.requests.show',$request))->assertOk()->assertSee('Legacy Workflow');
    }

    public function test_not_required_and_cancelled_are_distinct_and_each_requires_reason(): void
    {
        [$admin,$request,$service,$scope]=$this->planned(false,true);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'not_required'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.update',[$request,$scope]),['status'=>'not_required','reason'=>'Customer no longer needs it'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_service_work_scopes',['id'=>$scope->id,'status'=>'not_required','resolution_reason'=>'Customer no longer needs it']);
    }

    public function test_bulk_actions_validate_state_and_cannot_touch_another_request(): void
    {
        [$admin,$request,$service,$scope]=$this->planned(false,true);
        [, $otherRequest,, $otherScope]=$this->planned(false,true);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.bulk',$request),['action'=>'start_selected','work_scope_ids'=>[$scope->id,$otherScope->id]])->assertSessionHasNoErrors();
        $this->assertSame('in_progress',$scope->fresh()->status);
        $this->assertSame('pending',$otherScope->fresh()->status);
        $this->actingAs($admin)->patch(route('admin.requests.processing.work-items.bulk',$request),['action'=>'complete_selected','work_scope_ids'=>[$scope->id]])->assertSessionHasNoErrors();
        $this->assertSame('completed',$scope->fresh()->status);
    }

    public function test_legacy_stage_actions_are_retired_for_checklist_requests(): void
    {
        [$admin,$request]=$this->planned(false,true);
        $this->actingAs($admin)->post(route('admin.requests.processing.open',$request),['file_opened_at'=>now()->toDateString(),'priority'=>'normal'])->assertSessionHasErrors('processing');
        $this->actingAs($admin)->get(route('admin.requests.show',$request))->assertOk()->assertDontSee('Advance Processing Stage')->assertSee('Start Selected')->assertSee('Save Processing Progress');
    }

    private function planned(bool $requiresPayment, bool $paid): array
    {
        $admin=User::factory()->create(); $service=$this->service($requiresPayment);
        $request=CustomerRequest::create(['reference_no'=>'SC/2026/'.fake()->unique()->numerify('######'),'file_number'=>'SC/2026/F'.fake()->unique()->numerify('######'),'case_planning_version'=>1,'case_approved_at'=>now(),'case_approved_by'=>$admin->id,'service_id'=>$service->id,'name'=>'Customer','mobile'=>'9999999999','status'=>$requiresPayment&&!$paid?'payment_pending':'approved','payment_status'=>$paid?'received':($requiresPayment?'pending':'not_required')]);
        $requestService=$request->requestServices()->create(['service_id'=>$service->id,'service_name_en_snapshot'=>$service->name_en,'service_name_gu_snapshot'=>$service->name_gu,'professional_fee'=>1000,'status'=>'approved']);
        $item=WorkScopeItem::create(['name_en'=>'Drafting','name_gu'=>'ડ્રાફ્ટિંગ','normalized_name'=>'drafting-'.fake()->unique()->numerify('######'),'is_active'=>true]);
        WorkScopeItem::create(['name_en'=>'Unselected Review','name_gu'=>'ચકાસણી','normalized_name'=>'review-'.fake()->unique()->numerify('######'),'is_active'=>true]);
        $scope=$requestService->workScopes()->create(['work_scope_item_id'=>$item->id,'name_en_snapshot'=>'Drafting','name_gu_snapshot'=>'ડ્રાફ્ટિંગ','status'=>'pending','selected_by'=>$admin->id]);
        return [$admin,$request,$service,$scope];
    }
    private function service(bool $requiresPayment): Service
    {
        return Service::create(['name_en'=>'Service '.fake()->unique()->numerify('######'),'name_gu'=>'સેવા '.fake()->unique()->numerify('######'),'slug'=>'service-'.fake()->unique()->numerify('######'),'service_fee'=>1000,'estimated_days'=>3,'is_active'=>true,'available_online'=>true,'available_offline'=>true,'requires_payment_before_processing'=>$requiresPayment]);
    }
}

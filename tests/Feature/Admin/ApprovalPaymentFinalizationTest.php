<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPaymentFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_request_discount_gst_and_government_charges_are_calculated_at_bill_level(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'fixed', 'discount_value' => 300]))->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(3000.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(300.0, (float) $billing->discount_amount);
        $this->assertSame(2700.0, (float) $billing->net_professional_fee);
        $this->assertSame(486.0, (float) $billing->gst_amount);
        $this->assertSame(300.0, (float) $billing->government_charges_total);
        $this->assertSame(3486.0, (float) $billing->grand_total);
        $this->assertSame(3486.0, (float) $request->fresh()->amount_due);
        $this->assertSame(1000.0, (float) $items[0]->fresh()->professional_fee);
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->file_number);
    }

    public function test_percentage_and_zero_discounts_are_supported(): void
    {
        [$request, $items] = $this->requestWithServices(); $admin = User::factory()->create(); $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame(300.0, (float) $request->fresh()->billing->discount_amount);
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Approved correction'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'none', 'discount_value' => 0, 'discount_reason' => null]))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $request->fresh()->billing->discount_amount);
    }

    public function test_excessive_discounts_are_rejected_server_side(): void
    {
        [$request, $items] = $this->requestWithServices(); $admin = User::factory()->create(); $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'fixed', 'discount_value' => 3001]))->assertSessionHasErrors('discount_value');
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_value' => 101]))->assertSessionHasErrors('discount_value');
        $this->assertDatabaseCount('request_billings', 0);
    }

    public function test_pricing_freeze_requires_explicit_audited_unlock_and_payment_relocks_it(): void
    {
        [$request, $items] = $this->requestWithServices(); $admin = User::factory()->create(); $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_value' => 20]))->assertSessionHasErrors('pricing');
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Management correction approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 3000, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertTrue($request->fresh()->billing->isLocked());
        $this->assertDatabaseHas('request_billing_histories', ['request_id' => $request->id, 'action' => 'unlocked', 'reason' => 'Management correction approved']);
    }

    public function test_customer_tracking_uses_request_summary_without_private_metadata(): void
    {
        [$request, $items] = $this->requestWithServices(); $admin = User::factory()->create(); $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['internal_note' => 'Private negotiation note']))->assertSessionHasNoErrors();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Payment Summary')->assertSee('Total Professional Fee')->assertSee('GST')->assertSee('Stamp Duty')->assertSee('Grand Total')->assertDontSee('Regular Customer')->assertDontSee('Private negotiation note')->assertDontSee($admin->name);
    }

    public function test_legacy_frozen_totals_remain_unchanged_and_render_without_new_billing(): void
    {
        $service = $this->service('Legacy', 1000);
        $request = CustomerRequest::query()->create(['reference_no'=>'SC/2026/999901','service_id'=>$service->id,'name'=>'Legacy Customer','mobile'=>'9999999999','status'=>'payment_received','payment_status'=>'received','amount_due'=>1234,'amount_paid'=>1234]);
        $request->requestServices()->create(['service_id'=>$service->id,'professional_fee'=>1000,'net_professional_fee'=>900,'gst_rate'=>18,'gst_amount'=>162,'government_charges'=>172,'government_charges_snapshot'=>[['name'=>'Legacy Charge','amount'=>172]],'final_total'=>1234,'pricing_locked_at'=>now(),'status'=>'approved']);
        $this->assertNull($request->fresh()->billing);
        $this->assertSame(1234.0, (float) $request->fresh()->amount_due);
        $this->post(route('request.track.lookup'), ['reference_no'=>$request->reference_no,'mobile'=>$request->mobile])->assertOk()->assertSee('Payment Summary')->assertSee('1,234.00');
    }

    private function requestWithServices(): array
    {
        $first=$this->service('First Service',1000); $second=$this->service('Second Service',2000);
        $request=CustomerRequest::query()->create(['reference_no'=>'SC/2026/900001','service_id'=>$first->id,'name'=>'Approval Customer','mobile'=>'9999999999','status'=>'under_review','payment_status'=>'not_required','amount_due'=>0,'last_status_changed_at'=>now()]);
        $items=collect([$first,$second])->map(fn($service)=>$request->requestServices()->create(['service_id'=>$service->id,'service_name_en_snapshot'=>$service->name_en,'professional_fee'=>$service->service_fee,'gst_rate'=>$service->gst_rate,'government_charges'=>0,'status'=>'under_review']))->all();
        return [$request,$items];
    }

    private function approveAll(User $admin, CustomerRequest $request, array $items): void
    {
        foreach($items as $item) $this->actingAs($admin)->patch(route('admin.requests.services.decision',[$request,$item]),['decision'=>'approved'])->assertSessionHasNoErrors();
    }

    private function billingPayload(array $overrides=[]): array
    {
        return array_replace_recursive(['discount_type'=>'percentage','discount_value'=>10,'discount_reason'=>'regular_customer','internal_note'=>null,'gst_rate'=>18,'government_charges'=>[['name'=>'Stamp Duty','amount'=>200,'note'=>'As applicable','display_order'=>0],['name'=>'Registration Fee','amount'=>100,'display_order'=>1]]],$overrides);
    }

    private function service(string $name,float $fee): Service
    {
        return Service::query()->create(['name_en'=>$name,'name_gu'=>$name,'slug'=>str($name)->slug().'-'.fake()->unique()->numberBetween(1,99999),'service_fee'=>$fee,'gst_rate'=>18,'estimated_days'=>5,'is_active'=>true]);
    }
}
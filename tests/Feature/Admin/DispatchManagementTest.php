<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_dispatches(): void
    {
        $request = $this->request();

        $this->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload())
            ->assertRedirect(route('login'));
    }

    public function test_dispatch_before_payment_is_rejected(): void
    {
        $request = $this->request(['payment_status' => 'pending']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload())
            ->assertSessionHasErrors('dispatch');

        $this->assertDatabaseCount('request_dispatches', 0);
        $this->assertSame('ready_for_registration', $request->fresh()->status);
    }

    public function test_office_collection_can_be_dispatched_without_a_tracking_number(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload([
                'dispatch_method' => 'office_collection',
                'tracking_number' => null,
                'carrier_name' => null,
                'customer_remark' => 'Documents are ready for office collection.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('request_dispatches', [
            'request_id' => $request->id,
            'dispatch_status' => 'dispatched',
            'dispatch_method' => 'office_collection',
            'tracking_number' => null,
            'performed_by' => $admin->id,
        ]);
        $this->assertSame('dispatched', $request->fresh()->status);
        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $request->id,
            'from_status' => 'ready_for_registration',
            'to_status' => 'dispatched',
            'is_visible_to_customer' => true,
        ]);
    }

    public function test_postal_and_courier_dispatch_require_tracking_details(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload([
                'dispatch_method' => 'india_post_speed_post',
                'tracking_number' => null,
            ]))
            ->assertSessionHasErrors('tracking_number');

        $this->actingAs($admin)
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload([
                'dispatch_method' => 'courier',
                'tracking_number' => 'TRACK-1001',
                'carrier_name' => null,
            ]))
            ->assertSessionHasErrors('carrier_name');

        $this->assertDatabaseCount('request_dispatches', 0);
    }

    public function test_delivery_update_is_appended_and_dispatch_history_is_preserved(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload())
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.requests.dispatches.store', $request), $this->dispatchPayload([
                'dispatch_status' => 'delivered',
                'dispatch_date' => '2026-08-02 15:30:00',
                'customer_remark' => 'Documents delivered successfully.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('request_dispatches', 2);
        $this->assertDatabaseHas('request_dispatches', [
            'request_id' => $request->id,
            'dispatch_status' => 'delivered',
            'customer_remark' => 'Documents delivered successfully.',
        ]);
    }

    public function test_direct_dispatched_workflow_transition_is_rejected_without_dispatch_record(): void
    {
        $request = $this->request();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.requests.transition', $request), ['status' => 'dispatched'])
            ->assertSessionHasErrors('status');

        $this->assertSame('ready_for_registration', $request->fresh()->status);
    }

    public function test_processing_managed_request_must_be_ready_for_dispatch(): void
    {
        $admin=User::factory()->create(); $request=$this->request();
        $request->processing()->create(['processing_stage'=>'registered']);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store',$request),$this->dispatchPayload())->assertSessionHasErrors('dispatch');
        $this->assertSame('ready_for_registration',$request->fresh()->status);
        $request->processing->update(['processing_stage'=>'ready_for_dispatch']);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store',$request),$this->dispatchPayload())->assertSessionHasNoErrors();
        $this->assertSame('dispatched',$request->fresh()->status);
        $this->assertSame('dispatched',$request->processing->fresh()->processing_stage);
        $this->assertDatabaseHas('request_processing_histories',['request_id'=>$request->id,'from_stage'=>'ready_for_dispatch','to_stage'=>'dispatched']);
    }

    public function test_public_tracking_shows_safe_dispatch_information_including_carrier_name(): void
    {
        $admin = User::factory()->create(['name' => 'Private Dispatch Admin']);
        $request = $this->request(['status' => 'dispatched']);
        $request->dispatches()->create([
            'dispatch_status' => 'dispatched',
            'dispatch_method' => 'courier',
            'dispatch_date' => '2026-08-01 12:30:00',
            'tracking_number' => 'PUBLIC-TRACK-1001',
            'carrier_name' => 'Trusted Courier',
            'internal_note' => 'Private dispatch handling instructions.',
            'customer_remark' => 'Your documents are on the way.',
            'performed_by' => $admin->id,
        ]);

        $this->post(route('request.track.lookup'), [
            'reference_no' => $request->reference_no,
            'mobile' => $request->mobile,
        ])->assertOk()
            ->assertSee('Dispatch Information')
            ->assertSee('Courier')
            ->assertSee('PUBLIC-TRACK-1001')
            ->assertSee('Trusted Courier')
            ->assertSee('Your documents are on the way.')
            ->assertDontSee('Private dispatch handling instructions.')
            ->assertDontSee('Private Dispatch Admin');
    }

    private function dispatchPayload(array $attributes = []): array
    {
        return [
            'dispatch_status' => 'dispatched',
            'dispatch_method' => 'courier',
            'dispatch_date' => '2026-08-01 12:30:00',
            'tracking_number' => 'TRACK-1001',
            'carrier_name' => 'Trusted Courier',
            ...$attributes,
        ];
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->firstOrCreate(['slug' => 'dispatch-test'], [
            'name_en' => 'Dispatch Test',
            'name_gu' => 'ડિસ્પેચ ટેસ્ટ',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000990',
            'file_number' => 'SC/2026/F000990',
            'request_origin' => 'online',
            'service_id' => $service->id,
            'name' => 'Dispatch Customer',
            'mobile' => '9999999999',
            'address' => 'Patan, Gujarat',
            'status' => 'ready_for_registration',
            'payment_status' => 'received',
            'last_status_changed_at' => now(),
            ...$attributes,
        ]);
    }
}

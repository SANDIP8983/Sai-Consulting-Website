<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DispatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_is_unavailable_before_completed_and_completed_enables_it(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'in_progress']);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasErrors('dispatch');
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertSee('Complete the case processing before adding dispatch or delivery details.')->assertDontSee('Create Dispatch');
        $request->update(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertSee('Create Dispatch');
    }

    public function test_whatsapp_requires_mobile_but_not_address_or_tracking(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $data = $this->payload(['dispatch_method' => 'whatsapp', 'recipient_mobile' => null, 'delivery_address' => null, 'carrier_name' => null, 'tracking_number' => null]);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasErrors('recipient_mobile');
        $data['recipient_mobile'] = '9999999999';
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasNoErrors();
    }

    public function test_email_requires_email_but_not_address_or_tracking(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $data = $this->payload(['dispatch_method' => 'email', 'recipient_email' => null, 'delivery_address' => null, 'carrier_name' => null, 'tracking_number' => null]);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasErrors('recipient_email');
        $data['recipient_email'] = 'customer@example.com';
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasNoErrors();
    }

    public function test_courier_requires_address_carrier_and_tracking_before_dispatched(): void
    {
        $admin = User::factory()->create();
        foreach (['delivery_address', 'carrier_name', 'tracking_number'] as $field) {
            $request = $this->request();
            $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload([$field => null]))->assertSessionHasErrors($field);
        }
    }

    public function test_office_collection_requires_collected_by_and_collection_time(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $data = $this->payload(['dispatch_method' => 'office_collection', 'dispatch_status' => 'collected', 'recipient_name' => null, 'collected_at' => null, 'tracking_number' => null, 'carrier_name' => null, 'delivery_address' => null]);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasErrors('recipient_name');
        $data['recipient_name'] = 'Customer';
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasErrors('collected_at');
        $data['collected_at'] = now()->format('Y-m-d H:i:s');
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasNoErrors();
        $this->assertSame('delivered', $request->fresh()->status);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $dispatch = $request->dispatches()->create([...$this->payload(['dispatch_status' => 'prepared']), 'performed_by' => $admin->id]);
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$request, $dispatch]), ['dispatch_status' => 'delivered', 'delivered_at' => now()])->assertSessionHasErrors('dispatch_status');
    }

    public function test_first_dispatch_updates_request_to_dispatched(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('dispatched', $request->fresh()->status);
        $this->assertDatabaseHas('request_dispatch_histories', ['request_id' => $request->id, 'action' => 'created', 'to_status' => 'dispatched']);
    }

    public function test_delivered_and_collected_update_request_without_closing_it(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasNoErrors();
        $dispatch = $request->dispatches()->first();
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$request, $dispatch]), ['dispatch_status' => 'delivered', 'delivered_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $request->fresh()->status);
        $this->assertNull($request->fresh()->closed_at);
    }

    public function test_close_is_unavailable_while_dispatch_is_unresolved(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasErrors('closure');
    }

    public function test_close_works_after_successful_delivery_and_locks_edits(): void
    {
        $admin = User::factory()->create();
        $request = $this->delivered($admin);
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasNoErrors();
        $this->assertSame('closed', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->closed_at);
        $dispatch = $request->dispatches()->first();
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.update', [$request, $dispatch]), $this->payload())->assertSessionHasErrors('dispatch');
        $this->assertDatabaseHas('request_case_action_histories', ['request_id' => $request->id, 'action' => 'closed']);
    }

    public function test_reopen_closed_case_requires_reason_and_is_audited(): void
    {
        $admin = User::factory()->create();
        $request = $this->delivered($admin);
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.closure.reopen', $request), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.requests.closure.reopen', $request), ['reason' => 'Customer requested correction'])->assertSessionHasNoErrors();
        $this->assertSame('delivered', $request->fresh()->status);
        $this->assertDatabaseHas('request_case_action_histories', ['request_id' => $request->id, 'action' => 'reopened_after_closure', 'reason' => 'Customer requested correction']);
    }

    public function test_failed_returned_and_cancelled_require_reason(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasNoErrors();
        $dispatch = $request->dispatches()->first();
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$request, $dispatch]), ['dispatch_status' => 'failed_returned'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$request, $dispatch]), ['dispatch_status' => 'failed_returned', 'reason' => 'Recipient unavailable'])->assertSessionHasNoErrors();
    }

    public function test_multiple_dispatch_records_are_preserved(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload(['dispatch_method' => 'whatsapp', 'recipient_mobile' => '9999999999', 'tracking_number' => null, 'carrier_name' => null, 'delivery_address' => null, 'document_description' => 'Draft PDF']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload(['document_description' => 'Final registered copy']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('request_dispatches', 2);
    }

    public function test_internal_notes_and_private_proofs_are_not_public(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['name' => 'Private Dispatch Admin']);
        $request = $this->request();
        $data = $this->payload(['internal_note' => 'Private handling note', 'customer_remark' => 'Your copy is on the way.', 'proof_type' => 'courier_receipt', 'proof' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')]);
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $data)->assertSessionHasNoErrors();
        $proof = $request->dispatches()->first()->proofs()->first();
        Storage::disk('local')->assertExists($proof->file_path);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Your copy is on the way.')->assertDontSee('Private handling note')->assertDontSee('receipt.pdf')->assertDontSee('Private Dispatch Admin');
        auth()->logout();
        $this->get(route('admin.requests.dispatches.proofs.download', [$request, $request->dispatches()->first(), $proof]))->assertRedirect(route('login'));
    }

    public function test_public_tracking_shows_customer_safe_dispatch_details(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload(['tracking_url' => 'https://tracking.example/ABC', 'customer_remark' => 'Customer-safe update']))->assertSessionHasNoErrors();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Dispatch &amp; Delivery', false)->assertSee('TRACK-1001')->assertSee('Trusted Courier')->assertSee('https://tracking.example/ABC')->assertSee('Customer-safe update');
    }

    public function test_legacy_dispatch_record_remains_readable(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'dispatched']);
        $request->dispatches()->create(['dispatch_status' => 'not_dispatched', 'dispatch_method' => 'india_post_speed_post', 'dispatch_date' => now(), 'tracking_number' => 'LEGACY-1', 'performed_by' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertSee('Prepared (Legacy)')->assertSee('Speed Post (Legacy)')->assertSee('LEGACY-1');
    }

    public function test_dispatch_record_cannot_be_modified_through_another_request(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $other = $this->request();
        $dispatch = $request->dispatches()->create([...$this->payload(['dispatch_status' => 'prepared']), 'performed_by' => $admin->id]);
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$other, $dispatch]), ['dispatch_status' => 'dispatched'])->assertNotFound();
        $this->assertSame('prepared', $dispatch->fresh()->dispatch_status);
    }

    public function test_completed_historical_case_with_resolved_scopes_ignores_stale_legacy_processing_stage(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $selected = $request->requestServices()->create(['service_id' => $request->service_id, 'service_name_en_snapshot' => 'Historical Service', 'professional_fee' => 0, 'status' => 'rejected']);
        $selected->workScopes()->create(['name_en_snapshot' => 'Historical completed work', 'is_custom' => true, 'status' => 'completed', 'selected_by' => $admin->id]);
        $request->processing()->create(['processing_stage' => 'customer_verification_pending', 'requires_dispatch' => true, 'requires_payment_before_processing' => true]);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertSee('Create Dispatch')->assertDontSee('Legacy processing must be completed before dispatch.');
    }

    private function delivered(User $admin): CustomerRequest
    {
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.dispatches.store', $request), $this->payload())->assertSessionHasNoErrors();
        $dispatch = $request->dispatches()->first();
        $this->actingAs($admin)->patch(route('admin.requests.dispatches.status', [$request, $dispatch]), ['dispatch_status' => 'delivered', 'delivered_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();

        return $request->fresh();
    }

    private function closure(): array
    {
        return ['closure_date' => now()->format('Y-m-d H:i:s'), 'customer_remark' => 'Case finished', 'internal_note' => 'Verified closure', 'confirmed' => '1'];
    }

    private function payload(array $attributes = []): array
    {
        return ['dispatch_status' => 'dispatched', 'dispatch_method' => 'courier', 'dispatch_date' => now()->subMinute()->format('Y-m-d H:i:s'), 'document_description' => 'Final document package', 'recipient_name' => 'Customer', 'recipient_mobile' => '9999999999', 'recipient_email' => 'customer@example.com', 'delivery_address' => 'Patan, Gujarat', 'tracking_number' => 'TRACK-1001', 'carrier_name' => 'Trusted Courier', ...$attributes];
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'Dispatch '.$suffix, 'name_gu' => 'ડિસ્પેચ '.$suffix, 'slug' => 'dispatch-'.$suffix, 'is_active' => true, 'requires_dispatch' => true, 'requires_payment_before_processing' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'file_number' => 'SC/2026/F'.fake()->unique()->numerify('######'), 'request_origin' => 'online', 'service_id' => $service->id, 'name' => 'Dispatch Customer', 'mobile' => '9999999999', 'email' => 'customer@example.com', 'address' => 'Patan, Gujarat', 'status' => 'completed', 'payment_status' => 'received', 'completed_at' => now(), 'last_status_changed_at' => now(), ...$attributes]);
        $request->billing()->create(['total_original_professional_fee' => 1000, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 1000, 'gst_rate' => 0, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => 1000, 'pricing_locked_at' => now()]);
        $request->payments()->create(['amount' => 1000, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()]);

        return $request;
    }
}

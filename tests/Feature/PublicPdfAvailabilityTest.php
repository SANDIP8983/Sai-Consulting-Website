<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPdfAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_pdfs_are_available_only_after_their_workflow_stage(): void
    {
        $service = Service::query()->create([
            'name_en' => 'PDF stage service',
            'name_gu' => 'PDF stage service',
            'slug' => 'pdf-stage-service',
            'is_active' => true,
        ]);
        $request = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/990001',
            'service_id' => $service->id,
            'name' => 'PDF Customer',
            'mobile' => '9999999999',
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $this->verifyTracking($request)
            ->assertSee('Request Acknowledgement')
            ->assertDontSee('Payment Summary')
            ->assertDontSee('Case Summary')
            ->assertDontSee('Dispatch Slip');

        foreach (['payment-summary', 'case-summary', 'dispatch-slip'] as $type) {
            $this->get(route('request.track.pdf', [$request, $type]))->assertNotFound();
        }

        $request->billing()->create([
            'total_original_professional_fee' => 1000,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_professional_fee' => 1000,
            'gst_rate' => 18,
            'gst_amount' => 180,
            'government_charges_total' => 0,
            'grand_total' => 1180,
            'pricing_locked_at' => now(),
        ]);

        $this->verifyTracking($request)->assertSee('Payment Summary')->assertDontSee('Case Summary');
        $this->get(route('request.track.pdf', [$request, 'payment-summary']))->assertOk();

        $request->update(['status' => 'completed', 'completed_at' => now()]);
        $this->verifyTracking($request)->assertSee('Case Summary')->assertDontSee('Dispatch Slip');
        $this->get(route('request.track.pdf', [$request, 'case-summary']))->assertOk();
    }

    private function verifyTracking(CustomerRequest $request)
    {
        return $this->post(route('request.track.lookup'), [
            'reference_no' => $request->reference_no,
            'mobile' => $request->mobile,
        ])->assertOk();
    }
}

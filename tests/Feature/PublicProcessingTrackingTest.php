<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProcessingTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_shows_only_customer_safe_processing_information(): void
    {
        $admin=User::factory()->create(['name'=>'Private Processing Admin']);
        $service=Service::query()->create(['name_en'=>'Registration','name_gu'=>'Registration','slug'=>'public-processing','is_active'=>true,'sort_order'=>1]);
        $request=CustomerRequest::query()->create(['reference_no'=>'SC/2026/000800','file_number'=>'SC/2026/F000800','service_id'=>$service->id,'name'=>'Customer','mobile'=>'9999999999','status'=>'ready_for_registration','payment_status'=>'received']);
        $request->processing()->create(['processing_stage'=>'registered','token_booking_status'=>'booked','token_scheduled_at'=>'2026-08-02 10:00','registration_date'=>'2026-08-03','registration_number'=>'PRIVATE-REG-100','registration_number_public'=>false,'certified_copy_status'=>'pending','internal_file_note'=>'Private file note.','drafting_internal_note'=>'Private drafting note.','registration_internal_note'=>'Private registration note.']);
        $request->processingHistory()->create(['from_stage'=>'registration_pending','to_stage'=>'registered','remarks'=>'Registration completed successfully.','is_visible_to_customer'=>true,'changed_by'=>$admin->id]);
        $request->processingHistory()->create(['from_stage'=>'registered','to_stage'=>'registered','remarks'=>'Private correction discussion.','is_visible_to_customer'=>false,'changed_by'=>$admin->id]);

        $this->post(route('request.track.lookup'),['reference_no'=>$request->reference_no,'mobile'=>$request->mobile])->assertOk()->assertSee('File Processing')->assertSee('Token')->assertSee('Booked')->assertSee('Registration Completed')->assertSee('Certified Copy')->assertSee('Registration completed successfully.')->assertDontSee('PRIVATE-REG-100')->assertDontSee('Private file note.')->assertDontSee('Private drafting note.')->assertDontSee('Private registration note.')->assertDontSee('Private correction discussion.')->assertDontSee('Private Processing Admin');

        $request->processing->update(['registration_number_public'=>true]);
        $this->post(route('request.track.lookup'),['reference_no'=>$request->reference_no,'mobile'=>$request->mobile])->assertOk()->assertSee('PRIVATE-REG-100');
    }
}

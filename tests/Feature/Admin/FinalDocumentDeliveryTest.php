<?php

namespace Tests\Feature\Admin;

use App\Contracts\WhatsAppChannelInterface;
use App\Jobs\SendFinalDocumentDeliveryJob;
use App\Mail\CustomerFinalDocumentsMail;
use App\Models\CustomerRequest;
use App\Models\RequestFinalDocument;
use App\Models\RequestFinalDocumentDelivery;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class FinalDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_one_private_final_document_linked_to_the_request(): void
    {
        Storage::fake('local');
        $request = $this->customerRequest();

        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.store', $request), [
            'documents' => [$this->pdf('final-title-report.pdf')],
        ])->assertSessionHasNoErrors();

        $document = RequestFinalDocument::query()->sole();
        $this->assertSame($request->id, $document->request_id);
        $this->assertSame('final-title-report.pdf', $document->original_name);
        $this->assertStringStartsWith("customer-requests/{$request->id}/final-documents/", $document->storage_path);
        $this->assertStringNotContainsString('final-title-report', $document->storage_path);
        Storage::disk('local')->assertExists($document->storage_path);
        Storage::disk('public')->assertMissing($document->storage_path);
        $this->get('/storage/'.$document->storage_path)->assertForbidden();
        $this->get(route('admin.requests.show', $request))->assertOk()->assertSee('Final Documents / Customer Delivery')->assertSee('WhatsApp delivery stays unavailable');
    }

    public function test_admin_can_upload_multiple_final_documents(): void
    {
        Storage::fake('local');
        $request = $this->customerRequest();

        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.store', $request), [
            'documents' => [
                $this->pdf('registered-document.pdf'),
                UploadedFile::fake()->create('cover-letter.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('request_final_documents', 2);
        $this->assertSame(2, $request->finalDocuments()->count());
    }

    public function test_invalid_and_oversized_final_documents_are_rejected(): void
    {
        Storage::fake('local');
        $request = $this->customerRequest();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.requests.final-documents.store', $request), [
            'documents' => [UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('documents.0');
        $this->actingAs($admin)->post(route('admin.requests.final-documents.store', $request), [
            'documents' => [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
        ])->assertSessionHasErrors('documents.0');

        $this->assertDatabaseCount('request_final_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_admin_download_requires_authorization_and_correct_request_ownership(): void
    {
        Storage::fake('local');
        [$request, $document] = $this->storedDocument();
        $other = $this->customerRequest();

        auth()->logout();
        $this->get(route('admin.requests.final-documents.download', [$request, $document]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.requests.final-documents.download', [$request, $document]))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.requests.final-documents.download', [$other, $document]))->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.requests.final-documents.download', [$request, $document]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_missing_customer_email_blocks_delivery_gracefully(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->mock(WhatsAppChannelInterface::class, fn (MockInterface $mock) => $mock->shouldNotReceive('send'));
        [$request, $document] = $this->storedDocument(['email' => null]);

        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.send', $request), [
            'channel' => 'email', 'document_ids' => [$document->id],
        ])->assertSessionHasErrors('customer_email');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('request_final_document_deliveries', 0);
    }

    public function test_selected_final_documents_queue_once_and_write_delivery_audit(): void
    {
        Storage::fake('local');
        Queue::fake();
        $request = $this->customerRequest();
        [$first, $second] = $this->uploadDocuments($request, ['first.pdf', 'second.pdf']);
        $payload = ['channel' => 'email', 'document_ids' => [$first->id]];

        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.send', $request), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.send', $request), $payload)->assertSessionHasErrors('document_ids');

        Queue::assertPushed(SendFinalDocumentDeliveryJob::class, 1);
        Queue::assertPushed(SendFinalDocumentDeliveryJob::class, fn ($job) => $job->queue === 'customer-notifications');
        $delivery = RequestFinalDocumentDelivery::query()->sole();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame([$first->id], $delivery->documents()->pluck('request_final_documents.id')->all());
        $this->assertNotContains($second->id, $delivery->documents()->pluck('request_final_documents.id')->all());
        $this->assertDatabaseHas('request_final_document_deliveries', ['request_id' => $request->id, 'channel' => 'email', 'recipient_masked' => 'c***@example.com']);
        $this->assertDatabaseMissing('request_final_document_deliveries', ['channel' => 'whatsapp']);
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_delivery_job_sends_exactly_once_with_selected_final_documents_and_no_attachments(): void
    {
        Storage::fake('local');
        Queue::fake();
        Mail::fake();
        URL::forceRootUrl('https://saiconsultingchanasma.in');
        URL::forceScheme('https');
        $request = $this->customerRequest();
        [$selected, $unselected] = $this->uploadDocuments($request, ['customer-copy.pdf', 'admin-only.pdf']);
        $request->documents()->create(['file_name' => 'original-land-record.pdf', 'file_path' => 'customer-requests/original.pdf', 'file_type' => 'application/pdf']);

        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.send', $request), ['channel' => 'email', 'document_ids' => [$selected->id]]);
        $delivery = RequestFinalDocumentDelivery::query()->sole();
        $job = new SendFinalDocumentDeliveryJob($delivery->id);
        $job->handle();
        $job->handle();

        Mail::assertSent(CustomerFinalDocumentsMail::class, function (CustomerFinalDocumentsMail $mail) use ($selected, $unselected): bool {
            $html = $mail->render();

            return $mail->hasTo('customer@example.com')
                && str_contains($html, $selected->original_name)
                && str_contains($html, 'https://saiconsultingchanasma.in/request/final-documents/')
                && ! str_contains($html, $unselected->original_name)
                && ! str_contains($html, 'original-land-record.pdf')
                && $mail->attachments === []
                && $mail->rawAttachments === []
                && $mail->diskAttachments === [];
        });
        Mail::assertSent(CustomerFinalDocumentsMail::class, 1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempt_count);
    }

    public function test_only_sent_documents_appear_in_verified_tracking_and_cross_request_access_is_denied(): void
    {
        Storage::fake('local');
        $request = $this->customerRequest();
        [$sent, $unsent] = $this->uploadDocuments($request, ['released.pdf', 'internal-draft.pdf']);
        $delivery = $request->finalDocumentDeliveries()->create([
            'channel' => 'email', 'status' => 'sent', 'recipient_masked' => 'c***@example.com',
            'recipient_hash' => hash('sha256', 'customer@example.com'), 'idempotency_key' => hash('sha256', 'tracking-test'),
            'initiated_by' => $this->admin()->id, 'queued_at' => now(), 'sent_at' => now(),
        ]);
        $delivery->documents()->attach($sent);

        $response = $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk();
        $response->assertSee('released.pdf')->assertDontSee('internal-draft.pdf');
        $this->get(route('request.track.final-documents.download', [$request, $sent]))->assertOk();
        $this->get(route('request.track.final-documents.download', [$request, $unsent]))->assertNotFound();
        $this->get(URL::temporarySignedRoute('request.final-documents.signed', now()->addDay(), [$request, $sent]))->assertOk();

        $other = $this->customerRequest();
        $this->withSession(['public_tracking.verified_requests.'.$other->id => now()->timestamp])
            ->get(route('request.track.final-documents.download', [$other, $sent]))->assertNotFound();
        $crossRequestSignedUrl = URL::temporarySignedRoute('request.final-documents.signed', now()->addDay(), [$other, $sent]);
        $this->get($crossRequestSignedUrl)->assertNotFound();
    }

    public function test_transport_failure_is_recorded_without_changing_request_status(): void
    {
        Storage::fake('local');
        Queue::fake();
        $request = $this->customerRequest();
        $document = $this->uploadDocuments($request, ['delivery-failure.pdf'])[0];
        $this->actingAs($this->admin())->post(route('admin.requests.final-documents.send', $request), ['channel' => 'email', 'document_ids' => [$document->id]]);
        $delivery = RequestFinalDocumentDelivery::query()->sole();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        try {
            (new SendFinalDocumentDeliveryJob($delivery->id))->handle();
            $this->fail('Expected a retryable transport failure.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('request_final_document_deliveries', [
                'id' => $delivery->id,
                'status' => 'failed',
                'failure_category' => 'transport_failure',
            ]);
            $this->assertSame('completed', $request->fresh()->status);
        }
    }

    public function test_whatsapp_final_document_delivery_is_not_available_and_calls_no_provider(): void
    {
        Mail::fake();
        $this->mock(WhatsAppChannelInterface::class, fn (MockInterface $mock) => $mock->shouldNotReceive('send'));
        $request = $this->customerRequest();
        $delivery = $request->finalDocumentDeliveries()->create([
            'channel' => 'whatsapp', 'status' => 'pending', 'recipient_masked' => '********9999',
            'recipient_hash' => hash('sha256', '919999999999'), 'idempotency_key' => hash('sha256', 'disabled-whatsapp-delivery'),
            'initiated_by' => $this->admin()->id, 'queued_at' => now(),
        ]);

        (new SendFinalDocumentDeliveryJob($delivery->id))->handle();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('request_final_document_deliveries', [
            'id' => $delivery->id,
            'status' => 'skipped',
            'failure_category' => 'channel_not_available',
        ]);
    }

    public function test_unsent_document_can_be_removed_but_audited_document_cannot(): void
    {
        Storage::fake('local');
        Queue::fake();
        $request = $this->customerRequest();
        [$unsent, $queued] = $this->uploadDocuments($request, ['unsent.pdf', 'queued.pdf']);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.requests.final-documents.send', $request), ['channel' => 'email', 'document_ids' => [$queued->id]]);

        $this->actingAs($admin)->delete(route('admin.requests.final-documents.destroy', [$request, $unsent]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($unsent->storage_path);
        $this->actingAs($admin)->delete(route('admin.requests.final-documents.destroy', [$request, $queued]))->assertSessionHasErrors('final_document');
        Storage::disk('local')->assertExists($queued->storage_path);
    }

    private function storedDocument(array $attributes = []): array
    {
        $request = $this->customerRequest($attributes);
        $document = $this->uploadDocuments($request, ['final.pdf'])[0];

        return [$request, $document];
    }

    private function uploadDocuments(CustomerRequest $request, array $names): array
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.requests.final-documents.store', $request), [
            'documents' => collect($names)->map(fn ($name) => $this->pdf($name))->all(),
        ])->assertSessionHasNoErrors();

        return $request->finalDocuments()->orderBy('id')->get()->all();
    }

    private function customerRequest(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'Final Document Service '.$suffix, 'name_gu' => 'Final Document Service '.$suffix, 'slug' => 'final-docs-'.$suffix, 'is_active' => true]);

        return CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'),
            'service_id' => $service->id,
            'name' => 'Customer',
            'mobile' => '9999999999',
            'email' => 'customer@example.com',
            'status' => 'completed',
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
    }
}

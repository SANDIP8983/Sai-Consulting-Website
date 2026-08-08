<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicRequestUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('approvedPublicFiles')]
    public function test_approved_public_file_types_are_accepted(string $extension): void
    {
        Storage::fake('local');
        $service = $this->service();
        $payload = $this->payload($service);
        $payload['documents'] = [$this->approvedFile($extension)];

        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));

        $document = CustomerRequest::query()->sole()->documents()->sole();
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertMatchesRegularExpression(
            '#^customer-requests/\d+/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(pdf|jpg|png)$#',
            $document->file_path,
        );
    }

    public static function approvedPublicFiles(): array
    {
        return [
            'PDF' => ['pdf'],
            'JPG' => ['jpg'],
            'JPEG' => ['jpeg'],
            'PNG' => ['png'],
        ];
    }

    public function test_doc_and_docx_are_rejected_even_when_service_configuration_permits_them(): void
    {
        Storage::fake('local');
        $service = $this->service(['doc', 'docx'], 51200);

        foreach (['doc', 'docx'] as $extension) {
            $payload = $this->payload($service);
            $payload['mobile'] = $extension === 'doc' ? '9111111111' : '9222222222';
            $payload['documents'] = [UploadedFile::fake()->create(
                "property-record.{$extension}",
                20,
                $extension === 'doc' ? 'application/msword' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            )];

            $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        }

        $this->assertDatabaseCount('requests', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_file_over_ten_megabytes_is_rejected_even_when_configuration_permits_more(): void
    {
        Storage::fake('local');
        $service = $this->service(['pdf'], 51200);
        $payload = $this->payload($service);
        $payload['documents'] = [UploadedFile::fake()->create('large-property-record.pdf', 10241, 'application/pdf')];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_extension_and_detected_mime_must_be_consistent(): void
    {
        Storage::fake('local');
        $payload = $this->payload($this->service());
        $payload['documents'] = [UploadedFile::fake()->createWithContent('property-photo.jpg', $this->pdfContent())];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_suspicious_kyc_filename_is_rejected_logged_without_content_and_not_persisted(): void
    {
        Storage::fake('local');
        Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Public document upload rejected.'
            && $context['reason'] === 'prohibited_filename'
            && isset($context['filename_fingerprint'], $context['request_ip_fingerprint'])
            && ! array_key_exists('content', $context));
        $payload = $this->payload($this->service());
        $payload['documents'] = [UploadedFile::fake()->createWithContent('customer-aadhaar-card.pdf', $this->pdfContent())];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');

        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('request_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_path_like_filename_is_rejected_and_cannot_escape_private_request_storage(): void
    {
        Storage::fake('local');
        $payload = $this->payload($this->service());
        $payload['documents'] = [UploadedFile::fake()->createWithContent('../../outside.pdf', $this->pdfContent())];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_executable_content_disguised_with_an_approved_extension_is_rejected(): void
    {
        Storage::fake('local');
        $payload = $this->payload($this->service());
        $payload['documents'] = [UploadedFile::fake()->createWithContent('property-record.pdf', "MZ\x90\x00executable")];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_normal_public_request_submissions_remain_allowed(): void
    {
        $service = $this->service();

        foreach (range(1, 2) as $number) {
            $payload = $this->payload($service);
            $payload['name'] = "Rate Limit Customer {$number}";
            $payload['mobile'] = '900000000'.$number;
            $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        }

        $this->assertDatabaseCount('requests', 2);
    }

    public function test_excessive_rapid_submissions_are_throttled_without_data_side_effects(): void
    {
        $service = $this->service();

        foreach (range(1, 10) as $number) {
            $payload = $this->payload($service);
            $payload['name'] = "Allowed Customer {$number}";
            $payload['mobile'] = (string) (9100000000 + $number);
            $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        }

        $blockedPayload = $this->payload($service);
        $blockedPayload['name'] = 'Blocked Customer';
        $blockedPayload['mobile'] = '9199999999';
        $this->from(route('request.create'))->post(route('request.store'), $blockedPayload)
            ->assertRedirect(route('request.create'))
            ->assertSessionHasErrors('request');

        $this->assertDatabaseCount('requests', 10);
        $this->assertDatabaseCount('request_services', 10);
        $this->assertDatabaseCount('request_status_histories', 10);
        $this->assertDatabaseMissing('requests', ['name' => 'Blocked Customer']);
    }

    private function service(array $types = ['pdf', 'jpg', 'jpeg', 'png'], int $maximumKilobytes = 10240): Service
    {
        $service = Service::query()->create([
            'name_en' => 'Secure Public Upload',
            'name_gu' => 'Secure Public Upload',
            'slug' => 'secure-public-upload-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
            'available_online' => true,
            'requires_property_documents' => false,
        ]);
        $service->requiredDocuments()->create([
            'name_en' => 'Property Record',
            'name_gu' => 'Property Record',
            'allowed_file_types' => $types,
            'max_upload_size_kb' => $maximumKilobytes,
            'is_active' => true,
        ]);

        return $service;
    }

    private function payload(Service $service): array
    {
        return [
            'service_ids' => [$service->id],
            'name' => 'Security Test Customer',
            'mobile' => '9999999999',
            'declaration' => '1',
        ];
    }

    private function approvedFile(string $extension): UploadedFile
    {
        return match ($extension) {
            'pdf' => UploadedFile::fake()->createWithContent('property-record.pdf', $this->pdfContent()),
            'jpg' => UploadedFile::fake()->image('property-photo.jpg', 20, 20),
            'jpeg' => UploadedFile::fake()->image('property-photo.jpeg', 20, 20),
            'png' => UploadedFile::fake()->image('property-map.png', 20, 20),
        };
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}

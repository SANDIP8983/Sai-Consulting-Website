<?php

namespace App\Data\Pdf;

use App\Enums\PdfDocumentType;
use Illuminate\Support\Carbon;

final readonly class PdfDocumentData
{
    public function __construct(
        public PdfDocumentType $type,
        public string $referenceNumber,
        public ?string $fileNumber,
        public Carbon $generatedAt,
        public array $company,
        public array $content,
        public ?string $watermark = null,
        public bool $showSignaturePlaceholder = false,
        public bool $showQrPlaceholder = false,
    ) {}

    public function filename(): string
    {
        $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->referenceNumber);

        return strtolower($this->type->value).'-'.trim((string) $reference, '-').'.pdf';
    }
}

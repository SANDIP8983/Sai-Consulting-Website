<?php

namespace App\Services\Pdf;

use App\Data\Pdf\PdfDocumentData;
use App\Enums\PdfDocumentType;
use App\Models\CustomerRequest;
use Illuminate\Http\Response;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PdfGenerationService
{
    public function __construct(private readonly CustomerSafePdfDataFactory $data) {}

    public function forRequest(PdfDocumentType $type, CustomerRequest $request): PdfDocumentData
    {
        return $this->data->make($type, $request);
    }

    public function render(PdfDocumentData $document): string
    {
        $default = (new ConfigVariables)->getDefaults();
        $fonts = (new FontVariables)->getDefaults();
        $tempDir = storage_path('framework/cache/mpdf');
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            throw new \RuntimeException('Unable to create the PDF temporary directory.');
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => config('pdf.format'), 'orientation' => config('pdf.orientation'),
            'margin_left' => config('pdf.margins.left'), 'margin_right' => config('pdf.margins.right'),
            'margin_top' => config('pdf.margins.top'), 'margin_bottom' => config('pdf.margins.bottom'),
            'fontDir' => [...$default['fontDir'], config('pdf.font_dir')],
            'fontdata' => $fonts['fontdata'] + [config('pdf.font_family') => ['R' => config('pdf.font_regular'), 'B' => config('pdf.font_bold'), 'useOTL' => 0xFF, 'useKashida' => 0]],
            'default_font' => config('pdf.font_family'), 'tempDir' => $tempDir,
            'useSubstitutions' => true,
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = false;
        $mpdf->SetTitle($document->type->title().' - '.$document->referenceNumber);
        $mpdf->SetAuthor($document->company['name']);
        $mpdf->SetDisplayMode('fullpage');
        if ($document->watermark) {
            $mpdf->SetWatermarkText($document->watermark);
            $mpdf->showWatermarkText = true;
        }
        $mpdf->WriteHTML(view($document->type->view(), ['document' => $document])->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    public function download(PdfDocumentType $type, CustomerRequest $request): Response
    {
        $document = $this->forRequest($type, $request);

        return response($this->render($document), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$document->filename().'"', 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }
}

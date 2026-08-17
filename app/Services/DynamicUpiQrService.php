<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

class DynamicUpiQrService
{
    public function render(string $paymentUri): string
    {
        $qrCode = new QrCode(
            data: $paymentUri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return (new SvgWriter)->write($qrCode)->getString();
    }
}

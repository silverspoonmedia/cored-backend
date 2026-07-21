<?php

namespace App\Domain\PaymentGateway\Http\Controllers\Web;

use App\Http\Controllers\Web\Controller;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Generates a QR code image locally so the checkout screen never leaks the
 * payment payload to a third-party QR service. Replaces the previous
 * `https://api.qrserver.com/...` fallback shipped in instructionMedia.js.
 */
class QrCodeController extends Controller
{
    public function show(Request $request): Response
    {
        $payload = (string) $request->query('data', '');

        if ($payload === '') {
            return response('Missing data.', 422);
        }

        $options = new QROptions([
            'version' => -1, // auto
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 8,
            'imageTransparent' => false,
        ]);

        $png = (new QRCode($options))->render($payload);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

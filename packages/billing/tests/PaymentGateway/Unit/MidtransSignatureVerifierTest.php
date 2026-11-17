<?php

namespace App\Domain\PaymentGateway\Tests\Unit;

use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransSignatureVerifier;
use Tests\TestCase;

class MidtransSignatureVerifierTest extends TestCase
{
    public function test_valid_signature_returns_true(): void
    {
        $serverKey = 'SB-Mid-server-test-key';
        $orderId = 'INV-20260710-ABC123';
        $statusCode = '200';
        $grossAmount = '150000.00';

        $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $this->assertTrue(MidtransSignatureVerifier::isValid([
            'signature_key' => $signatureKey,
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
        ], $serverKey));
    }

    public function test_wrong_signature_returns_false(): void
    {
        $this->assertFalse(MidtransSignatureVerifier::isValid([
            'signature_key' => hash('sha512', 'tampered'),
            'order_id' => 'INV-1',
            'status_code' => '200',
            'gross_amount' => '10000.00',
        ], 'SB-Mid-server-test-key'));
    }

    public function test_missing_required_fields_returns_false(): void
    {
        $serverKey = 'SB-Mid-server-test-key';

        $this->assertFalse(MidtransSignatureVerifier::isValid([
            'order_id' => 'INV-1',
            'status_code' => '200',
            'gross_amount' => '10000.00',
        ], $serverKey));

        $this->assertFalse(MidtransSignatureVerifier::isValid([
            'signature_key' => 'abc',
            'status_code' => '200',
            'gross_amount' => '10000.00',
        ], $serverKey));

        $this->assertFalse(MidtransSignatureVerifier::isValid([
            'signature_key' => 'abc',
            'order_id' => '',
            'status_code' => '200',
            'gross_amount' => '10000.00',
        ], $serverKey));
    }

    public function test_numeric_status_code_and_gross_amount_are_cast_to_string(): void
    {
        $serverKey = 'SB-Mid-server-test-key';
        $orderId = 'INV-20260710-NUM01';
        $statusCode = 200;
        $grossAmount = 75000;

        $signatureKey = hash('sha512', $orderId.(string) $statusCode.(string) $grossAmount.$serverKey);

        $this->assertTrue(MidtransSignatureVerifier::isValid([
            'signature_key' => $signatureKey,
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
        ], $serverKey));
    }
}

<?php

namespace Tests\Domain\PaymentGateway\Unit;

use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuSymmetricCallbackSignature;
use Tests\TestCase;

class IpaymuSymmetricCallbackSignatureTest extends TestCase
{
    public function test_matches_sorted_payload_hmac(): void
    {
        $merchantVa = '1179000899';
        $payload = [
            'amount' => 50000,
            'reference_id' => 'INV-001',
            'status' => 'berhasil',
            'trx_id' => 'TRX-123',
        ];

        $work = $payload;
        ksort($work);
        $receivedHex = hash_hmac('sha256', json_encode($work), $merchantVa);

        $this->assertTrue(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac(
            $payload,
            $receivedHex,
            $merchantVa,
        ));
    }

    public function test_rejects_empty_received_hex_or_merchant_va(): void
    {
        $payload = ['status' => 'berhasil'];

        $this->assertFalse(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac($payload, '', '1179000899'));
        $this->assertFalse(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac($payload, 'abc123', ''));
        $this->assertFalse(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac($payload, '   ', '1179000899'));
    }

    public function test_ignores_signature_field_in_payload(): void
    {
        $merchantVa = '1179000899';
        $payload = [
            'amount' => 25000,
            'reference_id' => 'INV-002',
            'signature' => 'must-be-ignored',
            'status' => 'berhasil',
        ];

        $work = $payload;
        unset($work['signature']);
        ksort($work);
        $receivedHex = hash_hmac('sha256', json_encode($work), $merchantVa);

        $this->assertTrue(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac(
            $payload,
            $receivedHex,
            $merchantVa,
        ));
    }

    public function test_rejects_tampered_payload(): void
    {
        $merchantVa = '1179000899';
        $payload = [
            'amount' => 25000,
            'reference_id' => 'INV-002',
            'status' => 'berhasil',
        ];

        $work = $payload;
        ksort($work);
        $receivedHex = hash_hmac('sha256', json_encode($work), $merchantVa);

        $payload['amount'] = 99999;

        $this->assertFalse(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac(
            $payload,
            $receivedHex,
            $merchantVa,
        ));
    }

    public function test_matches_with_json_unescaped_slashes_flag(): void
    {
        $merchantVa = '1179000899';
        $payload = [
            'callback_url' => 'https://example.com/ipaymu/callback',
            'status' => 'berhasil',
        ];

        $work = $payload;
        ksort($work);
        $receivedHex = hash_hmac('sha256', json_encode($work, JSON_UNESCAPED_SLASHES), $merchantVa);

        $this->assertTrue(IpaymuSymmetricCallbackSignature::matchesSortedPayloadHmac(
            $payload,
            $receivedHex,
            $merchantVa,
        ));
    }
}

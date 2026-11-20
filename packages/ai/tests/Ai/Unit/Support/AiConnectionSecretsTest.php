<?php

namespace App\Domain\Ai\Tests\Unit\Support;

use App\Domain\Ai\Exceptions\AiException;
use App\Domain\Ai\Support\AiConnectionSecrets;
use App\Domain\Ai\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AiConnectionSecretsTest extends TestCase
{
    #[Test]
    public function normalize_trims_and_canonicalizes(): void
    {
        $payload = AiConnectionSecrets::normalizePayload(['api_key' => '  sk-abc  ']);

        $this->assertSame(['api_key' => 'sk-abc'], $payload);
    }

    #[Test]
    public function a_missing_or_blank_api_key_is_rejected(): void
    {
        foreach ([['api_key' => ''], ['api_key' => '   '], []] as $input) {
            try {
                AiConnectionSecrets::normalizePayload($input);
                $this->fail('Expected AiException for '.json_encode($input));
            } catch (AiException $e) {
                $this->assertSame(422, $e->statusCode());
            }
        }
    }

    #[Test]
    public function unknown_keys_are_rejected(): void
    {
        $this->expectException(AiException::class);

        AiConnectionSecrets::normalizePayload([
            'api_key' => 'sk-abc',
            'client_secret' => 'nope',
        ]);
    }

    #[Test]
    public function fingerprint_is_stable_and_does_not_leak_the_key(): void
    {
        $payload = ['api_key' => 'sk-super-secret'];

        $first = AiConnectionSecrets::fingerprint($payload);
        $second = AiConnectionSecrets::fingerprint($payload);

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString('sk-super-secret', $first);
        $this->assertSame(64, strlen($first));
    }

    #[Test]
    public function fingerprint_changes_with_the_key(): void
    {
        $this->assertNotSame(
            AiConnectionSecrets::fingerprint(['api_key' => 'sk-one']),
            AiConnectionSecrets::fingerprint(['api_key' => 'sk-two']),
        );
    }

    #[Test]
    public function hint_keeps_only_the_last_four_characters(): void
    {
        $this->assertSame('…w9Qx', AiConnectionSecrets::hint(['api_key' => 'sk-super-secret-w9Qx']));
        $this->assertSame('', AiConnectionSecrets::hint([]));
    }

    #[Test]
    public function allowed_keys_is_api_key_only(): void
    {
        $this->assertSame(['api_key'], AiConnectionSecrets::allowedKeys());
    }
}

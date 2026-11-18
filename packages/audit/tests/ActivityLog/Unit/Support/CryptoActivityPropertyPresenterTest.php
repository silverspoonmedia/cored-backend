<?php

namespace App\Domain\ActivityLog\Tests\Unit\Support;

use App\Domain\ActivityLog\Support\CryptoActivityPropertyPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CryptoActivityPropertyPresenterTest extends TestCase
{
    #[DataProvider('kmsKeyIdMaskingProvider')]
    public function test_mask_kms_key_id(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, CryptoActivityPropertyPresenter::maskKmsKeyId($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function kmsKeyIdMaskingProvider(): array
    {
        $ocid = 'ocid1.key.ap-singapore-1.abcdefghijklmnopqrstuvwxyz';

        return [
            'null' => [null, null],
            'empty' => ['', ''],
            'local dev' => ['local', 'local'],
            'short value' => ['short', '***hort'],
            'oci truncated' => [$ocid, 'ocid1.key....stuvwxyz'],
        ];
    }

    public function test_for_api_masks_kms_key_id_only(): void
    {
        $ocid = 'ocid1.key.ap-singapore-1.abcdefghijklmnopqrstuvwxyz';

        $result = CryptoActivityPropertyPresenter::forApi([
            'resource_type' => 'users',
            'kms_key_id' => $ocid,
            'field' => 'two_factor_secret',
        ]);

        $this->assertSame('ocid1.key....stuvwxyz', $result['kms_key_id']);
        $this->assertSame('users', $result['resource_type']);
        $this->assertSame('two_factor_secret', $result['field']);
    }
}

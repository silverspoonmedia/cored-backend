<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentKeyWriter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EnvironmentKeyWriterTest extends TestCase
{
    private EnvironmentKeyWriter $writer;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = new EnvironmentKeyWriter;
        $this->tempPath = storage_path('framework/testing-env-'.uniqid().'.env');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempPath)) {
            File::delete($this->tempPath);
        }

        parent::tearDown();
    }

    public function test_it_reads_app_key_and_previous_keys_from_quoted_values(): void
    {
        File::put($this->tempPath, implode("\n", [
            'APP_KEY="base64:abc123"',
            'APP_PREVIOUS_KEYS="base64:old1,base64:old2"',
        ]));

        $this->assertSame('base64:abc123', $this->writer->readAppKey($this->tempPath));
        $this->assertSame(['base64:old1', 'base64:old2'], $this->writer->readPreviousKeys($this->tempPath));
    }

    public function test_it_inserts_anchor_when_missing(): void
    {
        File::put($this->tempPath, "APP_KEY=\"\"\nAPP_DEBUG=true\n");

        $this->writer->ensureAnchorPresent($this->tempPath);

        $contents = File::get($this->tempPath);
        $this->assertStringContainsString("APP_KEY=\"\"\nAPP_PREVIOUS_KEYS=\"\"", $contents);
    }

    public function test_it_writes_new_app_key_without_rotating_when_rotate_from_is_null(): void
    {
        File::put($this->tempPath, implode("\n", [
            'APP_KEY=""',
            'APP_PREVIOUS_KEYS=""',
        ]));

        $newKey = $this->writer->writeAppKey($this->tempPath);

        $contents = File::get($this->tempPath);
        $this->assertMatchesRegularExpression('/^APP_KEY="base64:.+"$/m', $contents);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS=""', $contents);
        $this->assertSame($newKey, $this->writer->readAppKey($this->tempPath));
    }

    public function test_it_appends_rotated_key_to_previous_keys_without_duplicates(): void
    {
        $oldKey = 'base64:'.base64_encode(random_bytes(32));

        File::put($this->tempPath, implode("\n", [
            'APP_KEY="'.$oldKey.'"',
            'APP_PREVIOUS_KEYS=""',
        ]));

        $this->writer->writeAppKey($this->tempPath, $oldKey);
        $this->writer->writeAppKey($this->tempPath, $oldKey);

        $previousKeys = $this->writer->readPreviousKeys($this->tempPath);
        $this->assertCount(1, $previousKeys);
        $this->assertSame($oldKey, $previousKeys[0]);
        $this->assertNotSame($oldKey, $this->writer->readAppKey($this->tempPath));
    }

    public function test_it_writes_blind_index_pepper_when_empty(): void
    {
        File::put($this->tempPath, 'ENCRYPTION_BLIND_INDEX_PEPPER=""');

        $written = $this->writer->writeBlindIndexPepperIfEmpty($this->tempPath);

        $this->assertTrue($written);
        $this->assertMatchesRegularExpression(
            '/^ENCRYPTION_BLIND_INDEX_PEPPER="[a-f0-9]{64}"$/m',
            File::get($this->tempPath),
        );
    }

    public function test_it_skips_blind_index_pepper_when_already_set(): void
    {
        File::put($this->tempPath, 'ENCRYPTION_BLIND_INDEX_PEPPER="existing-pepper"');

        $written = $this->writer->writeBlindIndexPepperIfEmpty($this->tempPath);

        $this->assertFalse($written);
        $this->assertStringContainsString(
            'ENCRYPTION_BLIND_INDEX_PEPPER="existing-pepper"',
            File::get($this->tempPath),
        );
    }
}

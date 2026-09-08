<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Services\FileVariantGenerator;
use Tests\TestCase;

class FileVariantGeneratorTest extends TestCase
{
    public function test_encode_jpeg_scales_down_and_returns_bytes(): void
    {
        $source = $this->sampleJpegBytes(800, 600);
        $generator = new FileVariantGenerator;

        $result = $generator->encodeJpeg($source, 256);

        $this->assertNotSame('', $result['bytes']);
        $this->assertLessThanOrEqual(256, $result['max_px']);
        $this->assertGreaterThanOrEqual(40, $result['quality']);
    }

    private function sampleJpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image, quality: 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}

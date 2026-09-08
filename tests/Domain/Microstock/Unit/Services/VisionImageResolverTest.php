<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Archivd\Enums\FileVariantKind;
use App\Domain\Archivd\Enums\FileVariantStatus;
use App\Domain\Archivd\Models\FileVariant;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\FileVariantGenerator;
use App\Domain\Archivd\Services\FileVariantService;
use App\Domain\Archivd\Services\SignedDownloadService;
use App\Domain\Microstock\Services\AssetResizeService;
use App\Domain\Microstock\Services\VisionImageResolver;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisionImageResolverTest extends TestCase
{
    public function test_resolve_returns_signed_url_when_delivery_is_url_and_variant_ready(): void
    {
        config([
            'microstock.ai.vision_image.delivery' => 'url',
            'microstock.ai.vision_image.variant_kind' => 'ai_512',
        ]);

        $file = new HostedFile([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'name' => 'asset.jpg',
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
        ]);

        $variant = new FileVariant([
            'file_id' => $file->id,
            'kind' => FileVariantKind::Ai512,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'size' => 100,
            'max_px' => 512,
            'mime' => 'image/jpeg',
            'status' => FileVariantStatus::Ready,
        ]);
        $variant->setRelation('file', $file);

        $variants = $this->createMock(FileVariantService::class);
        $variants->method('findReady')->willReturn($variant);

        $downloads = $this->createMock(SignedDownloadService::class);
        $downloads->method('resolveVariant')->willReturn([
            'url' => 'https://cdn.test/variant.jpg',
            'expires_at' => now()->addMinutes(30),
        ]);

        $resolver = new VisionImageResolver(
            $variants,
            new FileVariantGenerator,
            $downloads,
            $this->createMock(AssetResizeService::class),
        );

        $this->assertSame('https://cdn.test/variant.jpg', $resolver->resolve($file));
    }

    public function test_resolve_returns_data_uri_when_delivery_is_base64(): void
    {
        config([
            'microstock.ai.vision_image.delivery' => 'base64',
            'microstock.ai.vision_image.variant_kind' => 'ai_1024',
        ]);

        $file = new HostedFile([
            'id' => (string) Str::uuid(),
            'user_id' => (string) Str::uuid(),
            'name' => 'asset.jpg',
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
        ]);

        $resizer = $this->createMock(AssetResizeService::class);
        $resizer->method('previewDataUri')->willReturn('data:image/jpeg;base64,abc');

        $resolver = new VisionImageResolver(
            $this->createMock(FileVariantService::class),
            new FileVariantGenerator,
            $this->createMock(SignedDownloadService::class),
            $resizer,
        );

        $this->assertSame('data:image/jpeg;base64,abc', $resolver->resolve($file));
    }
}

<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use App\Domain\Vtual\Services\PlatformVideoDtoMapper;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformVideoDtoMapperTest extends TestCase
{
    private PlatformVideoDtoMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = app(PlatformVideoDtoMapper::class);
    }

    #[Test]
    public function maps_uploaded_vod(): void
    {
        $payload = $this->mapper->toUpsertPayload($this->dto('uploaded'));

        $this->assertSame('vid1', $payload['external_id']);
        $this->assertSame(ContentType::Vod->value, $payload['type']);
        $this->assertSame(ContentStatus::Uploaded->value, $payload['status']);
        $this->assertSame('Title', $payload['title']);
    }

    #[Test]
    public function maps_live_and_upcoming_and_ended(): void
    {
        $live = $this->mapper->toUpsertPayload($this->dto('live'));
        $this->assertSame(ContentType::Live->value, $live['type']);
        $this->assertSame(ContentStatus::Live->value, $live['status']);

        $upcoming = $this->mapper->toUpsertPayload($this->dto('upcoming'));
        $this->assertSame(ContentType::Live->value, $upcoming['type']);
        $this->assertSame(ContentStatus::Scheduled->value, $upcoming['status']);
        $this->assertNotNull($upcoming['scheduled_at']);

        $ended = $this->mapper->toUpsertPayload($this->dto('ended'));
        $this->assertSame(ContentType::Archive->value, $ended['type']);
        $this->assertSame(ContentStatus::Ended->value, $ended['status']);
    }

    #[Test]
    public function maps_unknown_status_to_vod_unknown(): void
    {
        $payload = $this->mapper->toUpsertPayload($this->dto('weird'));

        $this->assertSame(ContentType::Vod->value, $payload['type']);
        $this->assertSame(ContentStatus::Unknown->value, $payload['status']);
    }

    private function dto(string $status): PlatformVideoDto
    {
        return new PlatformVideoDto(
            platform: PlatformKey::Youtube,
            externalId: 'vid1',
            channelExternalId: 'UCabc',
            title: 'Title',
            description: 'Desc',
            thumbnailUrl: 'https://img.example/thumb.jpg',
            url: 'https://www.youtube.com/watch?v=vid1',
            status: $status,
            publishedAt: Carbon::parse('2026-01-01T00:00:00Z'),
            startedAt: Carbon::parse('2026-01-01T12:00:00Z'),
            endedAt: null,
            durationSeconds: 120,
            viewCount: 10,
            concurrentViewers: null,
        );
    }
}

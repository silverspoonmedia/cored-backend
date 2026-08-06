<?php

namespace Tests\Domain\Vtual\Unit\Integrations\Support;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Support\ChannelProfileFingerprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChannelProfileFingerprintTest extends TestCase
{
    #[Test]
    public function youtube_url_and_handle_produce_same_fingerprint(): void
    {
        $fromUrl = ChannelProfileFingerprint::from(
            PlatformKey::Youtube,
            'https://www.youtube.com/@AizawaEma',
        );
        $fromHandle = ChannelProfileFingerprint::from(PlatformKey::Youtube, '@AizawaEma');

        $this->assertSame('handle:aizawaema', $fromUrl);
        $this->assertSame($fromUrl, $fromHandle);
    }

    #[Test]
    public function youtube_channel_id_fingerprint_uses_channel_id_kind(): void
    {
        $id = 'UC'.str_pad('TEST', 22, '0', STR_PAD_LEFT);

        $this->assertSame(
            'channel_id:'.$id,
            ChannelProfileFingerprint::from(PlatformKey::Youtube, $id),
        );
    }
}

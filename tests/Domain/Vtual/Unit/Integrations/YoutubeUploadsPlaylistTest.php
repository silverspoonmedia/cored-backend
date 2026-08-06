<?php

namespace Tests\Domain\Vtual\Unit\Integrations;

use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Youtube\YoutubeUploadsPlaylist;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeUploadsPlaylistTest extends TestCase
{
    #[Test]
    public function from_channel_id_converts_uc_to_uu(): void
    {
        $this->assertSame(
            'UUPkKpOHxEDcwmUAnRpIu-Ng',
            YoutubeUploadsPlaylist::fromChannelId('UCPkKpOHxEDcwmUAnRpIu-Ng'),
        );
    }

    #[Test]
    public function from_channel_id_rejects_non_uc_prefix(): void
    {
        $this->expectException(VtualException::class);

        YoutubeUploadsPlaylist::fromChannelId('PLsomething');
    }
}

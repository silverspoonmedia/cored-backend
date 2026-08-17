<?php

namespace Tests\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Support\SpaPublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpaPublicUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.spa.url' => '']);
    }

    #[Test]
    public function origin_is_null_when_spa_url_empty(): void
    {
        $this->assertNull(SpaPublicUrl::origin());
    }

    #[Test]
    public function password_reset_falls_back_to_blade_route(): void
    {
        $url = SpaPublicUrl::passwordReset('reset-token', 'user@example.com');

        $this->assertStringContainsString('/reset-password/reset-token', $url);
        $this->assertStringContainsString('email=user%40example.com', $url);
        $this->assertStringNotContainsString('https://spa.test', $url);
    }

    #[Test]
    public function password_reset_uses_spa_query_shape_when_spa_url_set(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $this->assertSame(
            'https://spa.test/reset-password?token=reset-token&email=user%40example.com',
            SpaPublicUrl::passwordReset('reset-token', 'user@example.com'),
        );
    }

    #[Test]
    public function email_verification_keeps_signed_api_url_without_spa(): void
    {
        $signed = 'https://monolith.test/api/v1/auth/email/verify/abc/def?expires=1&signature=sig';

        $this->assertSame($signed, SpaPublicUrl::emailVerification('abc', 'def', $signed));
    }

    #[Test]
    public function email_verification_rewrites_to_spa_and_forwards_signature(): void
    {
        config(['services.spa.url' => 'https://spa.test']);
        $signed = 'https://monolith.test/api/v1/auth/email/verify/abc/def?expires=99&signature=deadbeef';

        $this->assertSame(
            'https://spa.test/verify-email/abc/def?expires=99&signature=deadbeef',
            SpaPublicUrl::emailVerification('abc', 'def', $signed),
        );
    }

    #[Test]
    public function public_shares_fall_back_to_blade_routes(): void
    {
        $id = '01900000-0000-7000-8000-000000000001';

        $this->assertSame(
            route('file-hosting.public.files.show', ['file' => $id]),
            SpaPublicUrl::publicFileShare($id),
        );
        $this->assertSame(
            route('file-hosting.public.folders.show', ['folder' => $id]),
            SpaPublicUrl::publicFolderShare($id),
        );
    }

    #[Test]
    public function public_shares_use_spa_origin_when_set(): void
    {
        config(['services.spa.url' => 'https://spa.test']);
        $id = '01900000-0000-7000-8000-000000000001';

        $this->assertSame('https://spa.test/s/f/'.$id, SpaPublicUrl::publicFileShare($id));
        $this->assertSame('https://spa.test/s/d/'.$id, SpaPublicUrl::publicFolderShare($id));
    }

    #[Test]
    public function payment_bounce_falls_back_to_blade_ipaymu_routes(): void
    {
        $this->assertSame(route('payment.ipaymu.return', [], true), SpaPublicUrl::paymentReturn());
        $this->assertSame(route('payment.ipaymu.cancel', [], true), SpaPublicUrl::paymentCancel());
    }

    #[Test]
    public function payment_bounce_uses_spa_paths_when_spa_url_set(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $this->assertSame('https://spa.test/payment/return', SpaPublicUrl::paymentReturn());
        $this->assertSame('https://spa.test/payment/cancel', SpaPublicUrl::paymentCancel());
    }

    #[Test]
    public function rejects_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SpaPublicUrl::to('/reset-password/../admin');
    }

    #[Test]
    public function verify_email_notification_mints_spa_link_with_signature_query(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $user = User::factory()->unverified()->create();
        $notification = new class extends VerifyEmailNotification
        {
            public function publicUrl(User $user): string
            {
                return $this->verificationUrl($user);
            }
        };

        $url = $notification->publicUrl($user);
        $parts = parse_url($url);

        $this->assertSame('https', $parts['scheme'] ?? null);
        $this->assertSame('spa.test', $parts['host'] ?? null);
        $this->assertSame('/verify-email/'.$user->id.'/'.sha1($user->getEmailForVerification()), $parts['path'] ?? null);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertArrayHasKey('signature', $query);
        $this->assertNotSame('', $query['signature']);
    }

    #[Test]
    public function spa_verify_query_still_validates_on_the_signed_api_route(): void
    {
        config(['services.spa.url' => 'https://spa.test']);

        $user = User::factory()->unverified()->create();
        $notification = new class extends VerifyEmailNotification
        {
            public function publicUrl(User $user): string
            {
                return $this->verificationUrl($user);
            }
        };

        $spaUrl = $notification->publicUrl($user);
        parse_str((string) parse_url($spaUrl, PHP_URL_QUERY), $query);

        $this->getJson('/api/v1/auth/email/verify/'.$user->id.'/'.sha1($user->getEmailForVerification()).'?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('message', __('api/auth.email_verified'));
    }

    #[Test]
    public function reset_password_notification_action_url_uses_spa_when_configured(): void
    {
        config(['services.spa.url' => 'https://spa.test']);
        $user = User::factory()->create(['email' => 'reset-link@example.com']);
        $mail = (new ResetPasswordNotification('reset-token'))->toMail($user);
        $actionUrl = $mail->actionUrl ?? null;

        $this->assertSame(
            'https://spa.test/reset-password?token=reset-token&email=reset-link%40example.com',
            $actionUrl,
        );
    }
}

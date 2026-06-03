<?php

namespace Tests\Unit\Services\Auth;

use App\Exceptions\Auth\EmailAlreadyVerifiedException;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EmailVerificationService(new UserRepository);
    }

    public function test_verify_marks_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verified = $this->service->verify(
            $user->id,
            sha1($user->getEmailForVerification()),
        );

        $this->assertNotNull($verified->email_verified_at);
    }

    public function test_send_notification_throws_when_already_verified(): void
    {
        $user = User::factory()->create();

        $this->expectException(EmailAlreadyVerifiedException::class);

        $this->service->sendNotification($user);
    }
}

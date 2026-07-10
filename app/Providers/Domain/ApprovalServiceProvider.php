<?php

namespace App\Providers\Domain;

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Approval\Listeners\SendApprovalCompletedNotification;
use App\Domain\Approval\Listeners\UpdateDocumentStatus;
use App\Domain\Approval\Repositories\Contracts\ApprovalRepositoryInterface;
use App\Domain\Approval\Repositories\Eloquent\ApprovalRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ApprovalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApprovalRepositoryInterface::class, ApprovalRepository::class);
    }

    public function boot(): void
    {
        Event::listen(ApprovalCompleted::class, UpdateDocumentStatus::class);
        Event::listen(ApprovalCompleted::class, SendApprovalCompletedNotification::class);
    }
}

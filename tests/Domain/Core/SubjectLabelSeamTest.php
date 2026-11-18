<?php

namespace Tests\Domain\Core;

use App\Domain\ActivityLog\Http\Resources\ActivityLogResource;
use App\Domain\Identity\Models\User;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Service\Models\Service;
use App\Support\ActivityLog\SubjectLabelRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The seam that keeps `silverspoonmedia/audit` from importing a sibling package.
 *
 * `DependencyDirectionTest` proves the import is gone. This proves the behaviour it
 * used to hardcode still happens: a user row reads name + email, and a menu row reads
 * its translated name — but the menu half now arrives through the kernel contract,
 * registered by the domain that owns the model, not by a `instanceof` inside the
 * audit package.
 *
 * It also pins the degrade path. A host that installs the audit package without
 * navigation has no `Menu` class to look for, so the resource must fall back to the
 * subject key rather than reach for a class that does not exist.
 */
class SubjectLabelSeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_package_registers_a_presenter_for_its_own_subject_type(): void
    {
        $user = User::factory()->create();

        $registrar = app(SubjectLabelRegistrar::class);
        $presenter = $registrar->resolve($user);

        $this->assertNotNull($presenter, 'No presenter registered for User — the audit package must seed its own subject type.');
        $this->assertSame($user->name.' ('.$user->email.')', $presenter->label($user));
    }

    public function test_the_owning_domain_registers_the_presenter_for_its_model(): void
    {
        $menu = Menu::factory()->create(['name' => ['en' => 'Main menu', 'id' => 'Menu utama']]);

        app()->setLocale('en');

        $presenter = app(SubjectLabelRegistrar::class)->resolve($menu);

        $this->assertNotNull($presenter, 'No presenter registered for Menu — NavigationServiceProvider must publish it through the kernel seam.');
        $this->assertSame('Main menu', $presenter->label($menu));
    }

    public function test_the_resource_falls_back_to_the_key_when_no_domain_registered_a_presenter(): void
    {
        // A real model with no registered presenter — Service is owned by the billing
        // package, which publishes no activity-log label.
        $subject = Service::factory()->create();

        $activity = $this->makeActivity($subject);

        $payload = (new ActivityLogResource($activity))->toArray(Request::create('/'));

        $this->assertNull(app(SubjectLabelRegistrar::class)->resolve($subject));
        $this->assertSame($subject->getKey(), $payload['subject_label']);
    }

    private function makeActivity(Model $subject): Activity
    {
        return Activity::query()->create([
            'log_name' => 'default',
            'description' => 'updated',
            'event' => 'updated',
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => [],
        ]);
    }
}

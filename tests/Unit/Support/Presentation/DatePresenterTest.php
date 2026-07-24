<?php

namespace Tests\Unit\Support\Presentation;

use App\Support\Presentation\DatePresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatePresenterTest extends TestCase
{
    public function test_null_yields_null_companions(): void
    {
        $this->assertNull(DatePresenter::iso(null));
        $this->assertNull(DatePresenter::formatted(null));
        $this->assertNull(DatePresenter::human(null));
        $this->assertSame([null, null, null], DatePresenter::pack(null));
    }

    public function test_iso_is_machine_readable(): void
    {
        $carbon = Carbon::parse('2026-07-23 12:30:00', 'UTC');

        $iso = DatePresenter::iso($carbon);

        $this->assertNotNull($iso);
        $this->assertStringContainsString('2026-07-23', $iso);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $iso);
    }

    public function test_formatted_uses_absolute_pattern(): void
    {
        app()->setLocale('en');
        $carbon = Carbon::parse('2026-07-23 19:30:00');

        $formatted = DatePresenter::formatted($carbon);

        $this->assertNotNull($formatted);
        $this->assertStringContainsString('2026', $formatted);
        $this->assertStringContainsString('19:30', $formatted);
    }

    public function test_human_is_relative_and_locale_sensitive(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 20:00:00'));

        $carbon = Carbon::parse('2026-07-23 18:00:00');

        app()->setLocale('en');
        $en = DatePresenter::human($carbon);
        $this->assertNotNull($en);
        $this->assertNotSame('', $en);

        app()->setLocale('id');
        $id = DatePresenter::human($carbon);
        $this->assertNotNull($id);

        // Relative phrasing differs by locale (en "ago" vs id "yang lalu" / similar).
        $this->assertNotSame($en, $id);

        Carbon::setTestNow();
    }

    public function test_pack_returns_three_companions(): void
    {
        app()->setLocale('en');
        $carbon = Carbon::parse('2026-01-15 10:00:00');

        [$iso, $formatted, $human] = DatePresenter::pack($carbon);

        $this->assertNotNull($iso);
        $this->assertNotNull($formatted);
        $this->assertNotNull($human);
        $this->assertStringContainsString('2026', $iso);
    }

    public function test_empty_string_is_null(): void
    {
        $this->assertNull(DatePresenter::iso(''));
        $this->assertNull(DatePresenter::formatted(''));
        $this->assertNull(DatePresenter::human(''));
    }
}

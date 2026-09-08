<?php

namespace Tests\Unit\Support\Presentation;

use App\Support\Presentation\MoneyPresenter;
use Tests\TestCase;

class MoneyPresenterTest extends TestCase
{
    public function test_null_amount_is_null(): void
    {
        $this->assertNull(MoneyPresenter::formatted(null, 'IDR'));
    }

    public function test_empty_amount_is_null(): void
    {
        $this->assertNull(MoneyPresenter::formatted('', 'IDR'));
    }

    public function test_formats_with_currency_code(): void
    {
        app()->setLocale('en');

        $this->assertSame('IDR 10,000.00', MoneyPresenter::formatted(10000, 'IDR'));
        $this->assertSame('USD 12.50', MoneyPresenter::formatted(12.5, 'USD'));
    }

    public function test_formats_without_currency(): void
    {
        app()->setLocale('en');

        $this->assertSame('1,234.56', MoneyPresenter::formatted(1234.56, null));
        $this->assertSame('1,234.56', MoneyPresenter::formatted(1234.56, ''));
    }

    public function test_locale_id_uses_local_separators(): void
    {
        app()->setLocale('id');

        $formatted = MoneyPresenter::formatted(10000.5, 'IDR');

        $this->assertNotNull($formatted);
        $this->assertStringStartsWith('IDR ', $formatted);
        // id: thousand '.' decimal ',' (NumberFormatter or fallback)
        $this->assertStringContainsString('10', $formatted);
        $this->assertStringContainsString('000', $formatted);
        $this->assertStringContainsString('50', $formatted);
        $this->assertNotSame('IDR 10,000.50', $formatted);
    }

    public function test_each_supported_locale_returns_a_formatted_amount(): void
    {
        foreach (config('localization.supported_locales') as $locale) {
            app()->setLocale($locale);

            $formatted = MoneyPresenter::formatted(1234.56, 'USD');

            $this->assertNotNull($formatted);
            $this->assertStringStartsWith('USD ', $formatted);
            $this->assertStringContainsString('1', $formatted);
            $this->assertStringContainsString('234', $formatted);
            $this->assertStringContainsString('56', $formatted);
        }
    }

    public function test_non_numeric_is_null(): void
    {
        $this->assertNull(MoneyPresenter::formatted('not-a-number', 'IDR'));
    }

    public function test_zero_is_formatted(): void
    {
        app()->setLocale('en');

        $this->assertSame('IDR 0.00', MoneyPresenter::formatted(0, 'IDR'));
    }
}

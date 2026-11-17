<?php

namespace App\Domain\Currency\Tests\Unit\Services;

use App\Domain\Currency\Exceptions\ExchangeRateException;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Services\CurrencyAdminService;
use App\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsCurrencies;
use Tests\TestCase;

/**
 * The catalog half of the currency domain: exactly one settlement row, admin
 * toggles persist, and the FX failure is a translated business rule (502), not
 * an unhandled 500.
 */
class CurrencyAdminServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCurrencies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCurrencies();
    }

    public function test_update_keeps_a_single_settlement_currency(): void
    {
        $service = app(CurrencyAdminService::class);

        $service->update('USD', ['is_settlement' => true]);

        $this->assertSame(['USD'], Currency::query()->where('is_settlement', true)->pluck('code')->all());
        $this->assertFalse((bool) Currency::query()->find('IDR')?->is_settlement);
    }

    public function test_update_toggles_activation(): void
    {
        $currency = app(CurrencyAdminService::class)->update('EUR', ['is_active' => false]);

        $this->assertFalse($currency->isActive());
        $this->assertFalse((bool) Currency::query()->find('EUR')?->is_active);
    }

    public function test_unknown_code_is_a_validation_error_not_a_crash(): void
    {
        $this->expectException(ValidationException::class);

        app(CurrencyAdminService::class)->update('XXX', ['is_active' => false]);
    }

    public function test_fx_failure_is_a_business_rule_with_its_own_status(): void
    {
        $exception = new ExchangeRateException(__('api/currencies.fx_unavailable'));

        $this->assertInstanceOf(BusinessRuleException::class, $exception);
        $this->assertSame(502, $exception->statusCode());
        $this->assertNotSame('api/currencies.fx_unavailable', $exception->getMessage());
    }
}

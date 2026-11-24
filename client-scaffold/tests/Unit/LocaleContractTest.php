<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The two locales a consumer install ships (`en`, `id`) must answer from the
 * host tree and from every core package's `resources/lang`.
 *
 * A copy that boots but renders a raw dotted key is the failure this catches: a
 * package's translations load through `addPath()`, so a namespace typo, a missing
 * locale directory or a package that stopped shipping `id` all look green to
 * `route:list` and to `migrate`. Asserting a real sentence per source — host,
 * notification, billing, audit — is the only check that notices.
 */
class LocaleContractTest extends TestCase
{
    /**
     * One key per translation source, with the locale it is asserted in.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const PROBES = [
        // Host tree: `lang/{locale}/validation.php`.
        ['validation.required', 'en'],
        ['validation.required', 'id'],
        // Package tree: `vendor/silverspoonmedia/notification/resources/lang`.
        ['api/notifications.types.user_created', 'en'],
        ['api/notifications.types.user_created', 'id'],
        // Package tree: `vendor/silverspoonmedia/billing/resources/lang`.
        ['api/payments.midtrans.approve_ok', 'en'],
        ['api/payments.midtrans.approve_ok', 'id'],
        // Package tree: `vendor/silverspoonmedia/audit/resources/lang`.
        ['api/error-log.list_success', 'en'],
        ['api/error-log.list_success', 'id'],
    ];

    public function test_the_supported_locales_are_english_and_indonesian(): void
    {
        $this->assertSame(
            ['en', 'id'],
            config('localization.supported_locales'),
            'A consumer install ships two locales; a third here means a pack nobody translated.',
        );
    }

    public function test_every_probe_key_resolves_to_a_sentence(): void
    {
        foreach (self::PROBES as [$key, $locale]) {
            App::setLocale($locale);

            $value = Lang::get($key);

            $this->assertNotSame($key, $value, "[{$key}] renders as the raw key in [{$locale}] — the pack did not load.");
            $this->assertNotSame('', trim((string) $value), "[{$key}] is empty in [{$locale}].");
        }

        App::setLocale('en');
    }
}

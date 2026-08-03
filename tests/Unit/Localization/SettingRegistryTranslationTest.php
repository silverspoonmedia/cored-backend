<?php

namespace Tests\Unit\Localization;

use App\Support\Settings\SettingRegistry;
use Tests\TestCase;

/**
 * Ensures every SettingRegistry key has a translated field label in all locales.
 *
 * Complements LocaleParityTest: registry keys are dynamic and not discovered by
 * file parity alone when new settings are added to SettingRegistry.
 */
class SettingRegistryTranslationTest extends TestCase
{
    public function test_every_registry_key_has_field_translation_in_all_locales(): void
    {
        $locales = array_values((array) config('localization.supported_locales', ['en', 'id', 'ja']));

        foreach (array_keys(SettingRegistry::definitions()) as $key) {
            foreach ($locales as $locale) {
                $fields = trans('web/settings.fields', [], $locale);

                $this->assertIsArray($fields, "Locale [{$locale}] web/settings.fields must be an array.");

                $this->assertArrayHasKey(
                    $key,
                    $fields,
                    "Missing [{$locale}] translation for SettingRegistry key [{$key}]."
                );

                $this->assertNotSame(
                    '',
                    trim((string) $fields[$key]),
                    "Empty [{$locale}] translation for SettingRegistry key [{$key}]."
                );
            }
        }
    }
}

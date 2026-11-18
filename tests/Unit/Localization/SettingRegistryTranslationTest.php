<?php

namespace Tests\Unit\Localization;

use App\Support\Localization\LocalePreference;
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
        $locales = LocalePreference::supported();

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

    public function test_every_registry_option_value_has_translation_in_all_locales(): void
    {
        $locales = LocalePreference::supported();

        foreach (SettingRegistry::definitions() as $key => $definition) {
            $options = $definition['options'] ?? [];

            if ($options === []) {
                continue;
            }

            foreach ($options as $value) {
                $transKey = 'web/settings.options.'.$key.'.'.$value;

                foreach ($locales as $locale) {
                    $label = trans($transKey, [], $locale);

                    $this->assertNotSame(
                        $transKey,
                        $label,
                        "Missing [{$locale}] option label for [{$key}] value [{$value}].",
                    );
                    $this->assertNotSame(
                        '',
                        trim((string) $label),
                        "Empty [{$locale}] option label for [{$key}] value [{$value}].",
                    );
                }
            }
        }
    }
}

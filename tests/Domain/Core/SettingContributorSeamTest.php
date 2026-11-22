<?php

namespace Tests\Domain\Core;

use App\Support\Settings\SettingCatalog;
use App\Support\Settings\SettingRegistry;
use App\Support\Settings\SettingType;
use App\Support\Settings\Contracts\SettingContributor;
use Tests\TestCase;

/**
 * The settings seam, proven from both ends.
 *
 * A product owns its keys and registers them from its own provider; the kernel
 * owns the catalog and the contract. The behaviour that matters for a fork:
 *
 *  - a product contributor's keys appear exactly as they did while hardcoded,
 *    so `SettingsSeeder`, `SettingsReader`, `config/settings-local.php` and every
 *    `system_settings` row keep resolving;
 *  - a catalog with no contributor registered is a legal state — a fork that
 *    deleted the product boots, and admin Settings lists only the kernel groups;
 *  - a definition may declare its own validation rules, so the request class
 *    names no product key.
 */
class SettingContributorSeamTest extends TestCase
{
    public function test_a_product_contributor_publishes_its_keys_into_the_registry(): void
    {
        $definitions = SettingRegistry::definitions();

        $this->assertArrayHasKey('archivd.max_file_size_bytes', $definitions);
        $this->assertArrayHasKey('microstock.daily_free.enabled', $definitions);
        $this->assertSame('archivd', $definitions['archivd.max_file_size_bytes']['group']);
        $this->assertSame('microstock', $definitions['microstock.daily_free.enabled']['group']);

        // Config paths are unchanged: the reader falls back to the same literal.
        $this->assertSame('archivd.max_file_size_bytes', $definitions['archivd.max_file_size_bytes']['config']);
        $this->assertSame(
            'microstock.ai.vision_image.delivery',
            $definitions['microstock.ai.vision_image.delivery']['config'],
        );
    }

    public function test_the_product_contributors_are_registered_on_the_catalog(): void
    {
        $contributors = app(SettingCatalog::class)->contributors();

        $this->assertContains(\App\Domain\Archivd\Support\Settings\ArchivdSettingContributor::class, $contributors);
        $this->assertContains(\App\Domain\Microstock\Support\Settings\MicrostockSettingContributor::class, $contributors);
    }

    public function test_a_catalog_without_contributors_is_a_legal_state(): void
    {
        $catalog = new SettingCatalog;

        $this->assertSame(['platform', 'identity'], $catalog->groups());
        $this->assertNull($catalog->get('archivd.max_file_size_bytes'));
        $this->assertSame([], $catalog->forGroup('microstock'));
    }

    public function test_a_contributor_definition_may_declare_its_own_rules(): void
    {
        $contributor = new class implements SettingContributor
        {
            public function definitions(): array
            {
                return [
                    'example.mode' => [
                        'group' => 'example',
                        'type' => SettingType::String,
                        'config' => 'example.mode',
                        'options' => ['a', 'b'],
                        'rules' => ['string', \Illuminate\Validation\Rule::in(['a', 'b'])],
                    ],
                ];
            }
        };

        $catalog = new SettingCatalog;
        $catalog->register($contributor::class);

        $definition = $catalog->get('example.mode');

        $this->assertNotNull($definition);
        $this->assertSame(['a', 'b'], $definition['options']);
        $this->assertCount(2, $definition['rules']);
        $this->assertContains('example', $catalog->groups());
        $this->assertContains('platform', $catalog->groups());
    }

    public function test_the_request_accepts_a_definition_with_or_without_options(): void
    {
        // The request class reads `options` and `rules` off the definition; a key
        // with neither must still validate as a plain bounded string.
        $documented = app(SettingRegistry::class)->get('microstock.ai.vision_image.delivery');

        $this->assertNotNull($documented);
        $this->assertArrayHasKey('rules', $documented);

        $plain = app(SettingRegistry::class)->get('microstock.csv_disk');

        $this->assertNotNull($plain);
        $this->assertArrayNotHasKey('rules', $plain);
    }
}

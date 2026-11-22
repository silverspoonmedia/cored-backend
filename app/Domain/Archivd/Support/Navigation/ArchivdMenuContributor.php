<?php

namespace App\Domain\Archivd\Support\Navigation;

use App\Support\Navigation\Contracts\MenuContributor;

/**
 * Archivd's navigation nodes.
 *
 * The tree's shape is the navigation package's; the product files and settings
 * pages it owns are registered here from
 * {@see \App\Providers\Domain\ArchivdServiceProvider::boot()}. Removing the
 * product removes this class and its registration, never a package edit.
 *
 * Labels come from `web/` `archivd.menu.*`, one entry per locale; the brand name
 * stays literal, which is a brand and not a translation.
 */
final class ArchivdMenuContributor implements MenuContributor
{
    /**
     * @return array<string, array{nodes: list<array<string, mixed>>, after?: string|null}>
     */
    public function contributions(): array
    {
        $managementRoles = ['Root', 'Admin', 'Moderator', 'Management'];
        $adminRoles = ['Root', 'Admin'];

        return [
            'products' => [
                'nodes' => [
                    [
                        'key' => 'archivd',
                        'name' => $this->brand(),
                        'icon' => 'bi bi-cloud',
                        'route' => 'archivd.index',
                        'type' => 'p',
                        'children' => [
                            [
                                'name' => $this->label('my_files'),
                                'icon' => 'bi bi-folder2-open',
                                'route' => 'archivd.index',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->label('trash'),
                                'icon' => 'bi bi-trash3',
                                'route' => 'archivd.trash',
                                'type' => 'c',
                            ],
                            [
                                'name' => $this->label('blocklist'),
                                'icon' => 'bi bi-shield-x',
                                'route' => 'archivd.blocklist.index',
                                'type' => 'c',
                                'roles' => [...$managementRoles, 'Archivd Admin'],
                            ],
                        ],
                    ],
                ],
            ],
            'administration.settings' => [
                'after' => 'settings.platform',
                'nodes' => [
                    [
                        'key' => 'settings.archivd',
                        'name' => $this->brand(),
                        'icon' => 'bi bi-cloud',
                        'route' => 'settings.archivd',
                        'type' => 'c',
                        'roles' => $adminRoles,
                    ],
                ],
            ],
        ];
    }

    /**
     * Locale map for a menu label, read from `web/archivd.menu.*`.
     *
     * @return array<string, string>
     */
    private function label(string $key): array
    {
        $labels = [];

        foreach ($this->locales() as $locale) {
            $labels[$locale] = (string) trans('web/archivd.menu.'.$key, [], $locale);
        }

        return $labels;
    }

    /**
     * The product name is a brand: one spelling in every locale.
     *
     * @return array<string, string>
     */
    private function brand(): array
    {
        return array_fill_keys($this->locales(), 'Archivd');
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return array_values((array) config('localization.supported_locales', ['en']));
    }
}

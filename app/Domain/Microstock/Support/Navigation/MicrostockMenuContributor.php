<?php

namespace App\Domain\Microstock\Support\Navigation;

use App\Support\Navigation\Contracts\MenuContributor;

/**
 * Microstock's navigation node.
 *
 * Microstock has no sidebar entry of its own — it is reached through the Archivd
 * workspace switcher — so the only node it owns is its settings page. Registered
 * from {@see \App\Providers\Domain\MicrostockServiceProvider::boot()}; removing
 * the product removes this class and its registration.
 */
final class MicrostockMenuContributor implements MenuContributor
{
    /**
     * @return array<string, array{nodes: list<array<string, mixed>>, after?: string|null}>
     */
    public function contributions(): array
    {
        $adminRoles = ['Root', 'Admin'];

        return [
            'administration.settings' => [
                'after' => 'settings.archivd',
                'nodes' => [
                    [
                        'key' => 'settings.microstock',
                        'name' => $this->brand(),
                        'icon' => 'bi bi-images',
                        'route' => 'settings.microstock',
                        'type' => 'c',
                        'roles' => $adminRoles,
                    ],
                ],
            ],
        ];
    }

    /**
     * The product name is a brand: one spelling in every locale.
     *
     * @return array<string, string>
     */
    private function brand(): array
    {
        $locales = (array) config('localization.supported_locales', ['en']);

        return array_fill_keys($locales, 'Microstock');
    }
}

<?php

namespace App\Domain\Service\Database\Seeders;

use App\Domain\Service\Support\ServiceCatalogWriter;
use Illuminate\Database\Seeder;

/**
 * Package-owned catalog maintenance.
 *
 * Product SKUs (a product's storage plans and workspace sizes) are product
 * knowledge and are seeded by the owning product domain, which writes through
 * {@see ServiceCatalogWriter}. This seeder stays code-agnostic so the package
 * never imports a product catalog.
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $writer = app(ServiceCatalogWriter::class);

        // Any legacy `*.subscription.monthly` placeholder plan is retired; the
        // current catalog is main/addon rows seeded by the product domains.
        $writer->retirePattern(
            '%.subscription.monthly',
            'Legacy placeholder plan — replaced by the current catalog.',
        );
    }
}

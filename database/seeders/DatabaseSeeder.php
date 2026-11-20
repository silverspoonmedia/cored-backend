<?php

namespace Database\Seeders;

use App\Domain\Ai\Database\Seeders\AiConnectionSeeder;
use App\Domain\Archivd\Database\Seeders\ArchivdCatalogSeeder;
use App\Domain\Currency\Database\Seeders\CurrencySeeder;
use App\Domain\Identity\Database\Seeders\RoleSeeder;
use App\Domain\Identity\Models\User;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use App\Domain\Microstock\Database\Seeders\MicrostockCatalogSeeder;
use App\Domain\Navigation\Database\Seeders\MenuSeeder;
use App\Domain\Notification\Database\Seeders\NotificationTypeSettingSeeder;
use App\Domain\PaymentGateway\Database\Seeders\PaymentChannelSeeder;
use App\Domain\PaymentGateway\Database\Seeders\PaymentGatewaySeeder;
use App\Domain\Service\Database\Seeders\ServiceCatalogSeeder;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * The monolith's full seed.
 *
 * Product catalogs, product role ladders and the demo users live here, not in a
 * package: this seeder runs against the host database the products extend. The
 * core role ladder moved into the kernel
 * (`App\Domain\Identity\Database\Seeders\RoleSeeder`) so a package test can seed
 * it without this tree.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ArchivdRoleSeeder::class);
        $this->call(MicrostockRoleSeeder::class);
        $this->call(DomainAccessSeeder::class);
        $this->call(NotificationTypeSettingSeeder::class);
        $this->call(SettingsSeeder::class);

        if (app()->environment('local')) {
            $this->call(LocalSettingsSeeder::class);
        }

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $admin->assignRole('Admin');

        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $manager->assignRole('Management');

        $moderator = User::factory()->create([
            'name' => 'Finance Controller',
            'email' => 'finance@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $moderator->assignRole('Moderator');

        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $user->assignRole('User');

        $this->seedProductActivations($admin, $manager, $moderator, $user);

        $this->call(MenuSeeder::class);
        $this->call(PaymentGatewaySeeder::class);
        $this->call(PaymentChannelSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(InferenceCatalogSeeder::class);
        $this->call(ServiceCatalogSeeder::class);
        $this->call(ArchivdCatalogSeeder::class);
        $this->call(MicrostockCatalogSeeder::class);

        if (app()->environment('local')) {
            // Tier C wiring: `ai_connections` rows + consumer slug bindings from the
            // gitignored data file. Last on purpose — it needs the seeded users for
            // `created_by`, and LocalSettingsSeeder's local model aliases above.
            $this->call(AiConnectionSeeder::class);
        }
    }

    /**
     * Activate implicit products for seeded users so the registry has rows
     * out of the box (mirrors what happens on register for real users).
     */
    private function seedProductActivations(User ...$users): void
    {
        $service = App::make(ProductActivationService::class);

        foreach ($users as $user) {
            foreach (ProductRegistry::implicitProductKeys() as $productKey) {
                $service->activateImplicit($user, $productKey, 'seeder');
            }
        }
    }
}

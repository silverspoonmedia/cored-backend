<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(FileHostingRoleSeeder::class);
        $this->call(VtualRoleSeeder::class);
        $this->call(MicrostockRoleSeeder::class);
        $this->call(ZzDemoRoleSeeder::class);
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















<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ProductRoleSeeder::class);
        $this->call(FileHostingRoleSeeder::class);
        $this->call(NotificationTypeSettingSeeder::class);

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

        $this->call(MenuSeeder::class);
        $this->call(MenuRbacSeeder::class);
        $this->call(PaymentGatewaySeeder::class);
        $this->call(PaymentChannelSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(ServiceCatalogSeeder::class);
    }
}

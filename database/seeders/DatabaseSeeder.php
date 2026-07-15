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
        $this->call(NotificationTypeSettingSeeder::class);

        // Admin user (for Executive & Final Approval steps)
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $admin->assignRole('Admin');

        // Management user (for Manager Review step)
        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $manager->assignRole('Management');

        // Moderator user (for Finance Approval step)
        $moderator = User::factory()->create([
            'name' => 'Finance Controller',
            'email' => 'finance@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $moderator->assignRole('Moderator');

        // Regular user (for submitting orders)
        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('123456789'),
        ]);
        $user->assignRole('User');

        $this->call(MenuSeeder::class);
        $this->call(MenuRbacSeeder::class);
        $this->call(ApprovalWorkflowSeeder::class);
        $this->call(PaymentGatewaySeeder::class);
        $this->call(PaymentChannelSeeder::class);
    }
}

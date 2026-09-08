<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Identity\Models\User;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.store' => 'array']);
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => ArchivdRoleSeeder::class, '--force' => true]);

$user = User::factory()->create(['password' => bcrypt('password')]);
$user->assignRole('User');
echo 'can view archivd folder: '.var_export($user->can('view archivd folder'), true).PHP_EOL;

$token = auth('api')->login($user);
$res = app()->handle(Illuminate\Http\Request::create('/api/v1/archivd/quota', 'GET', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_X_SPA_ORIGIN' => '1',
]));
echo 'status: '.$res->getStatusCode().PHP_EOL;
echo 'body: '.substr($res->getContent(), 0, 500).PHP_EOL;

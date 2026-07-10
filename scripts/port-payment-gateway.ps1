# One-shot port script: boilerplate payment domain -> monolith PaymentGateway domain
$ErrorActionPreference = 'Stop'
$bp = 'c:\laragon\www\boilerplate'
$mo = 'c:\laragon\www\monolith'

function Ensure-Dir($path) {
    $dir = Split-Path $path -Parent
    if ($dir -and -not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
}

function Port-File($src, $dest, [scriptblock]$extra = $null) {
    if (-not (Test-Path $src)) { Write-Warning "Missing: $src"; return }
    $c = Get-Content $src -Raw -Encoding UTF8
    $c = $c -replace 'App\\Modules\\Core\\', 'App\Domain\PaymentGateway\'
    $c = $c -replace 'App\\Modules\\Core\\Models\\OrderMaster', 'App\Domain\Playground\Models\Order'
    $c = $c -replace 'OrderMaster', 'Order'
    $c = $c -replace 'orderDetails', 'details'
    $c = $c -replace 'App\\Modules\\Core\\Enums\\OrderMasterStatus', 'App\Domain\Playground\Enums\OrderStatus'
    $c = $c -replace 'OrderMasterStatus', 'OrderStatus'
    $c = $c -replace 'HasUlids', 'HasUuids'
    $c = $c -replace 'use HasFactory, HasUuids;', 'use HasFactory, HasUuids;'
    $c = $c -replace "Repositories\\Billing\\", 'Repositories\'
    $c = $c -replace 'api\.payment\.', 'api/payments.'
    $c = $c -replace "'ulid'", "'uuid'"
    $c = $c -replace 'Invoice ULID', 'Invoice UUID'
    $c = $c -replace 'App\\Http\\Controllers\\Controller', 'App\Http\Controllers\Controller'
    $c = $c -replace 'App\\Modules\\Core\\Http\\Controllers\\Controller', 'App\Http\Controllers\Controller'
    $c = $c -replace 'App\\Modules\\Core\\Traits\\ApiResponse', 'DELETED_API_RESPONSE'
    $c = $c -replace 'use ApiResponse;', ''
    $c = $c -replace 'App\\Support\\Modules\\ModuleApiPrefix', 'DELETED_MODULE_PREFIX'
    if ($extra) { $c = & $extra $c }
    Ensure-Dir $dest
    Set-Content -Path $dest -Value $c -Encoding UTF8 -NoNewline
    Write-Host "Wrote $dest"
}

# Models
Port-File "$bp\app\Modules\Core\Models\PaymentGateway.php" "$mo\app\Domain\PaymentGateway\Models\PaymentGateway.php"
Port-File "$bp\app\Modules\Core\Models\Invoice.php" "$mo\app\Domain\PaymentGateway\Models\Invoice.php"
Port-File "$bp\app\Modules\Core\Models\Subscription.php" "$mo\app\Domain\PaymentGateway\Models\Subscription.php"

# Enums
Port-File "$bp\app\Modules\Core\Enums\DisbursementStatus.php" "$mo\app\Domain\PaymentGateway\Enums\DisbursementStatus.php"
Port-File "$bp\app\Modules\Core\Enums\SubscriptionStatus.php" "$mo\app\Domain\PaymentGateway\Enums\SubscriptionStatus.php"
Port-File "$bp\app\Modules\Core\Enums\BillingCycle.php" "$mo\app\Domain\PaymentGateway\Enums\BillingCycle.php"

# Repositories
Port-File "$bp\app\Modules\Core\Repositories\Billing\Contracts\InvoiceRepositoryInterface.php" "$mo\app\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface.php"
Port-File "$bp\app\Modules\Core\Repositories\Billing\Eloquent\InvoiceRepository.php" "$mo\app\Domain\PaymentGateway\Repositories\Eloquent\InvoiceRepository.php"

# Billing services
Port-File "$bp\app\Modules\Core\Services\Billing\InvoiceFundsLedgerService.php" "$mo\app\Domain\PaymentGateway\Services\Billing\InvoiceFundsLedgerService.php"
Port-File "$bp\app\Modules\Core\Services\Billing\InvoiceDisbursementService.php" "$mo\app\Domain\PaymentGateway\Services\Billing\InvoiceDisbursementService.php"

# Payment services (all 18)
$payFiles = Get-ChildItem "$bp\app\Modules\Core\Services\Payment" -Recurse -Filter '*.php'
foreach ($f in $payFiles) {
    $rel = $f.FullName.Substring("$bp\app\Modules\Core\Services\Payment\".Length)
    Port-File $f.FullName "$mo\app\Domain\PaymentGateway\Services\Payment\$rel"
}

# Requests
$reqFiles = Get-ChildItem "$bp\app\Modules\Core\Http\Requests\V1\Payments" -Recurse -Filter '*.php'
foreach ($f in $reqFiles) {
    $rel = $f.FullName.Substring("$bp\app\Modules\Core\Http\Requests\V1\Payments\".Length)
    $dest = "$mo\app\Domain\PaymentGateway\Http\Requests\Payments\$rel"
    Port-File $f.FullName $dest
}

# Commands
$cmdFiles = Get-ChildItem "$bp\app\Modules\Core\Console\Commands\Payments*.php"
foreach ($f in $cmdFiles) {
    $name = $f.Name
    Port-File $f.FullName "$mo\app\Domain\PaymentGateway\Console\Commands\$name" {
        param($c)
        $c -replace 'namespace App\\Domain\\PaymentGateway\\Console\\Commands;', 'namespace App\Domain\PaymentGateway\Console\Commands;'
    }
}

# Seeder
Port-File "$bp\database\seeders\Core\PaymentGatewaySeeder.php" "$mo\database\seeders\PaymentGatewaySeeder.php" {
    param($c)
    $c -replace 'namespace Database\\Seeders\\Core;', 'namespace Database\Seeders;'
    $c -replace 'App\\Domain\\PaymentGateway\\Models\\PaymentGateway', 'App\Domain\PaymentGateway\Models\PaymentGateway'
}

Write-Host 'Port script complete.'

# Run Monolith, Gateway, and Frontend test suites from the Monolith repo root.
# Usage: .\scripts\test-workspace.ps1

$ErrorActionPreference = 'Stop'
$monolithRoot = Split-Path $PSScriptRoot -Parent
$gatewayRoot = Join-Path (Split-Path $monolithRoot -Parent) 'monolith-gateway'
$frontendRoot = 'C:\Users\Administrator\Documents\npm\monolith-frontend'

$results = @()

function Invoke-WorkspaceTest {
    param(
        [string]$Name,
        [string]$WorkingDirectory,
        [string]$Command
    )

    Write-Host "`n=== $Name ===" -ForegroundColor Cyan
    Push-Location $WorkingDirectory
    try {
        Invoke-Expression $Command
        if ($LASTEXITCODE -ne 0) {
            throw "Exit code $LASTEXITCODE"
        }
        $script:results += [pscustomobject]@{ Suite = $Name; Status = 'passed' }
    } catch {
        $script:results += [pscustomobject]@{ Suite = $Name; Status = 'failed'; Detail = $_.Exception.Message }
        throw
    } finally {
        Pop-Location
    }
}

try {
    Invoke-WorkspaceTest -Name 'Monolith (PHPUnit)' -WorkingDirectory $monolithRoot -Command 'composer test'
    Invoke-WorkspaceTest -Name 'Gateway (PHPUnit)' -WorkingDirectory $gatewayRoot -Command 'php artisan test'
    Invoke-WorkspaceTest -Name 'Frontend (Vitest)' -WorkingDirectory $frontendRoot -Command 'npm run test:unit -- --run'
} catch {
    Write-Host "`nWorkspace test run failed." -ForegroundColor Red
    $results | Format-Table -AutoSize
    exit 1
}

Write-Host "`nAll workspace test suites passed." -ForegroundColor Green
$results | Format-Table -AutoSize

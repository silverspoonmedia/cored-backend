# Remove empty directories left after domain migration (porting debris only).
# Usage:
#   .\scripts\remove-empty-dirs.ps1 -WhatIf   # dry-run
#   .\scripts\remove-empty-dirs.ps1             # delete

param(
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'
$monolithRoot = Split-Path $PSScriptRoot -Parent

$rootStrayDirs = @(
    'Console',
    'Http',
    'Models',
    'Repositories',
    'Services',
    'Support',
    'View',
    'Exceptions'
)

$scopeRoots = @(
    (Join-Path $monolithRoot 'app'),
    (Join-Path $monolithRoot 'tests')
) + ($rootStrayDirs | ForEach-Object { Join-Path $monolithRoot $_ })

function Test-DirTreeHasFiles {
    param([string]$Path)

    return (Get-ChildItem -LiteralPath $Path -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count -gt 0
}

function Get-ScopedEmptyDirectories {
    param([string[]]$Roots)

    $empty = @()

    foreach ($root in $Roots) {
        if (-not (Test-Path -LiteralPath $root)) {
            continue
        }

        Get-ChildItem -LiteralPath $root -Recurse -Directory -ErrorAction SilentlyContinue |
            Sort-Object { $_.FullName.Length } -Descending |
            ForEach-Object {
                if (-not (Test-DirTreeHasFiles -Path $_.FullName)) {
                    $empty += $_.FullName
                }
            }

        if (-not (Test-DirTreeHasFiles -Path $root)) {
            $empty += $root
        }
    }

    return $empty | Select-Object -Unique
}

$toRemove = Get-ScopedEmptyDirectories -Roots $scopeRoots

if ($toRemove.Count -eq 0) {
    Write-Host 'No empty directories found in scope.' -ForegroundColor Green
    exit 0
}

Write-Host "Found $($toRemove.Count) empty director$(if ($toRemove.Count -eq 1) { 'y' } else { 'ies' }) in scope." -ForegroundColor Cyan

foreach ($dir in ($toRemove | Sort-Object { $_.Length } -Descending)) {
    $relative = $dir.Replace($monolithRoot + '\', '')
    if ($WhatIf) {
        Write-Host "[WhatIf] Would remove: $relative"
        continue
    }

    if (Test-Path -LiteralPath $dir) {
        Remove-Item -LiteralPath $dir -Recurse -Force
        Write-Host "Removed: $relative" -ForegroundColor Yellow
    }
}

if ($WhatIf) {
    Write-Host "`nDry-run complete. Re-run without -WhatIf to delete." -ForegroundColor Cyan
} else {
    Write-Host "`nCleanup complete. Removed $($toRemove.Count) directories." -ForegroundColor Green
}

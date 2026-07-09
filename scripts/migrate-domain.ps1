# Bulk namespace replacement across PHP files in the Monolith project.
# Usage: .\scripts\migrate-domain.ps1 -Replacements @{ 'Old\\Ns' = 'New\\Ns' }

param(
    [Parameter(Mandatory = $true)]
    [hashtable] $Replacements,

    [string] $Root = (Split-Path $PSScriptRoot -Parent)
)

$files = Get-ChildItem -Path $Root -Recurse -Include *.php -File |
    Where-Object { $_.FullName -notmatch '\\vendor\\|\\node_modules\\|\\storage\\' }

foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $original = $content

    foreach ($key in $Replacements.Keys) {
        $content = $content.Replace($key, $Replacements[$key])
    }

    if ($content -ne $original) {
        [System.IO.File]::WriteAllText($file.FullName, $content)
        Write-Host "Updated: $($file.FullName.Replace($Root, ''))"
    }
}

Write-Host "Done. $($Replacements.Count) replacement rules applied."

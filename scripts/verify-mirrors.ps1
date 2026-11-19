<#>
.SYNOPSIS
    Verify that every package mirror matches the monolith source.

.DESCRIPTION
    A mirror is a one-way derivative produced by publish-packages.ps1, so drift
    means the published package no longer reflects packages/{slug} in this repo —
    a consumer installs stale code and nothing fails locally.

    For every selected package this compares
      1. the mirror's main with `git subtree split --prefix=packages/{slug}`
         from the local checkout, and
      2. the mirror's newest tag with the `version` declared in
         packages/{slug}/composer.json.

    Exit code 1 when any package drifted; each finding is printed with the two
    SHAs or versions, so the next publish (or the missing -Bump) is obvious.

    Contract: docs/integration/core-packages.md#distribution

.PARAMETER Packages
    Subset of packages to verify. Defaults to all six.

.PARAMETER Tag
    Also require that this tag exists on the mirror, e.g. v1.0.1.

.EXAMPLE
    ./scripts/verify-mirrors.ps1

.EXAMPLE
    ./scripts/verify-mirrors.ps1 -Tag v1.0.1
#>
[CmdletBinding()]
param(
    [string[]] $Packages = @('platform', 'navigation', 'notification', 'audit', 'billing', 'ai'),

    [string] $Tag
)

$ErrorActionPreference = 'Stop'

$Org = 'silverspoonmedia'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

function Invoke-Git {
    param([string[]] $Arguments, [string] $WorkDir = $Root, [switch] $AllowFail)

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    Push-Location $WorkDir
    try {
        $output = & git @Arguments 2>&1
        $code = $LASTEXITCODE
    }
    finally {
        Pop-Location
        $ErrorActionPreference = $previous
    }

    return @{ Code = $code; Output = ($output | Out-String).Trim() }
}

function Get-RemoteHead {
    param([string] $Url)

    $result = Invoke-Git -Arguments @('ls-remote', $Url, 'refs/heads/main')
    if ($result.Code -ne 0 -or $result.Output.Length -eq 0) {
        return $null
    }

    return ($result.Output -split "`n")[0].Split("`t")[0]
}

function Get-RemoteTags {
    param([string] $Url)

    $result = Invoke-Git -Arguments @('ls-remote', '--tags', $Url)
    $tagList = @()
    foreach ($line in ($result.Output -split "`n")) {
        # An annotated tag appears twice: `refs/tags/v1.0.1` and the peeled
        # `refs/tags/v1.0.1^{}` that points at the commit it wraps.
        if ($line -match 'refs/tags/([^\^]+)(\^\{\})?$') {
            $tagList += $Matches[1].Trim()
        }
    }

    return ($tagList | Sort-Object -Unique)
}

if (-not (Test-Path (Join-Path $Root 'composer.json'))) {
    throw "Run this from the monolith checkout: $Root does not look like the project root."
}

$drift = @()

foreach ($slug in $Packages) {
    $prefix = "packages/$slug"
    if (-not (Test-Path (Join-Path $Root $prefix))) {
        $drift += "missing package directory: $prefix"
        continue
    }

    $url = "https://github.com/$Org/$slug.git"

    Write-Host ''
    Write-Host "== $slug ==" -ForegroundColor Cyan

    $remote = Get-RemoteHead -Url $url
    if ($null -eq $remote) {
        $drift += "$slug`: mirror has no readable main ($url)"
        continue
    }

    # `git subtree split` prints progress to stderr and the SHA on stdout, so the
    # merged stream has to be filtered rather than used directly.
    $splitOutput = Invoke-Git -Arguments @('subtree', 'split', "--prefix=$prefix")
    $split = ($splitOutput.Output -split "\r?\n" | Where-Object { $_ -match '^[0-9a-f]{40}$' } | Select-Object -Last 1)

    if (-not $split -or $split.Length -ne 40) {
        $drift += "$slug`: local subtree split produced no SHA"
        continue
    }

    # The mirror's `main` is the split plus at most one commit that strips the
    # `version` field (see publish-packages.ps1), so a plain SHA comparison
    # against `ls-remote` never matches by design. Fetch it into a scratch ref
    # and accept either the split itself or a single child of it.
    $scratch = "refs/mirror-check/$slug"
    $fetched = Invoke-Git -Arguments @('fetch', '--quiet', '--force', $url, "refs/heads/main:$scratch") -AllowFail
    $mainSha = if ($fetched.Code -eq 0) { (Invoke-Git -Arguments @('rev-parse', "$scratch^{commit}")).Output } else { '' }
    $parentSha = if ($mainSha) { (Invoke-Git -Arguments @('rev-parse', "$scratch^") -AllowFail).Output } else { '' }
    Invoke-Git -Arguments @('update-ref', '-d', $scratch) -AllowFail | Out-Null

    if ($mainSha -eq $split) {
        Write-Host "  main in sync ($($split.Substring(0, 8)))"
    }
    elseif ($parentSha -eq $split) {
        Write-Host "  main in sync, release commit on top ($($mainSha.Substring(0, 8)))"
    }
    else {
        Write-Host "  main drifted: mirror $($remote.Substring(0, 8)) vs source $($split.Substring(0, 8))" -ForegroundColor Yellow
        $drift += "$slug`: main is behind the monolith — republish"
    }

    $manifest = Get-Content (Join-Path $Root "$prefix/composer.json") -Raw
    $declared = if ($manifest -match '(?m)^\s*"version"\s*:\s*"([^"]*)"') { $Matches[1] } else { '' }

    if ($declared -eq '') {
        Write-Host '  composer.json declares no version' -ForegroundColor Yellow
        $drift += "$slug`: composer.json declares no version (invariant: the monolith pins one)"
    }

    $tags = Get-RemoteTags -Url $url
    $latest = $tags | Sort-Object { [version]($_ -replace '^v', '') } | Select-Object -Last 1
    if ($latest -eq "v$declared") {
        Write-Host "  newest tag $latest matches the pinned version"
    }
    elseif ($latest) {
        Write-Host "  tag $latest does not match the pinned $declared" -ForegroundColor Yellow
        $drift += "$slug`: newest tag $latest, source pins $declared — publish with -Bump"
    }
    else {
        Write-Host '  mirror carries no tags yet' -ForegroundColor Yellow
        $drift += "$slug`: mirror carries no tags"
    }

    if ($Tag) {
        if ($tags -contains $Tag) {
            Write-Host "  tag $Tag present"
        }
        else {
            Write-Host "  tag $Tag missing" -ForegroundColor Yellow
            $drift += "$slug`: tag $Tag missing"
        }
    }
}

Write-Host ''
if ($drift.Count -gt 0) {
    Write-Host "DRIFT — $($drift.Count) finding(s):" -ForegroundColor Red
    foreach ($finding in $drift) {
        Write-Host "  - $finding"
    }
    exit 1
}

Write-Host "All $($Packages.Count) mirror(s) match the monolith source." -ForegroundColor Green

<#
.SYNOPSIS
    Publish the core domain packages from this monolith to their read-only mirrors.

.DESCRIPTION
    The monolith is the only source. For every package under packages/{slug} this
    script produces a one-way derivative in github.com/silverspoonmedia/{slug}:

      1. create the private mirror repo when it does not exist yet
      2. `git subtree split --prefix=packages/{slug}` so the mirror carries the real
         history of the package, not one squashed import
      3. push the split to the mirror's main
      4. in a throwaway clone: drop the `version` field from composer.json (a path
         repository needs it, a tagged VCS repository must not have it), commit,
         tag, push commit + tag

    A mirror is never edited by hand. Re-running is safe: an existing repo is
    reused, main is force-updated from the split, and an existing tag is skipped.

    Contract: docs/integration/core-packages.md#distribution

.PARAMETER Version
    Release tag, e.g. v1.0.0. Must start with "v".

.PARAMETER Packages
    Subset of packages to publish. Defaults to all five.

.PARAMETER DryRun
    Print the plan and change nothing (no repo creation, no push, no tag).

.EXAMPLE
    ./scripts/publish-packages.ps1 -Version v1.0.0 -DryRun

.EXAMPLE
    ./scripts/publish-packages.ps1 -Version v1.0.0
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^v\d+\.\d+\.\d+')]
    [string] $Version,

    [string[]] $Packages = @('navigation', 'notification', 'audit', 'billing', 'ai'),

    [switch] $DryRun
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

    if ($code -ne 0 -and -not $AllowFail) {
        throw "git $($Arguments -join ' ') failed in $WorkDir`n$output"
    }

    return @{ Code = $code; Output = $output }
}

function Test-MirrorExists {
    param([string] $Slug)

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        & gh repo view "$Org/$Slug" --json name *> $null
        return $LASTEXITCODE -eq 0
    }
    finally {
        $ErrorActionPreference = $previous
    }
}

function Get-RemoteTag {
    param([string] $Slug, [string] $Tag)

    $result = Invoke-Git -Arguments @('ls-remote', '--tags', "https://github.com/$Org/$Slug.git", "refs/tags/$Tag") -AllowFail

    return ($result.Output -join "`n").Trim().Length -gt 0
}

if (-not (Test-Path (Join-Path $Root 'composer.json'))) {
    throw "Run this from the monolith checkout: $Root does not look like the project root."
}

$currentBranch = (Invoke-Git -Arguments @('rev-parse', '--abbrev-ref', 'HEAD')).Output -join ''
if ($currentBranch.Trim() -ne 'main') {
    Write-Warning "Publishing from '$($currentBranch.Trim())' — a release is normally cut from main."
}

Write-Host "Publishing $($Packages -join ', ') as $Version from $currentBranch"
if ($DryRun) {
    Write-Host 'DRY RUN — no repo is created, nothing is pushed, no tag is written.' -ForegroundColor Yellow
}

foreach ($slug in $Packages) {
    $prefix = "packages/$slug"
    if (-not (Test-Path (Join-Path $Root $prefix))) {
        throw "Missing package directory: $prefix"
    }

    $url = "https://github.com/$Org/$slug.git"
    $splitBranch = "publish/$slug"

    Write-Host ''
    Write-Host "== $slug ==" -ForegroundColor Cyan

    $exists = Test-MirrorExists -Slug $slug
    if ($exists) {
        Write-Host "  mirror exists: $url"
    }
    elseif ($DryRun) {
        Write-Host "  would create private mirror: $url"
    }
    else {
        Write-Host "  creating private mirror: $url"
        & gh repo create "$Org/$slug" --private --description "Silverspoon core domain package: $slug (read-only mirror of packages/$slug in monolith)"
        if ($LASTEXITCODE -ne 0) {
            throw "gh repo create failed for $slug"
        }
    }

    if ($exists -and (Get-RemoteTag -Slug $slug -Tag $Version)) {
        Write-Host "  tag $Version already published — skipping" -ForegroundColor Yellow
        continue
    }

    if ($DryRun) {
        Write-Host "  would: git subtree split --prefix=$prefix --branch $splitBranch"
        Write-Host "  would: push $splitBranch to $url main"
        Write-Host "  would: drop 'version' from composer.json, commit, tag $Version, push"
        continue
    }

    Invoke-Git -Arguments @('subtree', 'split', "--prefix=$prefix", '--branch', $splitBranch) | Out-Null
    Write-Host "  split $prefix into $splitBranch"

    # A mirror is a derivative, so main is replaced rather than fast-forwarded.
    Invoke-Git -Arguments @('push', '--force', $url, "refs/heads/${splitBranch}:refs/heads/main") | Out-Null
    Write-Host '  pushed mirror main'

    $temp = Join-Path ([System.IO.Path]::GetTempPath()) "silverspoonmedia-$slug-$([guid]::NewGuid().ToString('N').Substring(0,8))"
    try {
        Invoke-Git -Arguments @('clone', '--quiet', $url, $temp) -WorkDir ([System.IO.Path]::GetTempPath()) | Out-Null

        $manifest = Join-Path $temp 'composer.json'
        $json = Get-Content $manifest -Raw
        $stripped = [regex]::Replace($json, '(?m)^\s*"version"\s*:\s*"[^"]*",\r?\n', '')

        if ($stripped -ne $json) {
            [System.IO.File]::WriteAllText($manifest, $stripped, (New-Object System.Text.UTF8Encoding($false)))
            Invoke-Git -Arguments @('add', 'composer.json') -WorkDir $temp | Out-Null
            Invoke-Git -Arguments @('commit', '-m', "chore(release): drop the path-repository version field for $Version") -WorkDir $temp | Out-Null
            Write-Host "  dropped 'version' from composer.json"
        }
        else {
            Write-Host "  composer.json already carries no 'version'"
        }

        Invoke-Git -Arguments @('tag', '-a', $Version, '-m', "silverspoonmedia/$slug $Version") -WorkDir $temp | Out-Null
        Invoke-Git -Arguments @('push', 'origin', 'HEAD:main', "refs/tags/$Version") -WorkDir $temp | Out-Null
        Write-Host "  tagged $Version and pushed" -ForegroundColor Green
    }
    finally {
        if (Test-Path $temp) {
            Remove-Item -Recurse -Force $temp -ErrorAction SilentlyContinue
        }
        Invoke-Git -Arguments @('branch', '-D', $splitBranch) -AllowFail | Out-Null
    }
}

Write-Host ''
if ($DryRun) {
    Write-Host 'Dry run complete — nothing changed.' -ForegroundColor Yellow
}
else {
    Write-Host "Published $($Packages.Count) mirror(s) at $Version." -ForegroundColor Green
    Write-Host 'Verify: gh repo view silverspoonmedia/<slug>  ·  git ls-remote --tags https://github.com/silverspoonmedia/<slug>.git'
}

<?php

// Install-shape support — where a core package's file lives in *this* checkout.
//
// Two shapes exist for the same six core packages:
//
//   the monolith        `packages/{slug}/…`           (Composer path repositories)
//   a consumer install  `vendor/silverspoonmedia/{slug}/…`  (VCS mirrors, `^1.2`)
//
// Rules and briefs are written once, against `packages/{slug}/…`, and the docs
// guard has to resolve them in whichever shape the repo is in. Before this file
// existed, a boilerplate fork failed the guard with 92 findings, every one of them
// a correct rule pointing at a real file through the other shape.
//
// Mapping never decides existence. It answers "which path would hold this file
// here"; the caller still stats the result, so a typo stays a finding in both
// shapes, and a missing `vendor/` (CI, gitignored) stays unverifiable rather than
// turning into a second, unrelated failure.

/**
 * Rewrite a `packages/{slug}/…` path onto the Composer install.
 *
 * The discriminator is the tree, not the slug: the monolith ships the six core
 * packages under `packages/`, a consumer install has none of them there. Deciding
 * per slug would leave a wildcard (a glob naming no single package) unmapped in
 * exactly the repos that need the mapping most, and an *empty* `packages/`
 * directory (Composer leaves one behind after a failed path install) would read
 * as "this is the monolith" and unmap everything.
 */
function docs_install_map_path(string $root, string $path): string
{
    if (! str_starts_with($path, 'packages/') && $path !== 'packages') {
        return $path;
    }

    if (docs_install_has_package_tree($root)) {
        return $path;
    }

    return $path === 'packages'
        ? 'vendor/silverspoonmedia'
        : 'vendor/silverspoonmedia/'.substr($path, strlen('packages/'));
}

/**
 * Does this checkout really carry the core packages as a `packages/` tree?
 *
 * One directory is not enough evidence — `packages/` can exist empty — so the
 * check is the platform package's own `src`, which every shape ships.
 */
function docs_install_has_package_tree(string $root): bool
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    return $cache[$root] = is_dir($root.'/packages/platform/src');
}

/**
 * Collapse `.` and `..` segments without touching the filesystem.
 *
 * A brief links `../../../packages/billing/routes/service.php`, so the resolved
 * path still carries `..` segments. `file_exists()` follows them; a string prefix
 * comparison does not, which is why the mapping below silently never fired on a
 * consumer checkout.
 */
function docs_install_normalize(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $prefix = str_starts_with($path, '/') ? '/' : '';
    $segments = [];

    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);

            continue;
        }

        $segments[] = $segment;
    }

    return $prefix.implode('/', $segments);
}

/**
 * Map an absolute, already-resolved link target onto the Composer install.
 *
 * Returns null when the target is not a `packages/` path or when this checkout
 * keeps its own tree (the monolith) — in both cases the caller's normal
 * "does not exist" finding is the right answer.
 */
function docs_install_link_target(string $root, string $resolved): ?string
{
    $rootNormalized = docs_install_normalize($root);
    $target = docs_install_normalize($resolved);

    if (! str_starts_with($target, $rootNormalized.'/packages/')) {
        return null;
    }

    $relative = substr($target, strlen($rootNormalized) + 1);
    $mapped = docs_install_map_path($root, $relative);

    if ($mapped === $relative) {
        return null;
    }

    return docs_install_normalize($root).'/'.$mapped;
}

/**
 * The package slug a `vendor/silverspoonmedia/…` path names, or null.
 *
 * A vendor path with no slug (`vendor/silverspoonmedia/**`, a whole-package glob)
 * has no single slug to look up, so it is answered by the install-presence check
 * instead.
 */
function docs_install_vendor_slug(string $path): ?string
{
    return preg_match('~^vendor/silverspoonmedia/([a-z0-9-]+)(/|$)~', $path, $match) === 1
        ? $match[1]
        : null;
}

/**
 * Is `silverspoonmedia/{slug}` a declared dependency of this checkout?
 *
 * Read from the manifest, not from disk: CI has no `vendor/`, and a consumer
 * checkout still has to be judged by the consumer rule there.
 */
function docs_install_requires_package(string $root, string $slug): bool
{
    static $cache = [];

    $key = $root.'|'.$slug;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $manifest = $root.'/composer.json';
    $data = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

    return $cache[$key] = is_array($data)
        && is_array($data['require'] ?? null)
        && array_key_exists('silverspoonmedia/'.$slug, $data['require']);
}

/**
 * Is the Composer install checked out for this repo?
 */
function docs_install_vendor_present(string $root): bool
{
    return is_dir($root.'/vendor/silverspoonmedia');
}

/**
 * Every path under `vendor/silverspoonmedia/`, normalised to forward slashes.
 *
 * Cached per root: a guard run walks this list once per rule glob otherwise, and
 * the tree carries thousands of files.
 *
 * @return list<string>
 */
function docs_install_vendor_files(string $root): array
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    $base = $root.'/vendor/silverspoonmedia';

    if (! is_dir($base)) {
        return $cache[$root] = [];
    }

    $prefix = strlen($root) + 1;
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), $prefix));
    }

    return $cache[$root] = $files;
}

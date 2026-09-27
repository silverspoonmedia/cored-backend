<?php

/**
 * Shared plumbing for the docs guards in `scripts/`.
 *
 * `docs-guard.php` resolves a relative markdown link's *path* and stops there, so a
 * heading that moves or is reworded leaves every `#anchor` pointing at nothing — the
 * silent half of the same failure mode the path check exists to catch. This library
 * answers the other half: what slugs a markdown file actually offers.
 *
 * Two rules are enforced here:
 *
 *   - **One slugger.** The GitHub algorithm is reimplemented once, so the PHP guard
 *     and its test cannot disagree about what a heading's anchor is.
 *   - **Fenced code is not prose.** A shell comment inside a ``` fence (`# Full reset
 *     only when intentional:`) is not a heading, and treating it as one invents an
 *     anchor that no link will ever match.
 *
 * Plain functions, not a class: the guards are standalone scripts run by
 * `php scripts/…` and by `DocsAnchorGuardTest`, and a `require` keeps them
 * dependency-free.
 */

/**
 * Absolute path to the backend repo root (this file lives in `scripts/lib/`).
 */
function docs_anchor_root(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Every heading slug a markdown file offers, in document order.
 *
 * Duplicate headings get GitHub's `-1`, `-2`, … suffix (the first occurrence is bare).
 *
 * @return list<string>
 */
function docs_anchor_slugs(string $path): array
{
    return docs_anchor_slugs_in((string) file_get_contents($path));
}

/**
 * Slug list for markdown source held in memory.
 *
 * @return list<string>
 */
function docs_anchor_slugs_in(string $markdown): array
{
    $slugs = [];
    $seen = [];

    foreach (docs_anchor_headings($markdown) as $heading) {
        $slug = docs_anchor_slug($heading);

        if ($slug === '') {
            continue;
        }

        $count = $seen[$slug] ?? 0;
        $seen[$slug] = $count + 1;

        $slugs[] = $count === 0 ? $slug : $slug.'-'.$count;
    }

    return $slugs;
}

/**
 * Heading texts in document order, with fenced code blocks skipped.
 *
 * @return list<string>
 */
function docs_anchor_headings(string $markdown): array
{
    $headings = [];
    $fence = null;

    foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
        // A fence opens or closes on a run of three or more backticks or tildes.
        // The closing fence must use the same character as the opener.
        if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $fenceMatch) === 1) {
            $marker = $fenceMatch[1][0];

            if ($fence === null) {
                $fence = $marker;
            } elseif ($fence === $marker) {
                $fence = null;
            }

            continue;
        }

        if ($fence !== null) {
            continue;
        }

        if (preg_match('/^#{1,6}\s+(.*?)\s*$/', $line, $heading) === 1) {
            $headings[] = docs_anchor_heading_text($heading[1]);
        }
    }

    return $headings;
}

/**
 * The rendered text of a heading — what GitHub slugs from.
 *
 * A link keeps its label (`[Foo](bar.md)` slugs as `foo`), and inline code spans
 * keep their content, so the backticks come off but the words inside stay.
 */
function docs_anchor_heading_text(string $heading): string
{
    // Images first (`![alt](src)` -> `alt`), then links, so the `!` cannot leak.
    $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $heading) ?? $heading;
    $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;

    return trim($text);
}

/**
 * GitHub's heading slug for one heading text.
 *
 * Letters, numbers, marks, connector punctuation (`_`), ASCII hyphens and spaces
 * survive; everything else — emphasis markers, backticks, em dashes and other
 * punctuation — is dropped. Each space then becomes one hyphen, and the spaces are
 * **not** collapsed, so `Archivd — product domain` slugs as `archivd--product-domain`
 * (the em dash is removed, leaving the two spaces that flanked it). That is the
 * anchor GitHub renders, and the form the existing docs already link to.
 */
function docs_anchor_slug(string $heading): string
{
    $slug = mb_strtolower($heading, 'UTF-8');

    // `-` is spelled out rather than left to `\p{Pd}`, which would keep em dashes.
    $slug = (string) preg_replace('/[^\p{L}\p{N}\p{M}\p{Pc}\s\-]+/u', '', $slug);
    $slug = (string) preg_replace('/\s/u', '-', $slug);

    return trim($slug);
}

/**
 * Collapse `.` and `..` segments without touching the filesystem, so two paths can be
 * compared even when they do not exist — which is the case for a link into a sibling
 * checkout that CI never fetched.
 */
function docs_path_normalize(string $path): string
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
 * Does `$path` live inside `$root`? Both are normalized first, so a link like
 * `../../../../gateway/docs/x.md` is recognised as leaving the repo rather than as a
 * missing file.
 */
function docs_path_within(string $path, string $root): bool
{
    $path = docs_path_normalize($path);
    $root = rtrim(docs_path_normalize($root), '/');

    return $path === $root || str_starts_with($path, $root.'/');
}

/**
 * The sibling checkout a cross-repo link points into — the first path segment outside
 * `$root`. `is_dir()` on the result is the presence probe: these checkouts exist on a
 * developer machine and are absent in CI, so an unresolvable sibling means the link is
 * *unverifiable*, not broken.
 */
function docs_path_sibling_root(string $path, string $root): string
{
    $parent = rtrim(dirname(rtrim(docs_path_normalize($root), '/')), '/');
    $escaped = substr(docs_path_normalize($path), strlen($parent) + 1);

    return $parent.'/'.strtok($escaped, '/');
}

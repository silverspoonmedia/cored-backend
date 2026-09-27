<?php

/**
 * The cross-repo half of the docs guards.
 *
 * A link like `../../../monolith-vue-workers/docs/i18n.md` leaves this repo. The guard
 * treats it as *unverifiable* when the sibling checkout is absent — correct, because
 * CI checks out one repo and a missing sibling is not a broken link — but that rule
 * has a cost: those links are never checked anywhere automated. Wave F measured 38 of
 * them in one direction and 6 in the other, all verified only on a machine that has
 * both repos.
 *
 * This library makes the present-sibling case a first-class, testable check:
 * `docs_cross_repo_report()` walks the same sources the guard walks, resolves every
 * outbound link, and returns what it verified, what it skipped, and what broke. The
 * script `scripts/docs-cross-repo-links.php` is the CLI over it; the unit test drives
 * it against fixtures, which is how the fail-closed behaviour is proven without
 * needing two checkouts on the runner.
 */

require_once __DIR__.'/docs-anchor-support.php';

/**
 * Every markdown file under `$docsDir`, minus the exempt subdirectories.
 *
 * @param  list<string>  $exempt  directory names directly under docs/ to skip
 * @return list<string> absolute paths, sorted
 */
function docs_paths_markdown(string $docsDir, array $exempt = []): array
{
    if (! is_dir($docsDir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($docsDir) + 1));

        foreach ($exempt as $exemptDir) {
            if (str_starts_with($relative, $exemptDir.'/')) {
                continue 2;
            }
        }

        $files[] = $file->getPathname();
    }

    sort($files);

    return $files;
}

/**
 * The files whose outbound links the guard resolves: living docs, rule files,
 * AGENTS.md and the root README. Kept next to `docs_paths_markdown()` so the guard and
 * this report cannot disagree about what a "link source" is.
 *
 * @return list<string> absolute paths, sorted
 */
function docs_link_sources(string $root): array
{
    $sources = array_merge(
        docs_paths_markdown($root.'/docs', ['archive']),
        glob($root.'/.cursor/rules/*.mdc') ?: [],
    );

    foreach (['/AGENTS.md', '/README.md'] as $rootFile) {
        if (is_file($root.$rootFile)) {
            $sources[] = $root.$rootFile;
        }
    }

    sort($sources);

    return $sources;
}

/**
 * Resolve every link that leaves `$root` into a sibling checkout.
 *
 * A sibling that is absent makes the link *skipped*, never *broken* — the same
 * contract the guard applies, so a single-repo run stays green. A sibling that is
 * present makes it *verified*: path and `#anchor` both, fail-closed.
 *
 * @return array{verified: int, skipped: int, findings: list<string>}
 */
function docs_cross_repo_report(string $root): array
{
    $root = docs_path_normalize($root);
    $verified = 0;
    $skipped = 0;
    $findings = [];

    foreach (docs_link_sources($root) as $source) {
        $contents = (string) file_get_contents($source);
        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $contents, $links);

        foreach (array_unique($links[1] ?? []) as $link) {
            if (preg_match('~^(https?:|mailto:|tel:)~', $link) === 1) {
                continue;
            }

            [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);

            if ($path === '') {
                continue;
            }

            $resolved = docs_path_normalize(
                dirname($source).'/'.urldecode($path)
            );

            // Inside the repo: the guard's own link check owns it.
            if (docs_path_within($resolved, $root)) {
                continue;
            }

            $sibling = docs_path_sibling_root($resolved, $root);

            if (! is_dir($sibling)) {
                $skipped++;

                continue;
            }

            $verified++;
            $rel = static fn (string $p): string => str_replace('\\', '/', substr($p, strlen($root) + 1));

            if (! file_exists($resolved)) {
                $findings[] = "{$rel($source)} links to {$link}, which does not exist in {$rel($sibling)}";

                continue;
            }

            if ($anchor === null || ! preg_match('/\.mdc?$/i', $resolved)) {
                continue;
            }

            if (! in_array(urldecode($anchor), docs_anchor_slugs($resolved), true)) {
                $findings[] = "{$rel($source)} links to {$link}, and no heading in that file has that anchor";
            }
        }
    }

    return ['verified' => $verified, 'skipped' => $skipped, 'findings' => $findings];
}

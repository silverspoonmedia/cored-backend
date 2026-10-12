<?php

// Docs guard — keeps the brief/pakem split from drifting.
//
// Two documentation surfaces exist and each topic has exactly one owner:
//
//   docs/**            human brief: one paragraph + pointer table
//   .cursor/rules/*.mdc agent pakem: imperative, glob-scoped, self-sufficient
//   AGENTS.md          always-on map: scope tag + one line per rule file
//
// The failure modes this guard closes are all silent: a brief that is never indexed,
// a brief that grew back into an essay, a rule whose `globs:` matches nothing (so it
// never fires), a rule that AGENTS.md forgot, a relative link to a file that moved,
// a link whose `#anchor` no longer matches any heading, and `docs/agents/` creeping
// back as a second home for agent rules.
//
// Not every living doc is a brief. A deep dive, a settled-terms glossary and an
// edge-case inventory each have their own budget (see
// `docs_budget_doc_kind()`), and a deep dive only earns its size when the brief in the
// same directory links to it. The allowlist is the last resort for a file that
// outgrows even its own kind — and an entry whose file now fits is itself a finding,
// so the list can only shrink.
//
// Usage:
//   php scripts/docs-guard.php                 this repo + the sibling SPA when present
//   php scripts/docs-guard.php --repo=backend  this repo only
//   php scripts/docs-guard.php --repo=spa      sibling SPA only (exits 1 when absent)
//
// Zero tolerance: any finding exits 1.

require __DIR__.'/lib/docs-anchor-support.php';
require __DIR__.'/lib/docs-budget-support.php';
require __DIR__.'/lib/docs-cross-repo-support.php';

$root = dirname(__DIR__);
$repo = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--repo=')) {
        $repo = substr($arg, 7);
    }
}

$repo = $repo ?: 'all';

if (! in_array($repo, ['all', 'backend', 'spa'], true)) {
    fwrite(STDERR, "unknown --repo value: {$repo} (expected all|backend|spa)".PHP_EOL);
    exit(1);
}

$targets = [];

if ($repo === 'backend' || $repo === 'all') {
    $targets[] = ['label' => 'monolith', 'root' => $root];
}

if ($repo === 'spa' || $repo === 'all') {
    $spaRoot = dirname($root).DIRECTORY_SEPARATOR.'monolith-vue-workers';

    if (! is_dir($spaRoot)) {
        fwrite(STDERR, 'cannot see the SPA repo: '.$spaRoot.PHP_EOL);
        fwrite(STDERR, 'The doc/rules split spans both repos, so a run without the sibling proves only half of it.'.PHP_EOL);
        fwrite(STDERR, 'Check out monolith-vue-workers as a sibling of this repo and retry, or run --repo=backend.'.PHP_EOL);
        exit(1);
    }

    $targets[] = ['label' => 'monolith-vue-workers', 'root' => $spaRoot];
}

$findings = [];
$unverifiable = 0;

foreach ($targets as $target) {
    $result = docs_guard_repo($target['root'], $target['label']);

    $findings = array_merge($findings, $result['findings']);
    $unverifiable += $result['unverifiable'];
}

if ($findings !== []) {
    fwrite(STDERR, 'docs guard: '.count($findings).' finding(s)'.PHP_EOL);
    foreach ($findings as $finding) {
        fwrite(STDERR, '  - '.$finding.PHP_EOL);
    }
    exit(1);
}

// An absent sibling is not a finding, but it is also not a pass: the links into it
// were never checked. Saying so on the OK line keeps a green run from reading as
// "every link in this repo was verified".
echo 'docs guard: OK ('.count($targets).' repo(s)'
    .($unverifiable > 0 ? ", {$unverifiable} cross-repo link(s) unverified — sibling checkout absent" : '')
    .')'.PHP_EOL;
exit(0);

/**
 * @return array{findings: list<string>, unverifiable: int}
 */
function docs_guard_repo(string $root, string $label): array
{
    $findings = [];
    $unverifiable = 0;
    $rel = static fn (string $path): string => str_replace('\\', '/', substr($path, strlen($root) + 1));

    $docsDir = $root.'/docs';
    $rulesDir = $root.'/.cursor/rules';
    $indexPath = $docsDir.'/README.md';
    $agentsPath = $root.'/AGENTS.md';

    // 1. Agent rules have exactly one home.
    if (is_dir($docsDir.'/agents')) {
        $findings[] = "[{$label}] docs/agents/ exists — agent rules live in .cursor/rules/*.mdc";
    }

    if (! is_file($indexPath)) {
        $findings[] = "[{$label}] docs/README.md is missing — every living doc must be indexed";

        return ['findings' => $findings, 'unverifiable' => $unverifiable];
    }

    $index = (string) file_get_contents($indexPath);

    // 2. Living docs (everything but docs/archive) must be indexed and within budget.
    $allowlist = docs_guard_allowlist($docsDir, $label, $findings);
    $livingDocs = docs_paths_markdown($docsDir, ['archive']);

    foreach ($livingDocs as $doc) {
        $relative = $rel($doc);
        $indexKey = substr($relative, strlen('docs/'));
        $kind = docs_budget_doc_kind($indexKey);

        if ($indexKey !== 'README.md' && ! str_contains($index, $indexKey)) {
            $findings[] = "[{$label}] {$relative} is not listed in docs/README.md";
        }

        $lines = docs_guard_line_count($doc);

        if ($lines > $kind['budget'] && ! array_key_exists($relative, $allowlist)) {
            $findings[] = "[{$label}] {$relative} is {$lines} lines (kind {$kind['name']}, budget {$kind['budget']})"
                .' — split the invariants into a rule file or move narrative to docs/archive/, then add an allowlist entry only if it must stay';
        }

        // A deep dive is reference material the brief in the same directory points at.
        // Unlinked, it is a second front door to the same topic.
        if ($kind['name'] === 'deep-dive') {
            $brief = dirname($doc).'/README.md';

            if (is_file($brief) && ! str_contains((string) file_get_contents($brief), basename($doc))) {
                $findings[] = "[{$label}] {$relative} is a deep dive no brief links to — add it to ".$rel($brief);
            }
        }
    }

    foreach ($allowlist as $allowed => $reason) {
        if (! is_file($root.'/'.$allowed)) {
            $findings[] = "[{$label}] docs/.docs-budget-allowlist.json lists {$allowed}, which does not exist — drop the stale entry";

            continue;
        }

        $lines = docs_guard_line_count($root.'/'.$allowed);
        $kind = docs_budget_doc_kind(substr($allowed, strlen('docs/')));

        if ($lines <= $kind['budget']) {
            $findings[] = "[{$label}] docs/.docs-budget-allowlist.json lists {$allowed} ({$lines} lines) but it now fits its "
                ."{$kind['name']} budget ({$kind['budget']}) — drop the entry";
        }
    }

    // 3. Rule files: budget, glob targets, AGENTS.md coverage, links.
    $ruleFiles = glob($rulesDir.'/*.mdc') ?: [];
    sort($ruleFiles);

    if ($ruleFiles === []) {
        $findings[] = "[{$label}] .cursor/rules/*.mdc is empty — the normative surface is missing";
    }

    $agents = is_file($agentsPath) ? (string) file_get_contents($agentsPath) : null;

    if ($agents === null) {
        $findings[] = "[{$label}] AGENTS.md is missing — it is the always-on rule map";
    }

    $alwaysOnLines = 0;

    foreach ($ruleFiles as $rule) {
        $name = basename($rule);
        $contents = (string) file_get_contents($rule);
        $lines = docs_guard_line_count($rule);

        if (docs_guard_is_always_on($contents)) {
            // Always-on rules are governed by the combined budget below, not by the
            // per-rule one: they carry the rules that must hold in every task. Their
            // `globs:` are still validated — an always-on rule fires everywhere, but
            // a glob that matches nothing is a stale declaration either way, and
            // skipping it here is how eight dead globs survived on this repo.
            $alwaysOnLines += $lines;
        } elseif ($lines > DOCS_BUDGET_RULE_MAX_LINES) {
            $findings[] = "[{$label}] .cursor/rules/{$name} is {$lines} lines (budget ".DOCS_BUDGET_RULE_MAX_LINES.') — split by concern';
        }

        foreach (docs_guard_globs($contents) as $glob) {
            if (! docs_guard_glob_matches($root, $glob)) {
                $findings[] = "[{$label}] .cursor/rules/{$name} globs: {$glob} matches no file — the rule never fires";
            }
        }

        if ($agents !== null && ! str_contains($agents, $name)) {
            $findings[] = "[{$label}] .cursor/rules/{$name} is not referenced in AGENTS.md";
        }
    }

    if ($alwaysOnLines > DOCS_BUDGET_ALWAYS_ON_MAX_LINES) {
        $findings[] = "[{$label}] always-on rules total {$alwaysOnLines} lines (budget "
            .DOCS_BUDGET_ALWAYS_ON_MAX_LINES.') — move a rule to a glob scope or trim';
    }

    if ($agents !== null) {
        preg_match_all('/[a-z0-9-]+\.mdc/', $agents, $mentioned);

        foreach (array_unique($mentioned[0] ?? []) as $mentionedRule) {
            if (! is_file($rulesDir.'/'.$mentionedRule)) {
                $findings[] = "[{$label}] AGENTS.md references {$mentionedRule}, which does not exist";
            }
        }
    }

    // 4. Relative markdown links in living docs, rules, AGENTS.md and the root
    //    README must resolve — path *and* `#anchor`. The path half catches a file
    //    that moved; the anchor half catches a heading that was reworded, which
    //    leaves every deep link into it pointing at nothing. External links keep the
    //    path check only: their anchors belong to someone else's site.
    //
    //    The root README is the front door and is not under docs/, so the living-doc
    //    walk never reaches it: a dead anchor there stayed invisible until it was
    //    added here. It gets the link and anchor checks, not a line budget.
    $rootReadme = $root.'/README.md';
    $linkSources = array_merge(
        $livingDocs,
        $ruleFiles,
        is_file($agentsPath) ? [$agentsPath] : [],
        is_file($rootReadme) ? [$rootReadme] : [],
    );

    foreach ($linkSources as $source) {
        $contents = (string) file_get_contents($source);
        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $contents, $links);

        $sourceSlugs = null;

        foreach (array_unique($links[1] ?? []) as $link) {
            if (preg_match('~^(https?:|mailto:|tel:)~', $link) === 1) {
                continue;
            }

            [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);

            // A same-file anchor resolves against the file the link is written in.
            if ($path === '' && $anchor !== null) {
                $sourceSlugs ??= docs_anchor_slugs($source);

                if (! in_array(urldecode($anchor), $sourceSlugs, true)) {
                    $findings[] = "[{$label}] {$rel($source)} links to {$link}, and no heading in that file has that anchor";
                }

                continue;
            }

            if ($path === '') {
                continue;
            }

            $resolved = dirname($source).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, urldecode($path));

            // A link that escapes this repo points at a sibling checkout
            // (`monolith-vue-workers`, `gateway`). Those are present on a developer
            // machine and absent in CI, so an unresolvable sibling is an
            // *unverifiable* link, not a broken one — the same rule the SPA guard
            // applies in reverse. Reporting it here would fail `--repo=backend`,
            // which exists precisely so the guard can run with no sibling at all.
            if (! docs_path_within($resolved, $root)
                && ! is_dir(docs_path_sibling_root($resolved, $root))) {
                $unverifiable++;

                continue;
            }

            if (! file_exists($resolved)) {
                $findings[] = "[{$label}] {$rel($source)} links to {$link}, which does not exist";

                continue;
            }

            if ($anchor === null || ! preg_match('/\.mdc?$/i', $resolved)) {
                continue;
            }

            if (! in_array(urldecode($anchor), docs_anchor_slugs($resolved), true)) {
                $findings[] = "[{$label}] {$rel($source)} links to {$link}, and no heading in "
                    .$rel($resolved).' has that anchor';
            }
        }
    }

    return ['findings' => $findings, 'unverifiable' => $unverifiable];
}

/**
 * @param  list<string>  $findings  appended to when the allowlist file is malformed
 * @return array<string, string>
 */
function docs_guard_allowlist(string $docsDir, string $label, array &$findings): array
{
    $path = $docsDir.'/.docs-budget-allowlist.json';

    if (! is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded) || ! is_array($decoded['files'] ?? null)) {
        $findings[] = "[{$label}] docs/.docs-budget-allowlist.json is malformed (expected {\"files\": {path: reason}})";

        return [];
    }

    foreach ($decoded['files'] as $file => $reason) {
        if (! is_string($reason) || trim($reason) === '') {
            $findings[] = "[{$label}] docs/.docs-budget-allowlist.json entry {$file} needs a reason";
        }
    }

    return $decoded['files'];
}

function docs_guard_line_count(string $path): int
{
    $contents = rtrim((string) file_get_contents($path), "\r\n");

    return $contents === '' ? 0 : substr_count($contents, "\n") + 1;
}

function docs_guard_is_always_on(string $contents): bool
{
    if (! str_starts_with(ltrim($contents), '---')) {
        return true;
    }

    $end = strpos($contents, '---', 3);

    if ($end === false) {
        return true;
    }

    $frontmatter = substr($contents, 3, $end - 3);

    if (preg_match('/alwaysApply:\s*true/i', $frontmatter) === 1) {
        return true;
    }

    return preg_match('/alwaysApply:\s*false/i', $frontmatter) === 1 ? false : true;
}

/**
 * @return list<string>
 */
function docs_guard_globs(string $contents): array
{
    if (! preg_match('/^globs:\s*(.+)$/m', $contents, $match)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', trim($match[1], " \t\"'[]")))));
}

/**
 * Every relative path under `$root` that a rule glob is allowed to match.
 *
 * Built once per root and pruned *while walking*: the skip list below used to be
 * applied after `RecursiveDirectoryIterator` had already descended into the tree,
 * so every glob of every rule re-walked `node_modules/` and `vendor/` to discard
 * them one path at a time. That made this guard's runtime a property of the machine
 * rather than of the repo — on a loaded box the walk blew the suite's 180s timeout,
 * which reads as a broken doc split instead of a slow disk.
 */
function docs_guard_root_files(string $root): array
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    $skipPrefixes = ['vendor/', 'node_modules/', '.git/', 'storage/', 'public/build/'];
    $prefix = strlen($root) + 1;

    $relativePath = static function (SplFileInfo $file) use ($prefix): string {
        return str_replace('\\', '/', substr($file->getPathname(), $prefix));
    };

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($relativePath, $skipPrefixes): bool {
                $relative = $relativePath($file);

                foreach ($skipPrefixes as $skip) {
                    if (str_starts_with($relative, $skip)) {
                        return false;
                    }

                    // The directory entry itself carries no trailing slash, so it has
                    // to be pruned by name — otherwise the walk descends into the tree
                    // and discards it one path at a time, which is the cost this
                    // function exists to avoid.
                    if ($file->isDir() && $relative === rtrim($skip, '/')) {
                        return false;
                    }
                }

                return true;
            }
        )
    );

    $files = [];

    foreach ($iterator as $file) {
        $files[] = $relativePath($file);
    }

    return $cache[$root] = $files;
}

function docs_guard_glob_matches(string $root, string $glob): bool
{
    $regex = '#^'.str_replace(
        ['\*\*/', '\*\*', '\*', '\?'],
        ['.*/', '.*', '[^/]*', '[^/]'],
        preg_quote($glob, '#')
    ).'$#';

    foreach (docs_guard_root_files($root) as $relative) {
        if (preg_match($regex, $relative) === 1) {
            return true;
        }
    }

    return false;
}

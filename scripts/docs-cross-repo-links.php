<?php

// Cross-repo link check — the fail-closed half of the docs guard.
//
// `docs-guard.php` treats a link that leaves this repo as *unverifiable* when the
// sibling checkout is absent, which is right for CI (one repo, no credentials) but
// leaves 38 monolith -> monolith-vue-workers links (and a handful into `gateway`)
// checked nowhere automated. This script is the check for the case where the sibling
// *is* present: it resolves every outbound path and `#anchor`, and exits 1 on a
// break.
//
// It is deliberately separate from the guard rather than folded into it, because the
// two have different contracts:
//
//   docs-guard.php            runs everywhere; an absent sibling is not a failure
//   docs-cross-repo-links.php runs where both repos exist; a broken link is
//
// CI does not run this (the sibling is private and `GITHUB_TOKEN` cannot read it).
// It runs from a developer machine and from `DocsCrossRepoLinkTest`, which drives the
// same library against fixtures — so the fail-closed behaviour is covered on the
// runner even though the real cross-repo links are not.
//
// Usage:
//   php scripts/docs-cross-repo-links.php

require __DIR__.'/lib/docs-cross-repo-support.php';

$root = docs_path_normalize(dirname(__DIR__));
$report = docs_cross_repo_report($root);

if ($report['findings'] !== []) {
    fwrite(STDERR, 'cross-repo links: '.count($report['findings']).' finding(s)'.PHP_EOL);

    foreach ($report['findings'] as $finding) {
        fwrite(STDERR, '  - '.$finding.PHP_EOL);
    }

    exit(1);
}

$summary = "cross-repo links: OK ({$report['verified']} verified";

if ($report['skipped'] > 0) {
    $summary .= ", {$report['skipped']} skipped — sibling checkout absent";
}

echo $summary.')'.PHP_EOL;
exit(0);

<?php

/**
 * The size budgets the docs guard enforces, in one place.
 *
 * `docs-guard.php` is a script: it runs the check and exits, so nothing can `require`
 * it to read the numbers it enforces. That made the budgets un-inspectable, and the
 * human copy of them lives in `.cursor/rules/docs-pakem.mdc` — two copies, no
 * comparison, drifting silently. Wave F found the table and the constants agreeing by
 * luck rather than by construction.
 *
 * Moving them here gives the guard and the drift test (`DocsBudgetGuardTest`) the same
 * source, so the table can be asserted against the numbers instead of trusted.
 *
 * The SPA keeps its own copy in `monolith-vue-workers/scripts/check-docs.mjs` — a
 * Node script cannot share PHP constants — so the drift test reads that file's
 * constants and `docKind()` too, and the two repos are compared through it.
 */

/**
 * A glob-scoped rule is a focused concern, not a second doc.
 */
const DOCS_BUDGET_RULE_MAX_LINES = 60;

/**
 * The always-on set is the context every conversation pays for.
 */
const DOCS_BUDGET_ALWAYS_ON_MAX_LINES = 150;

/**
 * Which kind of living doc this is, and what it may cost in lines.
 *
 * The kind is derived from the file name, not from a registry: the name already says
 * what the file is, and a new kind has to be a deliberate choice here. Everything
 * that is not a named kind is a brief.
 *
 * A directory that needs more than one deep dive splits it into `reference-<topic>.md`
 * siblings; the `reference` prefix keeps every one of them a deep dive, so each is
 * still linked from the brief in the same directory.
 *
 * @param  string  $relativeToDocs  path relative to docs/, e.g. `platform/tooling.md`
 * @return array{name: string, budget: int}
 */
function docs_budget_doc_kind(string $relativeToDocs): array
{
    $name = basename($relativeToDocs);

    return match (true) {
        $relativeToDocs === 'README.md' => ['name' => 'index', 'budget' => 150],
        in_array($name, ['billing-edge-cases.md', 'daily-free-trial.md', 'reference-edge-cases.md'], true) => ['name' => 'edge-case', 'budget' => 200],
        str_starts_with($name, 'reference') => ['name' => 'deep-dive', 'budget' => 400],
        $name === 'naming-conventions.md' => ['name' => 'glossary', 'budget' => 200],
        default => ['name' => 'brief', 'budget' => 90],
    };
}

/**
 * Every kind the guard knows, as the human table names it, with a path that resolves
 * to that kind. Used by the drift test to compare the table with the registry.
 *
 * The label is the exact text the rule file writes before the number; the path is a
 * real file of that kind, so the test proves the label, the number and the registry
 * agree rather than only that two integers match.
 *
 * @return array<string, array{0: string, 1: string}> human label => [kind name, sample path relative to docs/]
 */
function docs_budget_kind_samples(): array
{
    return [
        'index' => ['index', 'README.md'],
        'brief' => ['brief', 'platform/tooling.md'],
        'deep dive' => ['deep-dive', 'platform/reference-tooling.md'],
        'glossary' => ['glossary', 'platform/naming-conventions.md'],
        'edge case' => ['edge-case', 'platform/reference-edge-cases.md'],
    ];
}

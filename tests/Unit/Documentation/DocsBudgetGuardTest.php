<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * The doc-size budgets are stated twice on purpose — once as the numbers the guards
 * enforce, once as the sentence a human reads in `.cursor/rules/docs-pakem.mdc`.
 *
 * Wave F found the two agreeing by luck, not by construction: the rule named a
 * registry function that lived inside a script nobody could `require`, so the only
 * way to notice a drift was to read both and compare by eye. This test makes the
 * comparison mechanical. Editing a budget in one place and not the other fails here,
 * naming the kind.
 *
 * The SPA keeps a third copy in `scripts/check-docs.mjs` (a Node script cannot share
 * PHP constants), so it is compared too, and skipped when the sibling is not present.
 */
class DocsBudgetGuardTest extends TestCase
{
    private const SUPPORT = 'scripts/lib/docs-budget-support.php';

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', dirname(__DIR__, 3));

        require_once $this->root.'/'.self::SUPPORT;
    }

    public function test_every_sample_path_resolves_to_the_kind_it_claims(): void
    {
        foreach (docs_budget_kind_samples() as $label => [$kind, $sample]) {
            $path = $this->root.'/docs/'.$sample;

            $this->assertFileExists($path, "The sample for `{$label}` ({$sample}) does not exist — the table names a kind no doc uses");

            $this->assertSame(
                $kind,
                docs_budget_doc_kind($sample)['name'],
                "The sample for `{$label}` ({$sample}) does not resolve to kind `{$kind}`",
            );
        }
    }

    /**
     * The sentence in the rule file is the human owner of the numbers: split it on the
     * `·` separators and read the trailing integer of each segment.
     */
    public function test_the_rule_table_matches_the_registry(): void
    {
        $rule = $this->ruleContents();

        $this->assertMatchesRegularExpression(
            '/Budgets: .+$/m',
            $rule,
            'The budget sentence is missing from docs-pakem.mdc — this test cannot verify the numbers',
        );

        preg_match('/Budgets: (.+)$/m', $rule, $match);

        // The sentence ends with a full stop after the last number; drop it so the
        // final segment parses like every other one.
        $sentence = rtrim(rtrim(trim($match[1]), "\r"), '.');

        $stated = [];

        foreach (explode('·', $sentence) as $segment) {
            $segment = trim($segment);

            if (preg_match('/^([^`(]+?)\s*[`(]/', $segment, $label) !== 1
                || preg_match('/(\d+)$/', $segment, $number) !== 1) {
                $this->fail("Cannot read a label and a number out of the budget segment [{$segment}]");
            }

            $stated[$label[1]] = (int) $number[1];
        }

        $expected = [];

        foreach (docs_budget_kind_samples() as $label => [$kind, $sample]) {
            $expected[$label] = docs_budget_doc_kind($sample)['budget'];
        }

        ksort($expected);
        ksort($stated);

        $this->assertSame(
            $expected,
            $stated,
            'The budget sentence in docs-pakem.mdc and docs_budget_doc_kind() disagree',
        );
    }

    public function test_the_rule_caps_match_the_constants(): void
    {
        $rule = $this->ruleContents();

        $this->assertMatchesRegularExpression(
            '/Glob-scoped rule ≤ '.DOCS_BUDGET_RULE_MAX_LINES.' lines; all always-on rules combined ≤ '.DOCS_BUDGET_ALWAYS_ON_MAX_LINES.'\./',
            $rule,
            'The rule file states different caps than DOCS_BUDGET_RULE_MAX_LINES / DOCS_BUDGET_ALWAYS_ON_MAX_LINES',
        );
    }

    /**
     * The SPA twin holds the same numbers in JavaScript. A Node script cannot require
     * the PHP constants, so the comparison happens here — and is skipped when the
     * sibling checkout is absent, which is the case in the backend CI job.
     */
    public function test_the_spa_twin_holds_the_same_numbers(): void
    {
        $script = $this->root.'/../monolith-vue-workers/scripts/check-docs.mjs';

        if (! is_file($script)) {
            $this->markTestSkipped('The monolith-vue-workers sibling is not checked out.');
        }

        $contents = (string) file_get_contents($script);

        preg_match('/const RULE_MAX_LINES = (\d+)/', $contents, $ruleMax);
        preg_match('/const ALWAYS_ON_MAX_LINES = (\d+)/', $contents, $alwaysOnMax);

        $this->assertSame(DOCS_BUDGET_RULE_MAX_LINES, (int) ($ruleMax[1] ?? 0), 'SPA RULE_MAX_LINES drifted');
        $this->assertSame(DOCS_BUDGET_ALWAYS_ON_MAX_LINES, (int) ($alwaysOnMax[1] ?? 0), 'SPA ALWAYS_ON_MAX_LINES drifted');

        preg_match_all("/return \{ name: '([a-z-]+)', budget: (\d+) \}/", $contents, $kinds, PREG_SET_ORDER);

        $spa = [];

        foreach ($kinds as $kind) {
            $spa[$kind[1]] = (int) $kind[2];
        }

        $expected = [];

        foreach (docs_budget_kind_samples() as [$kind, $sample]) {
            $expected[$kind] = docs_budget_doc_kind($sample)['budget'];
        }

        ksort($expected);
        ksort($spa);

        $this->assertSame($expected, $spa, 'The SPA docKind() budgets drifted from the backend registry');
    }

    private function ruleContents(): string
    {
        $path = $this->root.'/.cursor/rules/docs-pakem.mdc';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}

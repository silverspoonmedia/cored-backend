<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards against the failure mode that survived three Blade-removal audits:
 * documentation that keeps naming route or lang files long after they were
 * deleted. Docs under docs/archive/ are historical snapshots and exempt.
 */
class DocReferenceIntegrityTest extends TestCase
{
    private const EXEMPT_DIRS = [
        'docs/archive',
    ];

    /**
     * @return list<string> repo-relative markdown paths
     */
    private function markdownFiles(): array
    {
        $root = $this->repoRoot();
        $files = [];

        foreach (['README.md', 'AGENTS.md'] as $topLevel) {
            if (is_file($root.'/'.$topLevel)) {
                $files[] = $topLevel;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/docs', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            foreach (self::EXEMPT_DIRS as $exempt) {
                if (str_starts_with($relative, $exempt.'/')) {
                    continue 2;
                }
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    public function test_referenced_web_route_files_exist(): void
    {
        $this->assertReferencedFilesExist(
            '#\broutes/web/([a-z0-9][a-z0-9._-]*\.php)#',
            fn (string $match): string => 'routes/web/'.$match,
            'The Blade removal deleted most routes/web/*.php files. Point at the surviving server endpoints or drop the reference.',
        );
    }

    public function test_referenced_web_lang_files_exist(): void
    {
        $this->assertReferencedFilesExist(
            '#\blang/(?:en|id|ja)/web/([a-z0-9][a-z0-9._-]*\.php)#',
            fn (string $match): string => 'lang/en/web/'.$match,
            'Most lang/*/web/*.php files were deleted with the Blade UI. Use a surviving file or describe it without a concrete path.',
        );
    }

    /**
     * @param  callable(string): string  $resolve  capture group -> repo-relative path to check
     */
    private function assertReferencedFilesExist(string $pattern, callable $resolve, string $hint): void
    {
        $root = $this->repoRoot();
        $failures = [];

        foreach ($this->markdownFiles() as $doc) {
            $contents = (string) file_get_contents($root.'/'.$doc);

            if (preg_match_all($pattern, $contents, $matches) === 0) {
                continue;
            }

            foreach (array_unique($matches[1]) as $capture) {
                $target = $resolve($capture);

                if (! is_file($root.'/'.$target)) {
                    $failures[] = "{$doc} references missing {$target}";
                }
            }
        }

        $this->assertSame([], $failures, $hint."\n".implode("\n", $failures));
    }

    private function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }
}

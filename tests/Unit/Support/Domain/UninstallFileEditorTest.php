<?php

namespace Tests\Unit\Support\Domain;

use App\Support\Domain\UninstallFileEditor;
use App\Support\Domain\UninstallManifest;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The `remove_block` op has two forms, and both are *selectors*: an anchor names
 * an entry, never a line number, so a manifest survives the file moving around
 * it. These cases pin the two units of removal — a container block and a single
 * entry with its wrapped lines and attached comment — plus the schema rule that
 * a `remove_block` entry carries exactly one of `begin` / `begins`.
 */
class UninstallFileEditorTest extends TestCase
{
    private UninstallFileEditor $editor;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = new UninstallFileEditor;
        $this->path = tempnam(sys_get_temp_dir(), 'uninstall-').'.php';
    }

    protected function tearDown(): void
    {
        File::delete($this->path);

        parent::tearDown();
    }

    #[Test]
    public function a_bare_anchor_removes_the_container_it_opens(): void
    {
        $this->write(<<<'PHP'
            <?php
            return [
                'keep' => ['a', 'b'],
                'Acme' => [
                    'label' => 'Acme',
                    'parent' => 'Globex',
                ],
            ];
            PHP);

        $result = $this->editor->apply($this->path, ['op' => 'remove_block', 'begin' => "'Acme' =>"]);

        $this->assertSame(1, $result['removed']);
        $this->assertStringNotContainsString('Acme', File::get($this->path));
        $this->assertStringContainsString("'keep' => ['a', 'b'],", File::get($this->path));
    }

    #[Test]
    public function an_anchor_that_opens_no_container_removes_only_its_own_entry(): void
    {
        $this->write(<<<'PHP'
            <?php
            // Kept for the next release.
            $AcmeDropdown = 'Acme';

            return ['kept' => true];
            PHP);

        $result = $this->editor->apply($this->path, [
            'op' => 'remove_block',
            'begins' => ['$AcmeDropdown'],
        ]);

        $contents = File::get($this->path);

        $this->assertSame(1, $result['removed']);
        $this->assertStringNotContainsString('AcmeDropdown', $contents);
        $this->assertStringNotContainsString('Kept for the next release.', $contents);
        $this->assertStringContainsString("return ['kept' => true];", $contents);
    }

    #[Test]
    public function a_comment_block_attached_above_the_entry_leaves_with_it(): void
    {
        $this->write(<<<'PHP'
            <?php
            // The product's own knob.
            // Two lines of it.
            'Globex.debug' => (bool) env('Globex_DEBUG', false),

            'Globex.keep' => 'yes',
            PHP);

        $this->editor->apply($this->path, ['op' => 'remove_block', 'begins' => ["'Globex.debug'"]]);

        $contents = File::get($this->path);

        $this->assertStringNotContainsString('The product', $contents);
        $this->assertStringNotContainsString('Two lines', $contents);
        $this->assertStringContainsString("'Globex.keep' => 'yes',", $contents);
    }

    #[Test]
    public function a_wrapped_entry_is_removed_whole(): void
    {
        $this->write(<<<'PHP'
            <?php
            parent::register([
                'storage.upload_proxy' => config('Globex.upload_proxy', [
                    'base_url' => '',
                    'token' => '',
                ]),
                'kept' => true,
            ]);
            PHP);

        $this->editor->apply($this->path, ['op' => 'remove_block', 'begin' => "'storage.upload_proxy' =>"]);

        $contents = File::get($this->path);

        $this->assertStringNotContainsString('base_url', $contents);
        $this->assertStringNotContainsString('token', $contents);
        $this->assertStringContainsString("'kept' => true,", $contents);
    }

    #[Test]
    public function several_anchors_remove_several_entries_for_one_credit(): void
    {
        $this->write(<<<'PHP'
            <?php
            use App\Domain\Globex\Support\One;
            use App\Domain\Globex\Support\Two;
            use App\Other\Support\Three;

            $x = new One(new Two(new Three));
            PHP);

        $result = $this->editor->apply($this->path, [
            'op' => 'remove_block',
            'begins' => ['use App\Domain\Globex\Support\One;', 'use App\Domain\Globex\Support\Two;'],
        ]);

        $contents = File::get($this->path);

        $this->assertSame(2, $result['removed']);
        $this->assertStringNotContainsString('Support\One;', $contents);
        $this->assertStringNotContainsString('Support\Two;', $contents);
        $this->assertStringContainsString('use App\Other\Support\Three;', $contents);
    }

    #[Test]
    public function an_absent_anchor_removes_nothing(): void
    {
        $this->write("<?php\nreturn ['kept' => true];");

        $result = $this->editor->apply($this->path, ['op' => 'remove_block', 'begin' => "'Acme' =>"]);

        $this->assertSame(0, $result['removed']);
    }

    #[Test]
    public function a_remove_block_edit_needs_exactly_one_of_begin_or_begins(): void
    {
        $manifest = $this->app->make(UninstallManifest::class);
        $base = [
            'schema' => UninstallManifest::SCHEMA,
            'domain' => 'Globex',
            'repos' => ['backend' => ['delete' => [], 'edits' => []]],
        ];

        foreach ([
            'neither' => ['file' => 'a.php', 'op' => 'remove_block'],
            'both' => ['file' => 'a.php', 'op' => 'remove_block', 'begin' => 'x', 'begins' => ['y']],
            'empty list' => ['file' => 'a.php', 'op' => 'remove_block', 'begins' => []],
        ] as $case => $edit) {
            $candidate = $base;
            $candidate['repos']['backend']['edits'] = [$edit];

            try {
                $manifest->validate($candidate, 'Globex');
                $this->fail("The schema accepted a remove_block edit with {$case} anchor.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('begin', $exception->getMessage(), $case);
            }
        }

        $candidate = $base;
        $candidate['repos']['backend']['edits'] = [['file' => 'a.php', 'op' => 'remove_block', 'begins' => ['y']]];
        $manifest->validate($candidate, 'Globex');

        $this->addToAssertionCount(1);
    }

    private function write(string $contents): void
    {
        File::put($this->path, $contents."\n");
    }
}

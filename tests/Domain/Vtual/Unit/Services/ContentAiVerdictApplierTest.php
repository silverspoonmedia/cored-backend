<?php

namespace Tests\Domain\Vtual\Unit\Services;

use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Services\ContentAiVerdictApplier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The applier is where the "program verdict binds" contract is enforced on the
 * write. The system prompt promises the model the same contract, and a prompt
 * that promises what the code does not keep is worse than no prompt at all.
 */
class ContentAiVerdictApplierTest extends TestCase
{
    private ContentAiVerdictApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applier = new ContentAiVerdictApplier;
    }

    #[Test]
    public function a_null_row_takes_the_whole_ai_verdict(): void
    {
        $content = $this->content(kind: null, category: null, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(
            isMusic: true,
            category: ContentCategory::Cover,
            detail: 'Kanaria - KING',
        ));

        $this->assertTrue($applied['changed']);
        $this->assertSame(ContentKind::Music, $applied['kind']);
        $this->assertSame(ContentCategory::Cover, $applied['category']);
        $this->assertSame(ContentKind::Music, $content->content_type);
        $this->assertSame(ContentCategory::Cover, $content->content_category);
        $this->assertSame('Kanaria - KING', $content->content_category_detail);
    }

    #[Test]
    public function a_bound_music_verdict_survives_a_not_music_answer(): void
    {
        // Strong program evidence (歌ってみた) already said music; a model
        // disagreement must not erase it.
        $content = $this->content(kind: ContentKind::Music, category: ContentCategory::Cover, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(isMusic: false));

        $this->assertFalse($applied['changed']);
        $this->assertNull($applied['kind']);
        $this->assertSame(ContentKind::Music, $content->content_type);
        $this->assertSame(ContentCategory::Cover, $content->content_category);
    }

    #[Test]
    public function a_bound_sub_category_is_never_replaced(): void
    {
        $content = $this->content(kind: ContentKind::Music, category: ContentCategory::Original, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(
            isMusic: true,
            category: ContentCategory::Cover,
            detail: 'someone else - song',
        ));

        $this->assertFalse($applied['changed']);
        $this->assertSame(ContentCategory::Original, $content->content_category);
        $this->assertNull($content->content_category_detail);
    }

    #[Test]
    public function the_ai_fills_the_sub_category_the_program_pass_abstained_on(): void
    {
        $content = $this->content(kind: ContentKind::Music, category: null, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(
            isMusic: true,
            category: ContentCategory::SingingStream,
        ));

        $this->assertTrue($applied['changed']);
        $this->assertNull($applied['kind']);
        $this->assertSame(ContentCategory::SingingStream, $applied['category']);
        $this->assertSame(ContentKind::Music, $content->content_type);
        $this->assertSame(ContentCategory::SingingStream, $content->content_category);
    }

    #[Test]
    public function an_attribution_is_only_written_for_the_sub_category_the_row_carries(): void
    {
        // The model named a cover attribution while the row is an original — the
        // text describes a verdict this row does not have, so it is dropped.
        $content = $this->content(kind: ContentKind::Music, category: ContentCategory::Original, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(
            isMusic: true,
            category: ContentCategory::Cover,
            detail: 'Kanaria - KING',
        ));

        $this->assertNull($content->content_category_detail);
        $this->assertFalse($applied['changed']);
    }

    #[Test]
    public function a_not_music_answer_clears_a_stale_sub_category_only_where_it_stands(): void
    {
        $content = $this->content(kind: null, category: ContentCategory::Cover, detail: 'stale attribution');

        $applied = $this->applier->apply($content, $this->verdict(isMusic: false));

        $this->assertTrue($applied['changed']);
        $this->assertSame(ContentKind::NonMusic, $applied['kind']);
        $this->assertSame(ContentKind::NonMusic, $content->content_type);
        $this->assertNull($content->content_category);
        $this->assertNull($content->content_category_detail);
    }

    #[Test]
    public function a_null_sub_category_in_a_music_verdict_still_resolves_the_kind(): void
    {
        $content = $this->content(kind: null, category: null, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(isMusic: true, category: null));

        $this->assertSame(ContentKind::Music, $applied['kind']);
        $this->assertNull($applied['category']);
        $this->assertSame(ContentKind::Music, $content->content_type);
        $this->assertNull($content->content_category);
    }

    #[Test]
    public function a_verdict_matching_the_row_is_not_a_change(): void
    {
        $content = $this->content(kind: ContentKind::Music, category: ContentCategory::Cover, detail: null);

        $applied = $this->applier->apply($content, $this->verdict(
            isMusic: true,
            category: ContentCategory::Cover,
            detail: null,
        ));

        $this->assertFalse($applied['changed']);
    }

    /**
     * @return array{is_music: bool, category: ?ContentCategory, detail: ?string, confidence: float}
     */
    private function verdict(
        bool $isMusic,
        ?ContentCategory $category = null,
        ?string $detail = null,
        float $confidence = 0.9,
    ): array {
        return [
            'is_music' => $isMusic,
            'category' => $isMusic ? $category : null,
            'detail' => $isMusic ? $detail : null,
            'confidence' => $confidence,
        ];
    }

    private function content(
        ?ContentKind $kind,
        ?ContentCategory $category,
        ?string $detail,
    ): Content {
        $content = new Content;
        $content->content_type = $kind;
        $content->content_category = $category;
        $content->content_category_detail = $detail;

        return $content;
    }
}

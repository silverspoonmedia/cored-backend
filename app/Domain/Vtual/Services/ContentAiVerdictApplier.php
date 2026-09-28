<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Models\Content;

/**
 * Apply an AI audit verdict to a content row, honouring the program pass.
 *
 * The program classifier runs first and its verdict **binds**: the AI pass is
 * the fallback for the layers the program pass could not decide, not a second
 * opinion on the layers it did. `ContentAiClassifier` states this to the model in
 * its system prompt, and this class is where the same rule is enforced on the
 * write — a prompt that promises a contract the code does not keep is worse than
 * no prompt at all.
 *
 * Concretely, per layer:
 *
 * - `content_type` is only written when it is still null. A row the program pass
 *   called music stays music even if the model answers "not music" (strong
 *   keyword evidence such as 歌ってみた outweighs a model disagreement).
 * - `content_category` is only written when it is still null, so a sub-category
 *   the program pass resolved is never replaced.
 * - `content_category_detail` is attribution text for the *same* sub-category, so
 *   it may be filled in when the program pass left it empty without contradicting
 *   anything. It is never allowed to describe a different sub-category.
 *
 * Used by both AI entry points (`vtual:audit-content-ai` and
 * `vtual:classify-contents --ai`) so they cannot drift apart.
 */
class ContentAiVerdictApplier
{
    /**
     * @param  array{is_music: bool, category: ?ContentCategory, detail: ?string, confidence: float}  $verdict
     * @return array{kind: ?ContentKind, category: ?ContentCategory, changed: bool}
     *                                                                              `kind`/`category` are the layers actually written, or null when the
     *                                                                              program pass already owned them.
     */
    public function apply(Content $content, array $verdict): array
    {
        $changed = false;
        $wroteKind = null;
        $wroteCategory = null;

        if ($verdict['is_music']) {
            if ($content->content_type === null) {
                $content->content_type = ContentKind::Music;
                $wroteKind = ContentKind::Music;
                $changed = true;
            }

            if ($content->content_category === null && $verdict['category'] !== null) {
                $content->content_category = $verdict['category'];
                $wroteCategory = $verdict['category'];
                $changed = true;
            }

            // Detail belongs to a cover attribution. Only fill it when it would
            // describe the sub-category the row actually carries.
            $detail = $verdict['detail'];
            if ($detail !== null
                && $detail !== ''
                && $content->content_category_detail === null
                && $content->content_category === $verdict['category']
            ) {
                $content->content_category_detail = $detail;
                $changed = true;
            }

            return ['kind' => $wroteKind, 'category' => $wroteCategory, 'changed' => $changed];
        }

        // A "not music" answer may only stand where the program pass abstained.
        // Where the program pass already said music, its verdict wins and the row
        // keeps its layer — the audit item is still closed by the caller so it is
        // not retried forever.
        if ($content->content_type === null) {
            $content->content_type = ContentKind::NonMusic;
            $content->content_category = null;
            $content->content_category_detail = null;
            $changed = true;
            $wroteKind = ContentKind::NonMusic;
        }

        return ['kind' => $wroteKind, 'category' => $wroteCategory, 'changed' => $changed];
    }
}

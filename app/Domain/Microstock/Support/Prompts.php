<?php

namespace App\Domain\Microstock\Support;

/**
 * Hardcoded AI prompts (English) for microstock metadata, aligned to Adobe
 * Stock / Shutterstock 2026 contributor standards.
 *
 * Stage 1 (vision): analyze the asset image and draft title + description +
 * category + a 4-tier keyword set (literal / contextual / conceptual /
 * stylistic) with the top-10 carrying the most search weight.
 * Stage 2 (thinking): curate the draft against an explicit checklist — dedupe,
 * drop generic/spam, enforce ordering, inject missing concepts, validate the
 * category, and tighten the title — into the final metadata set.
 */
class Prompts
{
    /**
     * Shared marketplace rules injected into both stages so they never drift.
     * Kept dense: some gateway models choke (524 timeout) on long prompts when
     * an image is attached, so every word here earns its place.
     */
    private static function marketplaceRules(): string
    {
        return <<<'PROMPT'
RULES (Adobe Stock / Shutterstock):
- Title: natural sentence, 40-70 chars, no commas/trademarks/subjective adjectives. Cartoon → subject + action, not clothing list.
- Description: one factual sentence (who/what/action).
- Category: ONE of People, Lifestyle, Education, Business, Food, Nature, Technology, Health, Holidays, Abstract, Objects, Illustration (cartoon/vector → Illustration).
- Keywords: 15-30, ordered literal > contextual > conceptual > stylistic (max 2). Top 10 = literal+contextual. Singular nouns, no stem repeats, visible only.
- BAN: front view, full length, white background, isolated, cute, beautiful, palette spam, clip art if illustration present.
- With collection context, top-5 keywords reflect that theme when visually supported.
PROMPT;
    }

    public static function visionSystem(): string
    {
        return 'You are a microstock metadata expert. Analyze the stock asset image and produce search-optimized metadata.'
            ."\n\n".self::marketplaceRules()
            ."\n\n".'Think like a buyer: capture what the subject REPRESENTS and its commercial USE, not just what it is.'
            ."\n".'Respond ONLY with a JSON object: {"title": string, "description": string, "category": string, "keywords": string[]}';
    }

    public static function visionUser(?string $hint = null): string
    {
        $prompt = 'Analyze this stock asset and return the JSON metadata. Ground every title, description, and keyword'
            .' in the main subject actually visible; do not invent keywords for content that is not present.'
            .' Do not add sports equipment, venues, or activities unless clearly visible — collection context may shape keywords only when the image supports them.';

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            if (str_contains($hint, "\n") || str_starts_with($hint, 'PRIMARY CONCEPT:')) {
                $prompt .= "\n\n## COLLECTION CONTEXT (commercial ground truth)\n".$hint
                    ."\n— bias the conceptual keywords, category, and title framing toward this intent,"
                    .' without overriding what is visible.';
            } else {
                $prompt .= "\n\n## COLLECTION CONTEXT (commercial ground truth)\n\"{$hint}\""
                    .' — bias the conceptual keywords, category, and title framing toward this intent,'
                    .' without overriding what is visible.';
            }
        }

        return $prompt;
    }

    public static function thinkingSystem(): string
    {
        return 'Microstock SEO curator. Checklist: '
            .'0) VERIFY draft vs image — fix subject/gender/action/gear/category; drop invisible terms. '
            .'1) TITLE natural 40-70 chars. 2) DEDUPE same-stem. 3) BAN list below. '
            .'4) INJECT hint concept top-5 when supported. 5) ORDER top10 literal+context; concept; max 2 stylistic. '
            .'6) Final 15-30 keywords, valid category.'
            ."\n\n".self::marketplaceRules()
            ."\n\n".'JSON only: {"title": string, "description": string, "category": string, "keywords": string[]}';
    }

    public static function thinkingUser(
        string $title,
        array $keywords,
        ?string $hint = null,
        string $description = '',
        string $category = '',
        bool $withImage = false,
    ): string {
        $keywordsJson = json_encode(array_values($keywords));

        $prompt = $withImage
            ? "Image attached — verify the draft against what you see.\n\n"
            : '';

        $prompt .= <<<PROMPT
DRAFT METADATA
title: {$title}
description: {$description}
category: {$category}
keywords: {$keywordsJson}
PROMPT;

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= <<<PROMPT


COLLECTION CONTEXT (commercial ground truth)
{$hint}
PROMPT;
        }

        return $prompt."\n\nCURATE the draft per the checklist and return the final JSON metadata.";
    }

    public static function batchSystem(): string
    {
        return 'You are a microstock metadata expert. You are given MULTIPLE stock asset images. '
            .'Each image is preceded by a line "ASSET <id>:" identifying which asset it belongs to. '
            .'For EVERY asset, produce search-optimized metadata applying these rules per asset:'
            ."\n\n".self::marketplaceRules()
            ."\n\n".'Respond ONLY with a JSON object wrapping one entry per asset, in the same order: '
            .'{"assets": [{"id": "<id>", "title": string, "description": string, "category": string, "keywords": string[]}, ...]} '
            .'Use the exact "id" that preceded each image. Do not skip any asset.';
    }

    public static function batchUser(?string $hint = null): string
    {
        $prompt = <<<'PROMPT'
Analyze every stock asset above and return the JSON object with an "assets"
array of metadata, one object per asset id. Ground each title, description,
and keyword set in the
main subject actually visible in that asset; do not invent generic keywords for
content that is not present. Do not add sports equipment or venues unless
clearly visible in that specific image.
PROMPT;

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= <<<PROMPT


## COLLECTION CONTEXT (commercial ground truth)
The asset owner describes this collection as: "{$hint}".
Treat this as the collection's commercial intent and use it to shape every
title, description, category, and keyword set — without overriding what is
actually visible in each image.
PROMPT;
        }

        return $prompt;
    }
}

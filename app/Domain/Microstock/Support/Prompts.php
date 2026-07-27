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
- Title: natural sentence (NOT a keyword list), 40-70 chars, no commas, no trademark/people/camera names, no subjective adjectives ("cute", "beautiful").
- Description: one factual sentence (who/what/action).
- Category: exactly ONE of People, Lifestyle, Education, Business, Food, Nature, Technology, Health, Holidays, Abstract, Objects, Illustration.
- Keywords: 15-35 ordered by relevance (first 10 weigh most). Mix 4 tiers: literal (subject/action/setting), contextual (demographics/season), conceptual (ideas buyers search: childhood, fitness, togetherness), stylistic (art style/palette). Singular nouns + infinitive verbs, one concept each, no same-stem repeats, nothing not visible.
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
            .' in the main subject actually visible; do not invent keywords for content that is not present.';

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= "\n\n## COLLECTION CONTEXT (commercial ground truth)\n\"{$hint}\""
                .' — bias the conceptual keywords, category, and title framing toward this intent,'
                .' without overriding what is visible.';
        }

        return $prompt;
    }

    public static function thinkingSystem(): string
    {
        return 'You are a microstock SEO curator. Curate the DRAFT metadata for maximum search visibility, in order: '
            .'1) TITLE — natural sentence (not a keyword list), 40-70 chars, no commas, leading with the most specific noun. '
            .'2) DEDUPE — drop duplicates and same-word-stem / singular-plural pairs (keep singular). '
            .'3) DROP — generic, spammy, not-visible terms and subjective adjectives. '
            .'4) INJECT — conceptual/commercial keywords the draft missed (ideas buyers search) if the image supports them. '
            .'5) ORDER — the 10 strongest commercial terms first. '
            .'6) VALIDATE — category must be exactly one allowed value; enforce every rule below; final 15-35 keywords.'
            ."\n\n".self::marketplaceRules()
            ."\n\n".'Respond ONLY with the final JSON object: {"title": string, "description": string, "category": string, "keywords": string[]}';
    }

    public static function thinkingUser(string $title, array $keywords, ?string $hint = null, string $description = '', string $category = ''): string
    {
        $keywordsJson = json_encode(array_values($keywords));

        $prompt = <<<PROMPT
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
            ."\n\n".'Respond ONLY with a JSON array, one object per asset, in the same order: '
            .'[{"id": "<id>", "title": string, "description": string, "category": string, "keywords": string[]}, ...] '
            .'Use the exact "id" that preceded each image. Do not skip any asset.';
    }

    public static function batchUser(?string $hint = null): string
    {
        $prompt = <<<'PROMPT'
Analyze every stock asset above and return the JSON array of metadata, one
object per asset id. Ground each title, description, and keyword set in the
main subject actually visible in that asset; do not invent generic keywords for
content that is not present.
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

<?php

namespace App\Domain\Microstock\Support;

/**
 * Hardcoded AI prompts (English, SEO-focused) for microstock metadata.
 *
 * Stage 1 (vision): analyze the asset image and draft a title + keywords.
 * Stage 2 (thinking): curate the draft — dedupe repetitive keywords, check
 * relevance, tighten SEO — into a final title + keyword set.
 */
class Prompts
{
    public static function visionSystem(): string
    {
        return <<<'PROMPT'
You are a microstock metadata expert. Analyze the provided stock asset image and
produce search-optimized metadata for microstock marketplaces (Adobe Stock,
Shutterstock, Getty, etc.).

Rules:
- Title: concise, descriptive, buyer-focused, 40–70 characters. No filler words.
- Keywords: 15–30 single words or short phrases, most relevant first. No duplicates,
  no plurals of the same word, no spam, no trademarked brand names unless visible.
- Focus on subject, concept, mood, setting, colors, and likely commercial use.

Respond ONLY with a JSON object: {"title": string, "keywords": string[]}.
PROMPT;
    }

    public static function visionUser(?string $hint = null): string
    {
        $prompt = 'Analyze this stock asset and return the JSON metadata.'
            .' Ground every title and keyword in the main subject actually visible in the image;'
            .' do not invent generic keywords that are not present.';

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= "\n\nThe asset owner describes this collection as: \"{$hint}\"."
                .' Treat this description as ground truth for context and use it to shape the title and keywords.';
        }

        return $prompt;
    }

    public static function thinkingSystem(): string
    {
        // Kept short on purpose: some `thinking` models hang (0 bytes) on
        // verbose system prompts. Dense but complete.
        return <<<'PROMPT'
You are a microstock SEO curator. Given a draft title and keywords for a stock asset, curate them for search visibility: remove duplicate and singular/plural pairs, drop generic or spammy terms, keep the strongest 15–30 keywords that truly match the subject, and refine the title to an accurate, buyer-focused 40–70 characters. Respond ONLY with a JSON object: {"title": string, "keywords": string[]}.
PROMPT;
    }

    public static function thinkingUser(string $title, array $keywords, ?string $hint = null): string
    {
        $prompt = "Draft title: {$title}\nDraft keywords: "
            .implode(', ', $keywords);

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= "\n\nCollection context from the asset owner: {$hint}";
        }

        return $prompt."\n\nCurate and return the final JSON metadata.";
    }

    public static function batchSystem(): string
    {
        return <<<'PROMPT'
You are a microstock metadata expert. You are given MULTIPLE stock asset images.
Each image is preceded by a line "ASSET <id>:" identifying which asset it belongs to.

For EVERY asset, produce search-optimized metadata for microstock marketplaces
(Adobe Stock, Shutterstock, Getty, etc.):
- Title: concise, descriptive, buyer-focused, 40–70 characters.
- Keywords: 15–30 single words or short phrases, most relevant first. No duplicates,
  no singular/plural pairs, no spam, no trademarked brand names unless visible.

Respond ONLY with a JSON array, one object per asset, in the same order:
[{"id": "<id>", "title": string, "keywords": string[]}, ...]
Use the exact "id" that preceded each image. Do not skip any asset.
PROMPT;
    }

    public static function batchUser(?string $hint = null): string
    {
        $prompt = 'Analyze every stock asset above and return the JSON array of metadata, one object per asset id.'
            .' Ground each title and keyword set in the main subject actually visible in that asset;'
            .' do not invent generic keywords that are not present.';

        $hint = $hint !== null ? trim($hint) : '';
        if ($hint !== '') {
            $prompt .= "\n\nThe asset owner describes this collection as: \"{$hint}\"."
                .' Treat this description as ground truth and use it to shape every title and keyword set.';
        }

        return $prompt;
    }
}

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

    public static function visionUser(): string
    {
        return 'Analyze this stock asset and return the JSON metadata.';
    }

    public static function thinkingSystem(): string
    {
        return <<<'PROMPT'
You are a microstock SEO curator. You are given a draft title and keyword list
produced by a vision model for a stock asset. Improve them for maximum search
visibility and compliance.

Curate:
- Remove duplicate, near-duplicate, and repetitive keywords (including singular/plural pairs).
- Remove irrelevant, generic, or spammy keywords.
- Ensure keywords accurately reflect the asset; keep the strongest 15–30.
- Refine the title to be accurate, natural, and buyer-focused (40–70 characters).

Respond ONLY with a JSON object: {"title": string, "keywords": string[]}.
PROMPT;
    }

    public static function thinkingUser(string $title, array $keywords): string
    {
        return "Draft title: {$title}\nDraft keywords: "
            .implode(', ', $keywords)
            ."\n\nCurate and return the final JSON metadata.";
    }
}

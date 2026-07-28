import { describe, expect, it } from 'vitest';
import { normalizeKeywords, parseKeywordInput } from './microstock-panel.js';

describe('microstock-panel keyword helpers', () => {
    it('normalizeKeywords trims, drops empties, dedupes case-insensitively', () => {
        expect(normalizeKeywords([' eco ', 'Kitchen', 'ECO', '', '  ', 'kitchen']))
            .toEqual(['eco', 'Kitchen']);
    });

    it('normalizeKeywords handles non-array input', () => {
        expect(normalizeKeywords(null)).toEqual([]);
        expect(normalizeKeywords('x')).toEqual([]);
    });

    it('parseKeywordInput splits on comma and newline', () => {
        expect(parseKeywordInput('a, b\nc,,d')).toEqual(['a', 'b', 'c', 'd']);
    });

    it('parseKeywordInput merges cleanly with normalize for existing list', () => {
        const existing = normalizeKeywords(['eco']);
        const merged = normalizeKeywords(existing.concat(parseKeywordInput('eco, green')));
        expect(merged).toEqual(['eco', 'green']);
    });
});

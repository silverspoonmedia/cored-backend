import { describe, expect, it } from 'vitest';
import { normalizeKeywords, parseKeywordInput, renderStageStripHtml } from './microstock-panel.js';
import { stageStripItems } from './microstock-ui.js';

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

describe('renderStageStripHtml', () => {
    it('renders visible text labels next to stage icons', () => {
        const items = stageStripItems({ vision: 'ok', thinking: 'running', result: 'pending' }, {
            stageVisionLabel: 'Vision',
            stageThinkingLabel: 'Thinking',
            stageResultLabel: 'Result',
        });
        const html = renderStageStripHtml(items);

        expect(html).toContain('Vision');
        expect(html).toContain('Thinking');
        expect(html).toContain('Result');
        expect(html).toContain('fh-ms-stage-label');
        expect(html).toContain('aria-label="Vision ok"');
    });

    it('escapes label text in rendered HTML', () => {
        const items = stageStripItems({ vision: 'ok' }, { stageVisionLabel: '<script>' });
        const html = renderStageStripHtml(items);

        expect(html).toContain('&lt;script&gt;');
        expect(html).not.toContain('<script>');
    });
});

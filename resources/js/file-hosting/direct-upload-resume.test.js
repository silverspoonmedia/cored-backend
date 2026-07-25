import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
    computeContentHash,
    resumeHint,
    selectMissingPartNumbers,
} from './direct-upload.js';

describe('selectMissingPartNumbers', () => {
    it('returns all parts when nothing recorded', () => {
        expect(selectMissingPartNumbers(3, [])).toEqual([1, 2, 3]);
    });

    it('skips already-recorded parts (resume)', () => {
        expect(selectMissingPartNumbers(4, [{ part_number: 1 }, { part_number: 3 }])).toEqual([2, 4]);
    });

    it('returns empty when everything is recorded', () => {
        expect(selectMissingPartNumbers(2, [{ part_number: 1 }, { part_number: 2 }])).toEqual([]);
    });

    it('coerces string part numbers', () => {
        expect(selectMissingPartNumbers(3, [{ part_number: '2' }])).toEqual([1, 3]);
    });
});

describe('computeContentHash', () => {
    it('produces a stable sha256 hex digest for the same bytes', async () => {
        const a = new Blob(['hello world']);
        const b = new Blob(['hello world']);
        const c = new Blob(['different']);

        const ha = await computeContentHash(a);
        const hb = await computeContentHash(b);
        const hc = await computeContentHash(c);

        expect(ha).toMatch(/^[0-9a-f]{64}$/);
        expect(ha).toBe(hb);
        expect(ha).not.toBe(hc);
    });

    it('returns null when the blob cannot be hashed', async () => {
        // No arrayBuffer() -> feature-detect bails out (resume disabled).
        expect(await computeContentHash({})).toBeNull();
    });
});

describe('resumeHint', () => {
    beforeEach(() => {
        const store = new Map();
        globalThis.localStorage = {
            getItem: (k) => (store.has(k) ? store.get(k) : null),
            setItem: (k, v) => store.set(k, String(v)),
            removeItem: (k) => store.delete(k),
        };
    });

    afterEach(() => {
        delete globalThis.localStorage;
    });

    it('round-trips an upload id keyed by content hash', () => {
        resumeHint.set('abc', 'upload-1');
        expect(resumeHint.get('abc')).toBe('upload-1');
    });

    it('clears the hint', () => {
        resumeHint.set('abc', 'upload-1');
        resumeHint.clear('abc');
        expect(resumeHint.get('abc')).toBeNull();
    });

    it('is a no-op without a hash', () => {
        resumeHint.set('', 'upload-1');
        expect(resumeHint.get('')).toBeNull();
    });
});

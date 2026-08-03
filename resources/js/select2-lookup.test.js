import { describe, expect, it } from 'vitest';
import { processLookupResults } from './select2-lookup.js';

describe('processLookupResults', () => {
    it('unwraps Monolith API envelope', () => {
        expect(processLookupResults({
            status: 'success',
            message: 'ok',
            data: {
                results: [{ id: '1', text: 'Menu A' }],
                pagination: { more: true },
            },
        })).toEqual({
            results: [{ id: '1', text: 'Menu A' }],
            pagination: { more: true },
        });
    });

    it('accepts already-unwrapped payload', () => {
        expect(processLookupResults({
            results: [{ id: '2', text: 'Menu B' }],
            pagination: { more: false },
        })).toEqual({
            results: [{ id: '2', text: 'Menu B' }],
            pagination: { more: false },
        });
    });

    it('tolerates null/invalid input', () => {
        expect(processLookupResults(null)).toEqual({
            results: [],
            pagination: { more: false },
        });
        expect(processLookupResults({ data: null })).toEqual({
            results: [],
            pagination: { more: false },
        });
    });
});

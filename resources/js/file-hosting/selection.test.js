import { describe, expect, it } from 'vitest';
import { applyBrowseClick, listingKeys, rangeKeys } from './selection.js';

describe('listingKeys', () => {
    it('orders folders then files', () => {
        expect(listingKeys([{ id: 'a' }], [{ id: 'b' }, { id: 'c' }])).toEqual([
            'folder:a',
            'file:b',
            'file:c',
        ]);
    });
});

describe('rangeKeys', () => {
    const ordered = ['folder:a', 'file:b', 'file:c', 'file:d'];

    it('selects inclusive range', () => {
        expect(rangeKeys(ordered, 'file:b', 'file:d')).toEqual(['file:b', 'file:c', 'file:d']);
        expect(rangeKeys(ordered, 'file:d', 'file:b')).toEqual(['file:b', 'file:c', 'file:d']);
    });

    it('falls back when anchor missing', () => {
        expect(rangeKeys(ordered, null, 'file:c')).toEqual(['file:c']);
        expect(rangeKeys(ordered, 'missing', 'file:c')).toEqual(['file:c']);
    });
});

describe('applyBrowseClick', () => {
    const ordered = ['folder:a', 'file:b', 'file:c'];

    it('plain click selects only that key', () => {
        const { next, anchorKey } = applyBrowseClick({
            key: 'file:b',
            event: {},
            selected: new Set(['folder:a']),
            anchorKey: 'folder:a',
            orderedKeys: ordered,
        });
        expect([...next]).toEqual(['file:b']);
        expect(anchorKey).toBe('file:b');
    });

    it('ctrl toggles', () => {
        const { next } = applyBrowseClick({
            key: 'file:b',
            event: { ctrlKey: true },
            selected: new Set(['folder:a']),
            anchorKey: 'folder:a',
            orderedKeys: ordered,
        });
        expect(next.has('folder:a')).toBe(true);
        expect(next.has('file:b')).toBe(true);
    });

    it('shift ranges from anchor', () => {
        const { next, anchorKey } = applyBrowseClick({
            key: 'file:c',
            event: { shiftKey: true },
            selected: new Set(['folder:a']),
            anchorKey: 'folder:a',
            orderedKeys: ordered,
        });
        expect([...next]).toEqual(['folder:a', 'file:b', 'file:c']);
        expect(anchorKey).toBe('folder:a');
    });
});

import { describe, expect, it } from 'vitest';
import {
    HINT_MAX_LENGTH,
    composeCollectionHint,
    emptyDescribeSections,
    parseCollectionHint,
    validateCollectionDescribe,
} from './collection-describe.js';

const labelMaps = {
    visible: {
        person: 'Person',
        object: 'Object',
        food: 'Food',
    },
    intent: {
        business: 'Business',
        education: 'Education',
        health: 'Health',
    },
    style: {
        photo: 'Photo',
    },
};

const sampleSections = {
    primaryConcept: 'Eco-friendly kitchen lifestyle set for stock buyers.',
    visibleSelected: ['person', 'object', 'food'],
    visibleOther: '',
    intentSelected: ['business', 'education', 'health'],
    intentOther: '',
    styleSelected: ['photo'],
    styleOther: 'natural light, warm tones',
    additionalNotes: 'Wooden table, bright daylight, no logos.',
};

describe('composeCollectionHint', () => {
    it('builds structured multi-section hint', () => {
        const hint = composeCollectionHint(sampleSections, labelMaps);
        expect(hint).toContain('PRIMARY CONCEPT: Eco-friendly kitchen');
        expect(hint).toContain('VISIBLE SUBJECTS: Person, Object, Food');
        expect(hint).toContain('BUYER INTENT: Business, Education, Health');
        expect(hint).toContain('STYLE AND MOOD: Photo, natural light, warm tones');
        expect(hint).toContain('ADDITIONAL NOTES: Wooden table');
    });
});

describe('parseCollectionHint', () => {
    it('round-trips composed hint', () => {
        const hint = composeCollectionHint(sampleSections, labelMaps);
        const parsed = parseCollectionHint(hint, labelMaps);
        expect(parsed.primaryConcept).toBe(sampleSections.primaryConcept);
        expect(parsed.visibleSelected).toEqual(sampleSections.visibleSelected);
        expect(parsed.intentSelected).toEqual(sampleSections.intentSelected);
        expect(parsed.styleSelected).toEqual(['photo']);
        expect(parsed.styleOther).toBe('natural light, warm tones');
        expect(parsed.additionalNotes).toBe(sampleSections.additionalNotes);
    });

    it('falls back plain text to primary concept', () => {
        const parsed = parseCollectionHint('eco kitchen flat-lays', labelMaps);
        expect(parsed.primaryConcept).toBe('eco kitchen flat-lays');
        expect(parsed.visibleSelected).toEqual([]);
    });

    it('returns empty sections for blank input', () => {
        expect(parseCollectionHint('', labelMaps)).toEqual(emptyDescribeSections());
    });
});

describe('validateCollectionDescribe', () => {
    it('requires primary concept and each chip group', () => {
        const result = validateCollectionDescribe(emptyDescribeSections());
        expect(result.valid).toBe(false);
        expect(result.errors).toContain('primaryConcept');
        expect(result.errors).toContain('visible');
        expect(result.errors).toContain('intent');
        expect(result.errors).toContain('style');
    });

    it('accepts other text instead of chips', () => {
        const result = validateCollectionDescribe({
            ...emptyDescribeSections(),
            primaryConcept: 'Long enough concept here',
            visibleOther: 'Custom subject',
            intentOther: 'Custom intent',
            styleOther: 'Custom style',
        });
        expect(result.valid).toBe(true);
    });

    it('exports hint max length constant', () => {
        expect(HINT_MAX_LENGTH).toBe(2000);
    });
});

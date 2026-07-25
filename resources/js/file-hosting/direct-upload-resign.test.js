import { describe, expect, it } from 'vitest';
import { isAuthExpired } from './direct-upload.js';

describe('isAuthExpired (re-sign-on-expiry trigger)', () => {
    it('matches a 403 status failure from putPart', () => {
        expect(isAuthExpired(new Error('Storage PUT failed with status 403'))).toBe(true);
    });

    it('matches S3 expiry wording', () => {
        expect(isAuthExpired(new Error('SignatureDoesNotMatch'))).toBe(true);
        expect(isAuthExpired(new Error('Request has expired'))).toBe(true);
    });

    it('does not match unrelated failures', () => {
        expect(isAuthExpired(new Error('Storage PUT failed with status 500'))).toBe(false);
        expect(isAuthExpired(new Error('Storage PUT network error'))).toBe(false);
        expect(isAuthExpired(new Error('Missing ETag header from storage response'))).toBe(false);
    });

    it('tolerates a missing message', () => {
        expect(isAuthExpired(null)).toBe(false);
        expect(isAuthExpired({})).toBe(false);
    });
});

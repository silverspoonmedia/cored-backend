import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { abortUploadSession, resumeHint } from './direct-upload.js';

describe('abortUploadSession', () => {
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

    it('issues a DELETE to the abort url', async () => {
        const apiJson = vi.fn().mockResolvedValue({});
        await abortUploadSession({ abortUrl: '/uploads/abc-123', apiJson });

        expect(apiJson).toHaveBeenCalledWith('/uploads/abc-123', 'DELETE');
    });

    it('swallows a failed abort (never rejects)', async () => {
        const apiJson = vi.fn().mockRejectedValue(new Error('network down'));
        await expect(abortUploadSession({ abortUrl: '/uploads/abc', apiJson })).resolves.toBeUndefined();
    });

    it('is a no-op when no abort url is configured', async () => {
        const apiJson = vi.fn();
        await abortUploadSession({ abortUrl: '', apiJson });
        expect(apiJson).not.toHaveBeenCalled();
    });

    it('composes with resumeHint.clear on the failure path', async () => {
        resumeHint.set('hash-1', 'upload-1');
        expect(resumeHint.get('hash-1')).toBe('upload-1');

        await abortUploadSession({ abortUrl: '/uploads/upload-1', apiJson: () => Promise.resolve({}) });
        resumeHint.clear('hash-1');

        expect(resumeHint.get('hash-1')).toBeNull();
    });
});

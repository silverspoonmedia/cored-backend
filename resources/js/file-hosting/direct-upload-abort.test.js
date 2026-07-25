import { describe, expect, it, vi } from 'vitest';
import { abortUploadSession } from './direct-upload.js';

describe('abortUploadSession', () => {
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
});

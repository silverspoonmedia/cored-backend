import { describe, expect, it } from 'vitest';
import { buildInitContext } from './direct-upload.js';

describe('buildInitContext', () => {
    it('returns empty object when nothing is set (root upload)', () => {
        expect(buildInitContext({})).toEqual({});
    });

    it('omits keys for empty-string values (attribute absent reads as "")', () => {
        expect(buildInitContext({ folderId: '', userId: '', workspaceId: '' })).toEqual({});
    });

    it('maps folderId to folder_id', () => {
        expect(buildInitContext({ folderId: 'abc' })).toEqual({ folder_id: 'abc' });
    });

    it('maps user and workspace context', () => {
        expect(buildInitContext({ userId: 'u1', workspaceId: 'w1' })).toEqual({
            user_id: 'u1',
            workspace_id: 'w1',
        });
    });

    it('reads live: a folder set after init is picked up on next call', () => {
        // Emulate the dropzone dataset the SPA router mutates in place.
        const dataset = {};
        expect(buildInitContext(dataset)).toEqual({});

        dataset.folderId = 'folder-x';
        expect(buildInitContext(dataset)).toEqual({ folder_id: 'folder-x' });

        // Navigating back to root removes the attribute -> reads as null again.
        delete dataset.folderId;
        expect(buildInitContext(dataset)).toEqual({});
    });

    it('tolerates a null/undefined dataset', () => {
        expect(buildInitContext(null)).toEqual({});
        expect(buildInitContext(undefined)).toEqual({});
    });
});

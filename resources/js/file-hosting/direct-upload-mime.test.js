import { describe, expect, it } from 'vitest';
import {
    isRetriablePutError,
    isTooManyConcurrent,
    resolveMimeType,
    snapshotBatchContext,
} from './direct-upload.js';

describe('resolveMimeType', () => {
    it('uses declared type when present', () => {
        expect(resolveMimeType({ name: 'photo.jpg', type: 'image/jpeg' })).toBe('image/jpeg');
    });

    it('sniffs from extension when type is empty', () => {
        expect(resolveMimeType({ name: 'photo.JPG', type: '' })).toBe('image/jpeg');
        expect(resolveMimeType({ name: 'x.png', type: '' })).toBe('image/png');
    });

    it('falls back to octet-stream for unknown extensions', () => {
        expect(resolveMimeType({ name: 'raw.cr2', type: '' })).toBe('application/octet-stream');
    });
});

describe('snapshotBatchContext', () => {
    it('freezes folder and microstock flags at batch start', () => {
        const dataset = {
            folderId: 'folder-a',
            workspaceId: 'ws-1',
            workspaceKind: 'microstock',
            collectionLocked: '0',
        };
        const snapshot = snapshotBatchContext(dataset);

        dataset.folderId = 'folder-b';
        dataset.collectionLocked = '1';

        expect(snapshot.init).toEqual({ folder_id: 'folder-a', workspace_id: 'ws-1' });
        expect(snapshot.workspaceKind).toBe('microstock');
        expect(snapshot.collectionLocked).toBe(false);
    });
});

describe('isTooManyConcurrent', () => {
    it('matches server message', () => {
        expect(isTooManyConcurrent(new Error('Too many concurrent uploads. Finish or abort an existing session first.'))).toBe(true);
        expect(isTooManyConcurrent(new Error('quota exceeded'))).toBe(false);
    });
});

describe('isRetriablePutError', () => {
    it('matches network and 5xx errors', () => {
        expect(isRetriablePutError(new Error('Storage PUT network error'))).toBe(true);
        expect(isRetriablePutError(new Error('Storage PUT failed with status 520'))).toBe(true);
        expect(isRetriablePutError(new Error('Storage PUT failed with status 403'))).toBe(true);
        expect(isRetriablePutError(new Error('Missing ETag header from storage response'))).toBe(false);
    });
});

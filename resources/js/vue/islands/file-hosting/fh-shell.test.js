import { describe, expect, it } from 'vitest';
import { browseUrlWithContext } from '../../../file-hosting/realtime.js';
import { canDropOnFolder } from '../../../file-hosting/drag-move.js';
import { resolveFhBase, joinFhPath, parseFhUrl, isFhInternalUrl } from '../../../file-hosting/router.js';
import { unwrapData } from '../../composables/useApi.js';
import { hasBrowseChrome } from './composables/useFhShell.js';

describe('FH Vue SPA helpers', () => {
    it('resolveFhBase prefers config.fhBase', () => {
        expect(resolveFhBase({ fhBase: '/apps/file-hosting/' })).toBe('/apps/file-hosting');
    });

    it('joinFhPath fails closed on empty base', () => {
        expect(joinFhPath('', 'files', 'x')).toBe('');
        expect(joinFhPath('/apps/file-hosting', 'files', 'abc')).toBe('/apps/file-hosting/files/abc');
    });

    it('browseUrlWithContext sets folder/workspace query', () => {
        const url = browseUrlWithContext('/apps/file-hosting', {
            currentFolderId: 'fold-1',
            workspaceId: 'ws-1',
            ownerUserId: null,
        });
        const u = new URL(url, 'http://localhost');
        expect(u.searchParams.get('folder_id')).toBe('fold-1');
        expect(u.searchParams.get('workspace_id')).toBe('ws-1');
    });

    it('parseFhUrl detects browse trash show', () => {
        expect(parseFhUrl('http://localhost/apps/file-hosting').name).toBe('browse');
        expect(parseFhUrl('http://localhost/apps/file-hosting/trash').name).toBe('trash');
        expect(parseFhUrl('http://localhost/apps/file-hosting/files/019abcde-1234-7890-abcd-ef0123456789').name).toBe('show');
    });

    it('unwrapData peels Monolith envelope', () => {
        expect(unwrapData({ data: { folders: [] } })).toEqual({ folders: [] });
    });

    it('canDropOnFolder refuses self-drop', () => {
        expect(canDropOnFolder('abc', ['folder:abc'])).toBe(false);
        expect(canDropOnFolder('abc', ['file:x'])).toBe(true);
        expect(canDropOnFolder(null, ['folder:abc'])).toBe(true);
    });

    it('hasBrowseChrome is false on cold trash/show bootstrap', () => {
        expect(hasBrowseChrome({})).toBe(false);
        expect(hasBrowseChrome({ workspacesUrl: '' })).toBe(false);
        expect(hasBrowseChrome({ workspacesUrl: '/apps/file-hosting/workspaces' })).toBe(true);
    });

    it('cold browse URLs stay FH-internal but lack chrome for soft-nav gate', () => {
        expect(isFhInternalUrl('/apps/file-hosting')).toBe(true);
        expect(hasBrowseChrome({ trashUrl: '/apps/file-hosting/trash' })).toBe(false);
    });
});

import { fhFetch, fhConfirm, toast } from './useFhApi.js';
import { joinFhPath } from '../../../../file-hosting/router.js';
import { moveItemsRequest } from '../../../../file-hosting/drag-move.js';

/**
 * @param {ReturnType<import('./useFhShell.js').useFhShell>} shell
 */
export function useFhMutations(shell) {
    const { config, setBusy, softRefresh, softRefetchBrowse, clearSelection, joinFhPath: join } = shell;

    function entityBase(entity) {
        const fh = shell.resolveFhBase(config);
        return entity === 'folder'
            ? (config.folderBase || joinFhPath(fh, 'folders'))
            : (config.fileBase || joinFhPath(fh, 'files'));
    }

    function contextBody() {
        const body = {};
        if (config.ownerUserId) body.user_id = config.ownerUserId;
        if (config.workspaceId) body.workspace_id = config.workspaceId;
        return body;
    }

    async function jsonMutation(url, method, body = {}) {
        setBusy(true);
        try {
            const { ok, data, networkError } = await fhFetch(url, {
                method,
                json: { ...contextBody(), ...body },
            });
            if (networkError) {
                toast(config.messages?.networkError || 'Network error', 'danger');
                return false;
            }
            if (!ok) {
                toast(data.message || config.messages?.error || 'Error', 'danger');
                return false;
            }
            if (data.message) toast(data.message, 'success');
            await softRefresh();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function deleteEntity(entity, id) {
        const msgs = config.messages || {};
        const confirmMsg = entity === 'folder'
            ? (msgs.actions?.confirmDeleteFolder || msgs.confirmDeleteFolder)
            : (msgs.actions?.confirmDeleteFile || msgs.confirmDeleteFile);
        if (confirmMsg && !(await fhConfirm(confirmMsg))) return false;
        const url = joinFhPath(entityBase(entity), id);
        return jsonMutation(url, 'DELETE');
    }

    async function restoreEntity(entity, id) {
        const confirmMsg = config.messages?.confirmRestore;
        if (confirmMsg && !(await fhConfirm(confirmMsg))) return false;
        const url = joinFhPath(entityBase(entity), id, 'restore');
        return jsonMutation(url, 'POST');
    }

    async function forceDeleteEntity(entity, id) {
        const confirmMsg = config.messages?.confirmForceDelete;
        if (confirmMsg && !(await fhConfirm(confirmMsg))) return false;
        const url = joinFhPath(entityBase(entity), id, 'force');
        return jsonMutation(url, 'DELETE');
    }

    async function renameEntity(entity, id, name) {
        const url = joinFhPath(entityBase(entity), id, 'rename');
        const form = new FormData();
        form.append('_method', 'PATCH');
        form.append('name', name);
        if (config.ownerUserId) form.append('user_id', config.ownerUserId);
        if (config.workspaceId) form.append('workspace_id', config.workspaceId);
        setBusy(true);
        try {
            const { ok, data, networkError } = await fhFetch(url, { method: 'POST', body: form });
            if (networkError) {
                toast(config.messages?.networkError || 'Network error', 'danger');
                return false;
            }
            if (!ok) {
                toast(data.message || config.messages?.error || 'Error', 'danger');
                return false;
            }
            if (data.message) toast(data.message, 'success');
            await softRefresh();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function setVisibility(entity, id, visibility) {
        const url = joinFhPath(entityBase(entity), id, 'visibility');
        const form = new FormData();
        form.append('_method', 'PATCH');
        form.append('visibility', visibility);
        if (config.ownerUserId) form.append('user_id', config.ownerUserId);
        if (config.workspaceId) form.append('workspace_id', config.workspaceId);
        setBusy(true);
        try {
            const { ok, data, networkError } = await fhFetch(url, { method: 'POST', body: form });
            if (networkError) {
                toast(config.messages?.networkError || 'Network error', 'danger');
                return false;
            }
            if (!ok) {
                toast(data.message || config.messages?.error || 'Error', 'danger');
                return false;
            }
            if (data.message) toast(data.message, 'success');
            if (data.data?.share_url && visibility === (config.visLink || 'anyone_with_link')) {
                try {
                    await navigator.clipboard.writeText(data.data.share_url);
                    toast(config.messages?.linkCopied || 'Link copied', 'success');
                } catch {
                    /* ignore */
                }
            }
            await softRefresh();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function createFolder(name) {
        const fh = shell.resolveFhBase(config);
        const url = config.folderBase || joinFhPath(fh, 'folders');
        const form = new FormData();
        form.append('name', name);
        if (config.currentFolderId) form.append('parent_id', config.currentFolderId);
        if (config.ownerUserId) form.append('user_id', config.ownerUserId);
        if (config.workspaceId) form.append('workspace_id', config.workspaceId);
        setBusy(true);
        try {
            const { ok, data, networkError } = await fhFetch(url, { method: 'POST', body: form });
            if (networkError) {
                toast(config.messages?.networkError || 'Network error', 'danger');
                return false;
            }
            if (!ok) {
                toast(data.message || config.messages?.error || 'Error', 'danger');
                return false;
            }
            if (data.message) toast(data.message, 'success');
            await softRefetchBrowse();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function moveItems(keys, targetFolderId) {
        setBusy(true);
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const result = await moveItemsRequest({
                keys,
                targetFolderId: targetFolderId || null,
                config,
                csrfToken: csrf,
                parseKey(key) {
                    const [type, id] = String(key).split(':');
                    if (!type || !id) return null;
                    return { type, id };
                },
            });
            if (!result?.ok) {
                toast(result?.message || config.messages?.moveFailed || 'Move failed', 'danger');
                return false;
            }
            toast(result.message || config.messages?.moved || 'Moved', 'success');
            clearSelection();
            await softRefresh();
            return true;
        } catch (e) {
            toast(e?.message || config.messages?.moveFailed || 'Move failed', 'danger');
            return false;
        } finally {
            setBusy(false);
        }
    }

    async function bulkVisibility(idsByEntity, visibility) {
        const confirmMsg = config.messages?.bulkConfirmShare;
        if (visibility === (config.visLink || 'anyone_with_link') && confirmMsg && !(await fhConfirm(confirmMsg))) {
            return false;
        }
        setBusy(true);
        try {
            for (const [entity, ids] of Object.entries(idsByEntity)) {
                if (!ids?.length) continue;
                const url = entity === 'folder'
                    ? config.bulkFolderVisibilityUrl
                    : config.bulkFileVisibilityUrl;
                if (!url) continue;
                const { ok, data, networkError } = await fhFetch(url, {
                    method: 'POST',
                    json: { ids, visibility, ...contextBody() },
                });
                if (!ok || networkError) {
                    toast(data?.message || config.messages?.error || 'Error', 'danger');
                    return false;
                }
            }
            toast(config.messages?.success || 'OK', 'success');
            clearSelection();
            await softRefresh();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function bulkDelete(idsByEntity) {
        const confirmMsg = config.messages?.bulkConfirmDelete;
        if (confirmMsg && !(await fhConfirm(confirmMsg))) return false;
        setBusy(true);
        try {
            for (const [entity, ids] of Object.entries(idsByEntity)) {
                if (!ids?.length) continue;
                const url = entity === 'folder'
                    ? config.bulkFolderDeleteUrl
                    : config.bulkFileDeleteUrl;
                if (!url) continue;
                const { ok, data, networkError } = await fhFetch(url, {
                    method: 'DELETE',
                    json: { ids, ...contextBody() },
                });
                if (!ok || networkError) {
                    toast(data?.message || config.messages?.error || 'Error', 'danger');
                    return false;
                }
            }
            toast(config.messages?.success || 'OK', 'success');
            clearSelection();
            await softRefresh();
            return true;
        } finally {
            setBusy(false);
        }
    }

    async function search(q) {
        if (!config.searchUrl || !q) return [];
        const url = new URL(config.searchUrl, window.location.origin);
        url.searchParams.set('q', q);
        if (config.ownerUserId) url.searchParams.set('user_id', config.ownerUserId);
        if (config.workspaceId) url.searchParams.set('workspace_id', config.workspaceId);
        const { ok, payload } = await fhFetch(url.toString());
        if (!ok) return [];
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.results)) return payload.results;
        return [];
    }

    return {
        deleteEntity,
        restoreEntity,
        forceDeleteEntity,
        renameEntity,
        setVisibility,
        createFolder,
        moveItems,
        bulkVisibility,
        bulkDelete,
        search,
        entityBase,
    };
}

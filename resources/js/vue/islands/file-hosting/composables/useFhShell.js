import { ref, computed, reactive, watch } from 'vue';
import { createFhRouter, joinFhPath, parseFhUrl, resolveFhBase, isFhInternalUrl } from '../../../../file-hosting/router.js';
import { browseUrlWithContext } from '../../../../file-hosting/realtime.js';
import { fhFetch, toast } from './useFhApi.js';

const STORAGE_KEY = 'fh-view-mode';

/**
 * Cold trash/show boots omit browse chrome (workspacesUrl, upload, Blade modals).
 * Soft-nav to browse would keep stripped config — force full document load instead.
 *
 * @param {{ workspacesUrl?: string }|null|undefined} config
 * @returns {boolean}
 */
export function hasBrowseChrome(config) {
    return typeof config?.workspacesUrl === 'string' && config.workspacesUrl !== '';
}

/**
 * @param {object} bootstrap
 */
export function useFhShell(bootstrap) {
    const config = reactive({ ...(bootstrap.config || {}) });
    const panel = ref('browse');
    const loading = ref(false);
    const busyDepth = ref(0);
    const viewMode = ref(
        (typeof localStorage !== 'undefined' && localStorage.getItem(STORAGE_KEY)) || 'table',
    );
    const selectionAnchor = ref(null);

    const browse = ref(normalizeBrowse(bootstrap.browse));
    const trash = ref(normalizeTrash(bootstrap.trash));
    const showFile = ref(bootstrap.showFile || null);
    const quota = ref(bootstrap.quota || null);
    const workspaces = ref(Array.isArray(bootstrap.workspaces) ? bootstrap.workspaces : []);
    const workspaceMembers = ref(Array.isArray(bootstrap.workspaceMembers) ? bootstrap.workspaceMembers : []);
    const workspaceActivity = ref(Array.isArray(bootstrap.workspaceActivity) ? bootstrap.workspaceActivity : []);
    const currentWorkspace = ref(bootstrap.currentWorkspace || config.currentWorkspace || null);

    const selected = ref(new Set());
    const contextMenu = ref(null); // { x, y, entity, id, name }

    const isBusy = computed(() => busyDepth.value > 0);
    const isMicrostock = computed(
        () => (currentWorkspace.value?.kind || config.currentWorkspace?.kind) === 'microstock',
    );
    const fhBase = computed(() => resolveFhBase(config));

    const ownerQuery = computed(() => {
        const q = {};
        if (config.ownerUserId) q.user_id = config.ownerUserId;
        if (config.workspaceId) q.workspace_id = config.workspaceId;
        return q;
    });

    function setBusy(on) {
        busyDepth.value = Math.max(0, busyDepth.value + (on ? 1 : -1));
    }

    function setViewMode(mode) {
        viewMode.value = mode === 'grid' ? 'grid' : 'table';
        try {
            localStorage.setItem(STORAGE_KEY, viewMode.value);
        } catch {
            /* ignore */
        }
    }

    function clearSelection() {
        selected.value = new Set();
        selectionAnchor.value = null;
    }

    function toggleSelect(key, on) {
        const next = new Set(selected.value);
        if (on === undefined) {
            if (next.has(key)) next.delete(key);
            else next.add(key);
        } else if (on) {
            next.add(key);
        } else {
            next.delete(key);
        }
        selected.value = next;
        selectionAnchor.value = key;
    }

    function selectAll(keys, on) {
        selected.value = on ? new Set(keys) : new Set();
        selectionAnchor.value = on && keys.length ? keys[0] : null;
    }

    function setSelection(next, anchorKey = null) {
        selected.value = next instanceof Set ? next : new Set(next || []);
        selectionAnchor.value = anchorKey;
    }

    /**
     * Soft-nav only when browse chrome is already hydrated (warm index path).
     * Cold trash/show → browse must full-nav so Blade can inject upload/modals/workspaces.
     *
     * @param {string} href
     * @returns {boolean}
     */
    function shouldSoftNavigate(href) {
        if (!isFhInternalUrl(href)) {
            return false;
        }
        const route = parseFhUrl(href);
        if (route.name === 'browse' && !hasBrowseChrome(config)) {
            return false;
        }

        return true;
    }

    function syncConfigFromRoute(route) {
        config.currentFolderId = route.folderId || null;
        if (route.workspaceId !== undefined) {
            config.workspaceId = route.workspaceId;
        }
        if (route.userId !== undefined) {
            config.ownerUserId = route.userId;
        }
        if (config.realtime) {
            config.realtime.page = route.name === 'trash' ? 'trash' : route.name === 'show' ? 'show' : 'index';
            config.realtime.fileId = route.fileId || null;
        }
        if (config.browseUrl) {
            try {
                const u = new URL(config.browseUrl, window.location.origin);
                u.search = '';
                const base = u.pathname;
                const params = new URLSearchParams();
                if (config.ownerUserId) params.set('user_id', config.ownerUserId);
                if (config.workspaceId) params.set('workspace_id', config.workspaceId);
                config.browseUrl = params.toString() ? `${base}?${params}` : base;
            } catch {
                /* keep */
            }
        }
        if (config.trashUrl) {
            try {
                const u = new URL(config.trashUrl, window.location.origin);
                const params = new URLSearchParams();
                if (config.ownerUserId) params.set('user_id', config.ownerUserId);
                if (config.workspaceId) params.set('workspace_id', config.workspaceId);
                config.trashUrl = `${u.pathname}${params.toString() ? `?${params}` : ''}`;
            } catch {
                /* keep */
            }
        }
    }

    async function softRefetchBrowse() {
        const url = browseUrlWithContext(config.browseUrl || fhBase.value, config);
        const { ok, payload, data, networkError } = await fhFetch(url);
        if (!ok || networkError) {
            toast(data.message || config.messages?.loadFailed || 'Load failed', 'danger');
            return false;
        }
        browse.value = normalizeBrowse(payload);
        if (payload?.workspace) {
            currentWorkspace.value = payload.workspace;
            config.currentWorkspace = payload.workspace;
        }
        clearSelection();
        return true;
    }

    async function softRefetchTrash() {
        if (!config.trashUrl) return false;
        const { ok, payload, data, networkError } = await fhFetch(config.trashUrl);
        if (!ok || networkError) {
            toast(data.message || config.messages?.loadFailed || 'Load failed', 'danger');
            return false;
        }
        trash.value = normalizeTrash(payload);
        clearSelection();
        return true;
    }

    async function softRefetchShow(fileId) {
        const id = fileId || showFile.value?.id || config.realtime?.fileId;
        if (!id) return false;
        const base = config.fileShowBase || joinFhPath(fhBase.value, 'files');
        const url = new URL(joinFhPath(base, id), window.location.origin);
        if (config.ownerUserId) url.searchParams.set('user_id', config.ownerUserId);
        if (config.workspaceId) url.searchParams.set('workspace_id', config.workspaceId);
        const { ok, payload, data, networkError } = await fhFetch(url.toString());
        if (!ok || networkError) {
            toast(data.message || config.messages?.loadFailed || 'Load failed', 'danger');
            return false;
        }
        showFile.value = payload;
        return true;
    }

    async function softRefetchQuota() {
        if (!config.quotaUrl) return;
        const url = new URL(config.quotaUrl, window.location.origin);
        if (config.ownerUserId) url.searchParams.set('user_id', config.ownerUserId);
        const { ok, payload } = await fhFetch(url.toString());
        if (ok && payload) {
            quota.value = payload;
            if (payload.state) config.planState = payload.state;
        }
    }

    async function softRefetchWorkspaces() {
        if (!config.workspacesUrl) return;
        const { ok, payload } = await fhFetch(config.workspacesUrl);
        if (ok && Array.isArray(payload)) {
            workspaces.value = payload;
        }
    }

    async function softRefetchMembers() {
        if (!config.workspaceId || !config.workspacesBase) return;
        const url = joinFhPath(config.workspacesBase, config.workspaceId, 'members');
        if (!url) return;
        const { ok, payload } = await fhFetch(url);
        if (ok) workspaceMembers.value = Array.isArray(payload) ? payload : [];
    }

    async function softRefetchActivity() {
        if (!config.workspaceId || !config.workspacesBase) return;
        const url = joinFhPath(config.workspacesBase, config.workspaceId, 'activity') + '?limit=30';
        const { ok, payload } = await fhFetch(url);
        if (ok) workspaceActivity.value = Array.isArray(payload) ? payload : [];
    }

    function softRefresh() {
        const page = config.realtime?.page || panel.value;
        if (page === 'trash' || panel.value === 'trash') {
            softRefetchTrash();
        } else if (page === 'show' || panel.value === 'show') {
            softRefetchShow();
        } else {
            softRefetchBrowse();
        }
        softRefetchQuota();
    }

    function upsertFile(file) {
        if (!file?.id) return;
        const files = [...(browse.value.files || [])];
        const idx = files.findIndex((f) => f.id === file.id);
        if (idx >= 0) files[idx] = { ...files[idx], ...file };
        else files.unshift(file);
        browse.value = { ...browse.value, files };
    }

    let router = null;
    let onWorkspaceChange = null;

    function setWorkspaceChangeHandler(fn) {
        onWorkspaceChange = fn;
    }

    async function handleNavigate(route) {
        const prevWs = config.workspaceId;
        loading.value = true;
        try {
            syncConfigFromRoute(route);
            panel.value = route.name === 'unknown' ? 'browse' : route.name;
            clearSelection();
            contextMenu.value = null;

            if (route.name === 'browse') {
                await softRefetchBrowse();
                if (prevWs !== config.workspaceId) {
                    await softRefetchWorkspaces();
                    await softRefetchMembers();
                    await softRefetchActivity();
                    onWorkspaceChange?.(prevWs, config.workspaceId);
                }
            } else if (route.name === 'trash') {
                await softRefetchTrash();
                if (prevWs !== config.workspaceId) {
                    onWorkspaceChange?.(prevWs, config.workspaceId);
                }
            } else if (route.name === 'show') {
                await softRefetchShow(route.fileId);
            }
            await softRefetchQuota();
        } finally {
            loading.value = false;
        }
    }

    function startRouter(rootEl) {
        router = createFhRouter({
            root: rootEl || document,
            onNavigate: handleNavigate,
            shouldIntercept: shouldSoftNavigate,
        });
        const route = parseFhUrl();
        panel.value = route.name === 'unknown' ? 'browse' : route.name;
        syncConfigFromRoute(route);
        return router;
    }

    function navigate(href, opts = {}) {
        if (!href) return;
        if (!shouldSoftNavigate(href)) {
            window.location.href = href;
            return;
        }
        if (router) {
            router.go(href, opts);
            return;
        }
        window.location.href = href;
    }

    function browseHref(extra = {}) {
        const base = fhBase.value || config.browseUrl || '';
        if (!base) return '';
        const u = new URL(base.split('?')[0], window.location.origin);
        const params = new URLSearchParams();
        if (config.ownerUserId) params.set('user_id', config.ownerUserId);
        if (extra.workspace_id !== undefined) {
            if (extra.workspace_id) params.set('workspace_id', extra.workspace_id);
        } else if (config.workspaceId) {
            params.set('workspace_id', config.workspaceId);
        }
        if (extra.folder_id) params.set('folder_id', extra.folder_id);
        const qs = params.toString();
        return u.pathname + (qs ? `?${qs}` : '');
    }

    function trashHref() {
        const base = config.trashUrl || joinFhPath(fhBase.value, 'trash');
        if (!base) return '';
        const u = new URL(base.split('?')[0], window.location.origin);
        const params = new URLSearchParams();
        if (config.ownerUserId) params.set('user_id', config.ownerUserId);
        if (config.workspaceId) params.set('workspace_id', config.workspaceId);
        const qs = params.toString();
        return u.pathname + (qs ? `?${qs}` : '');
    }

    function fileShowHref(id) {
        return joinFhPath(config.fileShowBase || joinFhPath(fhBase.value, 'files'), id);
    }

    watch(viewMode, (m) => {
        try {
            localStorage.setItem(STORAGE_KEY, m);
        } catch {
            /* ignore */
        }
    });

    return {
        config,
        panel,
        loading,
        isBusy,
        setBusy,
        viewMode,
        setViewMode,
        browse,
        trash,
        showFile,
        quota,
        workspaces,
        workspaceMembers,
        workspaceActivity,
        currentWorkspace,
        isMicrostock,
        fhBase,
        ownerQuery,
        selected,
        selectionAnchor,
        contextMenu,
        clearSelection,
        toggleSelect,
        selectAll,
        setSelection,
        softRefetchBrowse,
        softRefetchTrash,
        softRefetchShow,
        softRefetchQuota,
        softRefetchWorkspaces,
        softRefetchMembers,
        softRefetchActivity,
        softRefresh,
        upsertFile,
        startRouter,
        navigate,
        browseHref,
        trashHref,
        fileShowHref,
        setWorkspaceChangeHandler,
        joinFhPath,
        resolveFhBase,
        shouldSoftNavigate,
        hasBrowseChrome: () => hasBrowseChrome(config),
        destroyRouter() {
            router?.destroy?.();
            router = null;
        },
    };
}

function normalizeBrowse(raw) {
    const data = raw && typeof raw === 'object' ? raw : {};
    return {
        folders: Array.isArray(data.folders) ? data.folders : [],
        files: Array.isArray(data.files) ? data.files : [],
        breadcrumb: Array.isArray(data.breadcrumb) ? data.breadcrumb : [],
        folder: data.folder || null,
        workspace: data.workspace || null,
        quota: data.quota || null,
    };
}

function normalizeTrash(raw) {
    const data = raw && typeof raw === 'object' ? raw : {};
    return {
        folders: Array.isArray(data.folders) ? data.folders : [],
        files: Array.isArray(data.files) ? data.files : [],
    };
}

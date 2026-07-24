/**
 * File Hosting client router — pushState soft-nav inside FH shell only.
 * Download hops and external URLs stay full document navigations.
 */

/**
 * @typedef {'browse'|'trash'|'show'|'unknown'} FhRouteName
 * @typedef {{ name: FhRouteName, fileId?: string|null, folderId?: string|null, workspaceId?: string|null, userId?: string|null, path: string, search: string, href: string }} FhRoute
 */

/**
 * @param {string} [href]
 * @returns {FhRoute}
 */
export function parseFhUrl(href) {
    const url = new URL(href || window.location.href, window.location.origin);
    const path = url.pathname.replace(/\/+$/, '') || '/';
    const search = url.search;
    const params = url.searchParams;

    const base = {
        folderId: params.get('folder_id') || null,
        workspaceId: params.get('workspace_id') || null,
        userId: params.get('user_id') || null,
        path,
        search,
        href: url.pathname + url.search,
    };

    const fileMatch = path.match(/\/file-hosting\/files\/([0-9a-fA-F-]{36})$/);
    if (fileMatch) {
        return { name: 'show', fileId: fileMatch[1], ...base };
    }

    if (path.endsWith('/file-hosting/trash') || path === '/file-hosting/trash') {
        return { name: 'trash', fileId: null, ...base };
    }

    if (path.endsWith('/file-hosting') || path === '/file-hosting') {
        return { name: 'browse', fileId: null, ...base };
    }

    return { name: 'unknown', fileId: null, ...base };
}

/**
 * @param {string} [href]
 * @returns {boolean}
 */
export function isFhInternalUrl(href) {
    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }
        const path = url.pathname.replace(/\/+$/, '') || '/';
        if (!path.includes('/file-hosting')) {
            return false;
        }
        // Download stays full navigation (signed hop / S3).
        if (/\/file-hosting\/files\/[^/]+\/download$/.test(path)) {
            return false;
        }
        // Uploads / bulk / mutation endpoints are not soft routes.
        if (/\/file-hosting\/(uploads|files\/bulk|folders\/bulk)/.test(path)) {
            return false;
        }
        return parseFhUrl(url.href).name !== 'unknown';
    } catch {
        return false;
    }
}

/**
 * @param {string} [href]
 * @returns {boolean}
 */
export function isPublicShareUrl(href) {
    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }
        const path = url.pathname;
        if (/\/s\/f\/[^/]+\/download$/.test(path)) {
            return false;
        }
        return /^\/s\/(f|d)\/[0-9a-fA-F-]{36}\/?$/.test(path);
    } catch {
        return false;
    }
}

/**
 * @param {string} [href]
 * @returns {{ kind: 'file'|'folder'|null, id: string|null, href: string }}
 */
export function parsePublicShareUrl(href) {
    const url = new URL(href || window.location.href, window.location.origin);
    const fileMatch = url.pathname.match(/\/s\/f\/([0-9a-fA-F-]{36})\/?$/);
    if (fileMatch) {
        return { kind: 'file', id: fileMatch[1], href: url.pathname + url.search };
    }
    const folderMatch = url.pathname.match(/\/s\/d\/([0-9a-fA-F-]{36})\/?$/);
    if (folderMatch) {
        return { kind: 'folder', id: folderMatch[1], href: url.pathname + url.search };
    }
    return { kind: null, id: null, href: url.pathname + url.search };
}

/**
 * @param {object} options
 * @param {(route: FhRoute, meta: { replace: boolean, popstate: boolean }) => Promise<void>|void} options.onNavigate
 * @param {(href: string) => boolean} [options.shouldIntercept]
 * @param {HTMLElement|Document} [options.root]
 */
export function createFhRouter(options) {
    const {
        onNavigate,
        shouldIntercept = isFhInternalUrl,
        root = document,
    } = options;

    let busy = false;
    let currentHref = window.location.pathname + window.location.search;

    async function go(href, { replace = false, popstate = false } = {}) {
        const url = new URL(href, window.location.origin);
        const next = url.pathname + url.search;
        if (busy) {
            return;
        }
        busy = true;
        try {
            // Auth FH routes use parseFhUrl; public /s/* uses shouldIntercept only.
            const route = parseFhUrl(next);
            const allowed = route.name !== 'unknown' || shouldIntercept(next);
            if (!allowed) {
                window.location.href = next;
                return;
            }
            if (!popstate) {
                if (replace) {
                    window.history.replaceState({ fh: true }, '', next);
                } else if (next !== currentHref) {
                    window.history.pushState({ fh: true }, '', next);
                }
            }
            currentHref = next;
            await onNavigate(route, { replace, popstate });
        } finally {
            busy = false;
        }
    }

    function onClick(e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || a.hasAttribute('download')) {
            return;
        }
        const href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
            return;
        }
        if (!shouldIntercept(href)) {
            return;
        }
        e.preventDefault();
        go(href);
    }

    function onPopState() {
        go(window.location.pathname + window.location.search, { popstate: true });
    }

    root.addEventListener('click', onClick);
    window.addEventListener('popstate', onPopState);

    // Seed history state so back from first soft-nav works.
    if (!window.history.state || !window.history.state.fh) {
        window.history.replaceState({ fh: true }, '', currentHref);
    }

    return {
        go,
        parse: parseFhUrl,
        get busy() {
            return busy;
        },
        destroy() {
            root.removeEventListener('click', onClick);
            window.removeEventListener('popstate', onPopState);
        },
    };
}

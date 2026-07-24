/**
 * Drag selected listing items onto a folder (or breadcrumb parent/root) to move.
 * MIME type application/x-fh-items so OS-file drops still hit the upload dropzone.
 */

export const FH_ITEMS_MIME = 'application/x-fh-items';

/**
 * @param {object} deps
 * @param {HTMLElement} deps.root
 * @param {Set<string>} deps.selected
 * @param {object} deps.config
 * @param {ReturnType<import('./selection').createSelectionController>} deps.selection
 * @param {(keys: string[], targetFolderId: string|null) => Promise<{ok: boolean, message?: string}>} deps.moveItems
 * @param {(msg: string, type?: string) => void} deps.showToast
 * @param {() => void} deps.softRefresh
 * @param {(on: boolean) => void} [deps.setBusy]
 */
export function createDragMove(deps) {
    const { root, selected, config, selection, moveItems, showToast, softRefresh, setBusy } = deps;

    let dragging = false;

    function isFhDrag(dt) {
        if (!dt || !dt.types) {
            return false;
        }
        return Array.from(dt.types).includes(FH_ITEMS_MIME);
    }

    function clearDropTargets() {
        root.querySelectorAll('.is-drop-target').forEach((el) => {
            el.classList.remove('is-drop-target');
        });
    }

    function clearDragSources() {
        root.querySelectorAll('.is-dragging-source').forEach((el) => {
            el.classList.remove('is-dragging-source');
        });
    }

    /**
     * @returns {{ folderId: string|null, el: HTMLElement }|null}
     * folderId null = drive root.
     */
    function moveTargetFromEvent(e) {
        // Prefer explicit breadcrumb drop chips (parent / root).
        const crumb = e.target.closest('[data-fh-drop-folder]');
        if (crumb && root.contains(crumb)) {
            const raw = crumb.getAttribute('data-fh-drop-folder') || '';
            const folderId = raw === '' || raw === 'root' ? null : raw;
            return { folderId, el: crumb };
        }

        const row = e.target.closest('[data-fh-row][data-fh-entity="folder"][data-fh-id]');
        if (!row || !root.contains(row)) {
            return null;
        }
        if (row.closest('[data-fh-panel="trash"]')) {
            return null;
        }
        const browse = root.querySelector('[data-fh-panel="browse"]') || root;
        if (!browse.contains(row) && !row.closest('[data-fh-view]')) {
            return null;
        }
        return { folderId: row.getAttribute('data-fh-id'), el: row };
    }

    /**
     * @param {string|null} folderId
     * @param {string[]} keys
     */
    function canDropOnFolder(folderId, keys) {
        // Root (null) is always a valid destination for items not already at root
        // (server no-ops same-parent moves; we still allow the gesture).
        if (folderId !== null && folderId !== undefined && folderId !== '') {
            if (keys.includes('folder:' + folderId)) {
                return false;
            }
        }
        return true;
    }

    function onDragStart(e) {
        const row = e.target.closest('[data-fh-row][data-fh-entity][data-fh-id]');
        if (!row || row.getAttribute('data-fh-entity') === 'parent') {
            return;
        }
        if (selection.isInteractiveTarget(e.target) && !e.target.closest('[data-fh-row]')) {
            e.preventDefault();
            return;
        }
        if (e.target.closest('input, button, a.fh-table__action-btn, [data-bs-toggle]')) {
            e.preventDefault();
            return;
        }

        const key = selection.rowKey(row);
        if (!key) {
            e.preventDefault();
            return;
        }

        if (!selected.has(key)) {
            selection.selectOnly(key);
        }

        const keys = Array.from(selected);
        dragging = true;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData(FH_ITEMS_MIME, JSON.stringify(keys));
        e.dataTransfer.setData('text/plain', keys.join(','));

        keys.forEach((k) => {
            const parsed = selection.parseKey(k);
            if (!parsed) {
                return;
            }
            const el = root.querySelector(
                '[data-fh-row][data-fh-entity="' + parsed.type + '"][data-fh-id="' + CSS.escape(parsed.id) + '"]',
            );
            if (el) {
                el.classList.add('is-dragging-source');
            }
        });
    }

    function onDragEnd() {
        dragging = false;
        clearDropTargets();
        clearDragSources();
    }

    function onDragOver(e) {
        if (!isFhDrag(e.dataTransfer) && !dragging) {
            return;
        }
        if (e.dataTransfer && e.dataTransfer.types && !isFhDrag(e.dataTransfer) && !dragging) {
            return;
        }

        const target = moveTargetFromEvent(e);
        if (!target) {
            clearDropTargets();
            return;
        }

        const keys = Array.from(selected);
        if (!canDropOnFolder(target.folderId, keys)) {
            clearDropTargets();
            return;
        }

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!target.el.classList.contains('is-drop-target')) {
            clearDropTargets();
            target.el.classList.add('is-drop-target');
        }
    }

    function onDragLeave(e) {
        const dropEl = e.target.closest('.is-drop-target');
        if (!dropEl) {
            return;
        }
        const related = e.relatedTarget;
        if (related && dropEl.contains(related)) {
            return;
        }
        dropEl.classList.remove('is-drop-target');
    }

    async function onDrop(e) {
        if (!isFhDrag(e.dataTransfer) && !dragging) {
            return;
        }

        const target = moveTargetFromEvent(e);
        if (!target) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        let keys = [];
        try {
            const raw = e.dataTransfer.getData(FH_ITEMS_MIME) || e.dataTransfer.getData('text/plain');
            if (raw && raw.startsWith('[')) {
                keys = JSON.parse(raw);
            } else if (raw) {
                keys = raw.split(',').filter(Boolean);
            }
        } catch (err) {
            keys = Array.from(selected);
        }
        if (!keys.length) {
            keys = Array.from(selected);
        }

        clearDropTargets();
        clearDragSources();
        dragging = false;

        if (!canDropOnFolder(target.folderId, keys)) {
            showToast(config.messages?.moveInvalid || config.messages?.error || 'Cannot move here', 'error');
            return;
        }

        if (typeof setBusy === 'function') {
            setBusy(true);
        }
        try {
            const result = await moveItems(keys, target.folderId);
            if (result?.ok) {
                showToast(result.message || config.messages?.moved || config.messages?.success || 'Moved', 'success');
                selected.clear();
                selection.syncDom();
                softRefresh();
            } else {
                showToast(result?.message || config.messages?.moveFailed || config.messages?.error || 'Move failed', 'error');
            }
        } finally {
            if (typeof setBusy === 'function') {
                setBusy(false);
            }
        }
    }

    function makeRowsDraggable() {
        root.querySelectorAll('[data-fh-panel="browse"] [data-fh-row], [data-fh-view] [data-fh-row]').forEach((row) => {
            if (row.closest('[data-fh-panel="trash"]')) {
                return;
            }
            if (!row.closest('[data-fh-view], [data-fh-panel="browse"]')) {
                return;
            }
            // Virtual parent "..." is drop target only — not movable.
            if (row.getAttribute('data-fh-entity') === 'parent') {
                row.removeAttribute('draggable');
                row.querySelectorAll('a[href]').forEach((a) => {
                    a.setAttribute('draggable', 'false');
                });
                return;
            }
            if (!row.getAttribute('data-fh-id')) {
                return;
            }
            row.setAttribute('draggable', 'true');
            row.querySelectorAll('a[href]').forEach((a) => {
                a.setAttribute('draggable', 'false');
            });
        });
    }

    function bind() {
        root.addEventListener('dragstart', onDragStart);
        root.addEventListener('dragend', onDragEnd);
        root.addEventListener('dragover', onDragOver);
        root.addEventListener('dragleave', onDragLeave);
        root.addEventListener('drop', onDrop);
        makeRowsDraggable();
    }

    return {
        bind,
        makeRowsDraggable,
        clearDropTargets,
    };
}

/**
 * Sequential web PATCH move for files/folders.
 *
 * @param {object} opts
 * @param {string[]} opts.keys
 * @param {string|null} opts.targetFolderId
 * @param {object} opts.config
 * @param {string} opts.csrfToken
 * @param {(key: string) => {type: string, id: string}|null} opts.parseKey
 */
export async function moveItemsRequest(opts) {
    const { keys, targetFolderId, config, csrfToken, parseKey } = opts;
    const owner = config.ownerUserId || '';
    const workspace = config.workspaceId || '';
    let lastMessage = '';
    let okCount = 0;

    for (const key of keys) {
        const parsed = parseKey(key);
        if (!parsed) {
            continue;
        }

        if (parsed.type === 'folder' && targetFolderId && parsed.id === targetFolderId) {
            continue;
        }

        const isFile = parsed.type === 'file';
        const base = isFile ? config.fileRenameBase : config.folderRenameBase;
        if (!base) {
            return { ok: false, message: config.messages?.error || 'Error' };
        }

        const url = base + '/' + parsed.id + '/move';
        const body = new FormData();
        body.append('_method', 'PATCH');
        body.append('_token', csrfToken);
        if (owner) {
            body.append('user_id', owner);
        }
        if (workspace) {
            body.append('workspace_id', workspace);
        }
        if (isFile) {
            body.append('folder_id', targetFolderId || '');
        } else {
            body.append('parent_id', targetFolderId || '');
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                okCount += 1;
                lastMessage = data.message || lastMessage;
            } else {
                return {
                    ok: false,
                    message: data.message || config.messages?.moveFailed || config.messages?.error || 'Move failed',
                };
            }
        } catch (err) {
            return { ok: false, message: config.messages?.networkError || 'Network error' };
        }
    }

    if (okCount === 0) {
        return { ok: false, message: config.messages?.moveInvalid || config.messages?.error || 'Nothing to move' };
    }

    return { ok: true, message: lastMessage || config.messages?.moved || config.messages?.success };
}

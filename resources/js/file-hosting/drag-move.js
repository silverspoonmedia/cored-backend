/**
 * Drag selected listing items onto a folder to move (Drive-like).
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
 */
export function createDragMove(deps) {
    const { root, selected, config, selection, moveItems, showToast, softRefresh } = deps;

    let dragging = false;

    function isFhDrag(dt) {
        if (!dt || !dt.types) {
            return false;
        }
        // types may be DOMStringList
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

    function folderTargetFromEvent(e) {
        const row = e.target.closest('[data-fh-row][data-fh-entity="folder"][data-fh-id]');
        if (!row) {
            return null;
        }
        const browse = root.querySelector('[data-fh-panel="browse"]') || root;
        if (!browse.contains(row)) {
            return null;
        }
        return row;
    }

    function canDropOnFolder(folderId, keys) {
        if (!folderId) {
            return false;
        }
        // Cannot drop onto a selected folder being moved (self / multi).
        if (keys.includes('folder:' + folderId)) {
            return false;
        }
        return true;
    }

    function onDragStart(e) {
        const row = e.target.closest('[data-fh-row][data-fh-entity][data-fh-id]');
        if (!row) {
            return;
        }
        // Don't start item-drag from interactive controls.
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
        // Some browsers need a text/plain fallback for DnD to activate.
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
        // Only accept our payload (when types available).
        if (e.dataTransfer && e.dataTransfer.types && !isFhDrag(e.dataTransfer) && !dragging) {
            return;
        }

        const folderRow = folderTargetFromEvent(e);
        if (!folderRow) {
            clearDropTargets();
            return;
        }

        const folderId = folderRow.getAttribute('data-fh-id');
        const keys = Array.from(selected);
        if (!canDropOnFolder(folderId, keys)) {
            clearDropTargets();
            return;
        }

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!folderRow.classList.contains('is-drop-target')) {
            clearDropTargets();
            folderRow.classList.add('is-drop-target');
        }
    }

    function onDragLeave(e) {
        const folderRow = e.target.closest('[data-fh-row].is-drop-target');
        if (!folderRow) {
            return;
        }
        // Leaving into a child still inside the row — ignore.
        const related = e.relatedTarget;
        if (related && folderRow.contains(related)) {
            return;
        }
        folderRow.classList.remove('is-drop-target');
    }

    async function onDrop(e) {
        if (!isFhDrag(e.dataTransfer) && !dragging) {
            return;
        }

        const folderRow = folderTargetFromEvent(e);
        if (!folderRow) {
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

        const folderId = folderRow.getAttribute('data-fh-id');
        clearDropTargets();
        clearDragSources();
        dragging = false;

        if (!canDropOnFolder(folderId, keys)) {
            showToast(config.messages?.moveInvalid || config.messages?.error || 'Cannot move here', 'error');
            return;
        }

        const result = await moveItems(keys, folderId);
        if (result?.ok) {
            showToast(result.message || config.messages?.moved || config.messages?.success || 'Moved', 'success');
            selected.clear();
            selection.syncDom();
            softRefresh();
        } else {
            showToast(result?.message || config.messages?.moveFailed || config.messages?.error || 'Move failed', 'error');
        }
    }

    function makeRowsDraggable() {
        root.querySelectorAll('[data-fh-panel="browse"] [data-fh-row], [data-fh-view] [data-fh-row]').forEach((row) => {
            // Trash panel rows may match if not scoped — skip trash.
            if (row.closest('[data-fh-panel="trash"]')) {
                return;
            }
            if (!row.closest('[data-fh-view], [data-fh-panel="browse"]')) {
                return;
            }
            row.setAttribute('draggable', 'true');
            // Prevent <a> native image/link drag from fighting row HTML5 DnD.
            row.querySelectorAll('a[href]').forEach((a) => {
                a.setAttribute('draggable', 'false');
            });
        });
    }

    function bind() {
        // Event delegation on root.
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

        // Skip moving a folder into itself.
        if (parsed.type === 'folder' && parsed.id === targetFolderId) {
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

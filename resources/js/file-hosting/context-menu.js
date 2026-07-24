/**
 * Right-click context menu for File Hosting browse listing (Drive-like).
 * Reuses existing rename/move/share modals and ajax delete — no new APIs.
 */

/**
 * @param {object} deps
 * @param {HTMLElement} deps.root
 * @param {Set<string>} deps.selected
 * @param {object} deps.config
 * @param {ReturnType<import('./selection').createSelectionController>} deps.selection
 * @param {(href: string) => void} deps.openHref
 * @param {(keys: string[], targetFolderId: string|null) => Promise<void>} deps.moveItems
 * @param {(url: string, method: string, body: object) => Promise<Response>} deps.bulkFetch
 * @param {(msg: string, type?: string) => void} deps.showToast
 * @param {() => void} deps.softRefresh
 * @param {string} deps.csrfToken
 * @param {(on: boolean) => void} [deps.setBusy]
 */
export function createContextMenu(deps) {
    const {
        root,
        selected,
        config,
        selection,
        openHref,
        moveItems,
        bulkFetch,
        showToast,
        softRefresh,
        csrfToken,
        setBusy,
    } = deps;

    const labels = {
        open: config.messages?.actions?.open || config.messages?.actions?.view || 'Open',
        rename: config.messages?.actions?.rename || 'Rename',
        move: config.messages?.actions?.move || 'Move',
        share: config.messages?.actions?.share || 'Share',
        delete: config.messages?.actions?.delete || 'Delete',
        unshare: config.messages?.bulkUnshare || config.messages?.actions?.unshare || 'Make private',
    };

    let menu = null;
    let lastFocusKey = null;

    function ensureMenu() {
        if (menu) {
            return menu;
        }
        menu = document.createElement('div');
        menu.className = 'fh-context-menu';
        menu.setAttribute('data-fh-context-menu', '');
        menu.hidden = true;
        menu.setAttribute('role', 'menu');
        menu.innerHTML = [
            item('open', labels.open, 'bi-folder2-open'),
            item('rename', labels.rename, 'bi-pencil'),
            item('move', labels.move, 'bi-folder-symlink'),
            item('share', labels.share, 'bi-share'),
            item('delete', labels.delete, 'bi-trash', true),
        ].join('');
        document.body.appendChild(menu);

        menu.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-fh-ctx]');
            if (!btn || btn.disabled) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const action = btn.getAttribute('data-fh-ctx');
            hide();
            runAction(action);
        });

        return menu;
    }

    function item(action, label, icon, danger) {
        return (
            '<button type="button" class="fh-context-menu__item'
            + (danger ? ' is-danger' : '')
            + '" role="menuitem" data-fh-ctx="'
            + action
            + '"><i class="bi '
            + icon
            + '" aria-hidden="true"></i><span>'
            + escapeHtml(label)
            + '</span></button>'
        );
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    function hide() {
        if (!menu) {
            return;
        }
        menu.hidden = true;
        menu.classList.remove('is-open');
    }

    function showAt(x, y) {
        const el = ensureMenu();
        updateEnabled();
        el.hidden = false;
        el.classList.add('is-open');
        // Position then clamp to viewport.
        el.style.left = '0px';
        el.style.top = '0px';
        const rect = el.getBoundingClientRect();
        const left = Math.min(x, window.innerWidth - rect.width - 8);
        const top = Math.min(y, window.innerHeight - rect.height - 8);
        el.style.left = Math.max(8, left) + 'px';
        el.style.top = Math.max(8, top) + 'px';
    }

    function primaryKey() {
        if (lastFocusKey && selected.has(lastFocusKey)) {
            return lastFocusKey;
        }
        return selected.values().next().value || null;
    }

    function updateEnabled() {
        if (!menu) {
            return;
        }
        // Virtual parent "..." row: Open only.
        if (lastFocusKey === 'parent') {
            setEnabled('open', true);
            setEnabled('rename', false);
            setEnabled('move', false);
            setEnabled('share', false);
            setEnabled('delete', false);
            return;
        }

        const count = selected.size;
        const key = primaryKey();
        const parsed = key ? selection.parseKey(key) : null;
        const perms = config.permissions || {};
        const single = count === 1;

        setEnabled('open', single);
        setEnabled('rename', single && canEdit(parsed, perms));
        setEnabled('move', count >= 1 && canEditAny(perms));
        setEnabled('share', count >= 1 && canEditAny(perms));
        setEnabled('delete', count >= 1 && canDeleteAny(perms));
    }

    function canEdit(parsed, perms) {
        if (!parsed) {
            return false;
        }
        return parsed.type === 'file' ? Boolean(perms.editFile) : Boolean(perms.editFolder);
    }

    function canEditAny(perms) {
        return Boolean(perms.editFile || perms.editFolder);
    }

    function canDeleteAny(perms) {
        return Boolean(perms.deleteFile || perms.deleteFolder);
    }

    function setEnabled(action, on) {
        const btn = menu.querySelector('[data-fh-ctx="' + action + '"]');
        if (btn) {
            btn.disabled = !on;
            btn.classList.toggle('is-disabled', !on);
        }
    }

    function rowEl(key) {
        const parsed = selection.parseKey(key);
        if (!parsed) {
            return null;
        }
        return root.querySelector(
            '[data-fh-row][data-fh-entity="' + parsed.type + '"][data-fh-id="' + CSS.escape(parsed.id) + '"]',
        );
    }

    function openBootstrapModal(modalId, dataset) {
        const modalEl = document.getElementById(modalId);
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }
        // Fake relatedTarget so existing show.bs.modal populate handlers work.
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.style.display = 'none';
        Object.keys(dataset).forEach((k) => {
            trigger.dataset[k] = dataset[k] == null ? '' : String(dataset[k]);
        });
        document.body.appendChild(trigger);
        const instance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modalEl.addEventListener(
            'hidden.bs.modal',
            function () {
                trigger.remove();
            },
            { once: true },
        );
        instance.show(trigger);
    }

    function readRowMeta(row) {
        if (!row) {
            return { name: '', visibility: 'private', shareUrl: '' };
        }
        const name =
            row.querySelector('[data-fh-name]')?.textContent?.trim()
            || row.querySelector('.fh-table__name-text')?.textContent?.trim()
            || '';
        // Prefer share button dataset when present (has visibility + share url).
        const shareBtn = row.querySelector(
            '[data-bs-target="#fhShareFileModal"], [data-bs-target="#fhShareFolderModal"]',
        );
        return {
            name: name,
            visibility: shareBtn?.dataset?.visibility || 'private',
            shareUrl: shareBtn?.dataset?.shareUrl || '',
        };
    }

    async function runAction(action) {
        // Parent "..." virtual row — open only.
        if (lastFocusKey === 'parent') {
            if (action !== 'open') {
                return;
            }
            const parentRow = root.querySelector('[data-fh-row][data-fh-entity="parent"]');
            const link = parentRow?.querySelector('a[href]');
            const href = link?.getAttribute('href');
            if (href && typeof openHref === 'function') {
                openHref(href);
            }
            return;
        }

        const key = primaryKey();
        if (!key) {
            return;
        }
        const parsed = selection.parseKey(key);
        if (!parsed) {
            return;
        }
        const row = rowEl(key);
        const meta = readRowMeta(row);
        const keys = Array.from(selected);

        if (action === 'open') {
            const link = row?.querySelector(
                'a.fh-table__name[href], .fh-file-card__name[href], td.fh-table__icon-cell a[href], a[href]:not(.fh-table__action-btn)',
            );
            const href = link?.getAttribute('href');
            if (href && typeof openHref === 'function') {
                openHref(href);
            }
            return;
        }

        if (action === 'rename') {
            if (parsed.type === 'file') {
                openBootstrapModal('fhRenameFileModal', { id: parsed.id, name: meta.name });
            } else {
                openBootstrapModal('fhRenameFolderModal', { id: parsed.id, name: meta.name });
            }
            return;
        }

        if (action === 'move') {
            if (keys.length === 1) {
                if (parsed.type === 'file') {
                    openBootstrapModal('fhMoveFileModal', { id: parsed.id });
                } else {
                    openBootstrapModal('fhMoveFolderModal', { id: parsed.id });
                }
            } else {
                // Multi-move: open move modal for first item is wrong. Prompt via first folder picker
                // using a temporary single-item modal path is messy — sequential not possible without
                // destination. Use first item's move modal, then after submit only that item moves.
                // Better: open modal for primary; document multi via drag. For multi context move,
                // open move modal and on form submit intercept is hard.
                // Plan: multi Move uses sequential PATCH after user picks destination via shared tree.
                openMultiMovePicker(keys);
            }
            return;
        }

        if (action === 'share') {
            if (keys.length === 1) {
                if (parsed.type === 'file') {
                    openBootstrapModal('fhShareFileModal', {
                        id: parsed.id,
                        visibility: meta.visibility,
                        shareUrl: meta.shareUrl,
                    });
                } else {
                    openBootstrapModal('fhShareFolderModal', {
                        id: parsed.id,
                        visibility: meta.visibility,
                        shareUrl: meta.shareUrl,
                    });
                }
            } else {
                await bulkVisibility(keys, 'anyone_with_link');
            }
            return;
        }

        if (action === 'delete') {
            await bulkDelete(keys);
        }
    }

    /**
     * Multi-item move: reuse file move modal tree UI, then PATCH all keys to chosen folder.
     */
    function openMultiMovePicker(keys) {
        const modalEl = document.getElementById('fhMoveFileModal');
        if (!modalEl || !window.bootstrap?.Modal) {
            showToast(config.messages?.error || 'Error', 'error');
            return;
        }

        // Mark form so submit handler can fan-out moves (file-manager wires this).
        const form = modalEl.querySelector('#fhMoveFileForm');
        if (form) {
            form.dataset.fhMultiMoveKeys = JSON.stringify(keys);
            // Dummy action; multi handler will ignore single-id action.
            form.action = (config.fileRenameBase || '') + '/__multi__/move';
        }

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.style.display = 'none';
        trigger.dataset.id = '__multi__';
        document.body.appendChild(trigger);

        const instance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        modalEl.addEventListener(
            'hidden.bs.modal',
            function () {
                trigger.remove();
                if (form) {
                    delete form.dataset.fhMultiMoveKeys;
                }
            },
            { once: true },
        );
        instance.show(trigger);

        // Explicitly load tree (bindPopulateModal handles __multi__ via dataset.id).
        if (typeof deps.loadMoveTree === 'function') {
            deps.loadMoveTree(modalEl, 'folder_id', null);
        }
    }

    async function bulkVisibility(keys, visibility) {
        const fileIds = [];
        const folderIds = [];
        keys.forEach((k) => {
            const p = selection.parseKey(k);
            if (!p) {
                return;
            }
            if (p.type === 'file') {
                fileIds.push(p.id);
            } else {
                folderIds.push(p.id);
            }
        });

        const confirmMsg = config.messages?.bulkConfirmShare
            || 'Share {n} selected items with anyone who has the link?';
        if (!window.confirm(String(confirmMsg).replace('{n}', String(keys.length)))) {
            return;
        }

        if (typeof setBusy === 'function') {
            setBusy(true);
        }
        try {
            const promises = [];
            if (fileIds.length && config.bulkFileVisibilityUrl) {
                promises.push(bulkFetch(config.bulkFileVisibilityUrl, 'POST', { ids: fileIds, visibility }));
            }
            if (folderIds.length && config.bulkFolderVisibilityUrl) {
                promises.push(bulkFetch(config.bulkFolderVisibilityUrl, 'POST', { ids: folderIds, visibility }));
            }
            const responses = await Promise.all(promises);
            if (responses.every((r) => r.ok)) {
                showToast(config.messages?.success || 'OK', 'success');
                selected.clear();
                selection.syncDom();
                softRefresh();
            } else {
                showToast(config.messages?.error || 'Error', 'error');
            }
        } catch (err) {
            showToast(config.messages?.networkError || 'Network error', 'error');
        } finally {
            if (typeof setBusy === 'function') {
                setBusy(false);
            }
        }
    }

    async function bulkDelete(keys) {
        const fileIds = [];
        const folderIds = [];
        keys.forEach((k) => {
            const p = selection.parseKey(k);
            if (!p) {
                return;
            }
            if (p.type === 'file') {
                fileIds.push(p.id);
            } else {
                folderIds.push(p.id);
            }
        });

        const confirmMsg =
            keys.length === 1
                ? (selection.parseKey(keys[0])?.type === 'folder'
                    ? (config.messages?.actions?.confirmDeleteFolder || 'Move this folder to trash?')
                    : (config.messages?.actions?.confirmDeleteFile || 'Move this file to trash?'))
                : (config.messages?.bulkConfirmDelete || 'Move {n} selected items to trash?');

        if (!window.confirm(String(confirmMsg).replace('{n}', String(keys.length)))) {
            return;
        }

        if (typeof setBusy === 'function') {
            setBusy(true);
        }
        try {
            const promises = [];
            if (fileIds.length && config.bulkFileDeleteUrl) {
                promises.push(bulkFetch(config.bulkFileDeleteUrl, 'DELETE', { ids: fileIds }));
            }
            if (folderIds.length && config.bulkFolderDeleteUrl) {
                promises.push(bulkFetch(config.bulkFolderDeleteUrl, 'DELETE', { ids: folderIds }));
            }
            // Single item without bulk: fall back to per-row ajax URL.
            if (promises.length === 0 && keys.length === 1) {
                const row = rowEl(keys[0]);
                const delBtn = row?.querySelector('[data-fh-ajax-action]');
                const url = delBtn?.getAttribute('data-fh-action-url');
                if (url) {
                    promises.push(
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                _method: 'DELETE',
                                user_id: config.ownerUserId || '',
                                workspace_id: config.workspaceId || '',
                            }),
                        }),
                    );
                }
            }

            const responses = await Promise.all(promises);
            if (responses.length && responses.every((r) => r.ok)) {
                showToast(config.messages?.success || 'OK', 'success');
                selected.clear();
                selection.syncDom();
                softRefresh();
            } else {
                showToast(config.messages?.error || 'Error', 'error');
            }
        } catch (err) {
            showToast(config.messages?.networkError || 'Network error', 'error');
        } finally {
            if (typeof setBusy === 'function') {
                setBusy(false);
            }
        }
    }

    function onContextMenu(e) {
        const browse = root.querySelector('[data-fh-panel="browse"]') || root;
        const trashPanel = root.querySelector('[data-fh-panel="trash"]');
        if (trashPanel && !trashPanel.hidden && trashPanel.contains(e.target)) {
            return;
        }

        const row = e.target.closest('[data-fh-row][data-fh-entity]');
        if (!row || !browse.contains(row)) {
            hide();
            return;
        }

        // Don't override browser menu on real form controls only.
        if (e.target.closest('input, textarea, select')) {
            return;
        }

        // Virtual parent "..." — Open only, no bulk key.
        if (row.getAttribute('data-fh-entity') === 'parent') {
            e.preventDefault();
            lastFocusKey = 'parent';
            showAt(e.clientX, e.clientY);
            return;
        }

        if (!row.getAttribute('data-fh-id')) {
            hide();
            return;
        }

        e.preventDefault();
        const key = selection.rowKey(row);
        lastFocusKey = key;
        selection.ensureSelected(key);
        showAt(e.clientX, e.clientY);
    }

    function bind() {
        ensureMenu();
        root.addEventListener('contextmenu', onContextMenu);
        document.addEventListener('click', function (e) {
            if (menu && !menu.hidden && !menu.contains(e.target)) {
                hide();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hide();
            }
        });
        window.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
    }

    return {
        bind,
        hide,
        openMultiMovePicker,
        /** @type {(keys: string[]) => void} */
        get multiMoveKeys() {
            return null;
        },
    };
}

/**
 * Drive-like selection for File Hosting browse listing.
 *
 * Keys: "file:{uuid}" | "folder:{uuid}" (same as checkbox data-fh-select values).
 * Single click selects; Ctrl/Cmd toggles; Shift ranges; Escape clears.
 * Name/icon links select on single click; open only on double-click (caller).
 */

const INTERACTIVE =
    'input, button, label, textarea, select, .fh-table__actions, .fh-file-card__actions, [data-bs-toggle], [data-fh-ajax-action], a.fh-table__action-btn';

/**
 * @param {Set<string>} selected
 * @param {HTMLElement} root
 * @param {{ onChange?: () => void }} [opts]
 */
export function createSelectionController(selected, root, opts = {}) {
    let anchorKey = null;
    let clickTimer = null;

    function orderedKeys() {
        // Prefer visible view (table vs grid) so range matches what user sees.
        const table = root.querySelector('[data-fh-view="table"]');
        const grid = root.querySelector('[data-fh-view="grid"]');
        const tableHidden = table && (table.hidden || table.classList.contains('d-none'));
        const scope = !tableHidden && table ? table : (grid || root);

        return Array.from(scope.querySelectorAll('[data-fh-row][data-fh-entity][data-fh-id]'))
            .map(rowKey)
            .filter(Boolean);
    }

    function rowKey(row) {
        if (!row) {
            return null;
        }
        const entity = row.getAttribute('data-fh-entity');
        const id = row.getAttribute('data-fh-id');
        if (!entity || !id) {
            return null;
        }
        return entity + ':' + id;
    }

    function parseKey(key) {
        const sep = key.indexOf(':');
        if (sep < 1) {
            return null;
        }
        return { type: key.slice(0, sep), id: key.slice(sep + 1) };
    }

    function syncDom() {
        root.querySelectorAll('[data-fh-row]').forEach((row) => {
            const key = rowKey(row);
            const on = key ? selected.has(key) : false;
            row.classList.toggle('is-selected', on);
            const cb = row.querySelector('[data-fh-select]');
            if (cb && cb.checked !== on) {
                cb.checked = on;
            }
        });

        const selectAll = root.querySelector('[data-fh-select-all]');
        if (selectAll) {
            const keys = orderedKeys();
            selectAll.checked = keys.length > 0 && keys.every((k) => selected.has(k));
            selectAll.indeterminate = selected.size > 0 && !selectAll.checked;
        }

        if (typeof opts.onChange === 'function') {
            opts.onChange();
        }
    }

    function selectOnly(key) {
        selected.clear();
        if (key) {
            selected.add(key);
            anchorKey = key;
        } else {
            anchorKey = null;
        }
        syncDom();
    }

    function toggle(key) {
        if (!key) {
            return;
        }
        if (selected.has(key)) {
            selected.delete(key);
        } else {
            selected.add(key);
        }
        anchorKey = key;
        syncDom();
    }

    function rangeSelect(toKey) {
        const keys = orderedKeys();
        if (!toKey || keys.length === 0) {
            return;
        }
        const from = anchorKey && keys.includes(anchorKey) ? anchorKey : toKey;
        const a = keys.indexOf(from);
        const b = keys.indexOf(toKey);
        if (a < 0 || b < 0) {
            selectOnly(toKey);
            return;
        }
        const lo = Math.min(a, b);
        const hi = Math.max(a, b);
        selected.clear();
        for (let i = lo; i <= hi; i++) {
            selected.add(keys[i]);
        }
        // Keep original anchor so further shift-clicks extend from first click.
        if (!anchorKey) {
            anchorKey = from;
        }
        syncDom();
    }

    function clear() {
        if (selected.size === 0) {
            syncDom();
            return;
        }
        selected.clear();
        anchorKey = null;
        syncDom();
    }

    function ensureSelected(key) {
        if (!key) {
            return;
        }
        if (!selected.has(key)) {
            selectOnly(key);
        } else {
            syncDom();
        }
    }

    function isInteractiveTarget(target) {
        return Boolean(target && target.closest && target.closest(INTERACTIVE));
    }

    function handleRowClick(e) {
        if (e.button !== 0) {
            return;
        }
        // Browse panel only (not trash/show).
        const browse = root.querySelector('[data-fh-panel="browse"]') || root;
        if (!browse.contains(e.target)) {
            return;
        }
        // Trash rows share data-fh-row — skip when trash panel is active.
        const trashPanel = root.querySelector('[data-fh-panel="trash"]');
        if (trashPanel && !trashPanel.hidden && trashPanel.contains(e.target)) {
            return;
        }

        const row = e.target.closest('[data-fh-row][data-fh-entity][data-fh-id]');
        if (!row || !browse.contains(row)) {
            // Click empty listing chrome → clear (not on toolbar/modals).
            if (
                e.target.closest('[data-fh-view]')
                && !e.target.closest('[data-fh-row]')
                && !isInteractiveTarget(e.target)
            ) {
                clear();
            }
            return;
        }

        if (isInteractiveTarget(e.target)) {
            return;
        }

        const key = rowKey(row);
        if (!key) {
            return;
        }

        // Listing open links: single click selects; double-click opens (separate handler).
        const openLink = e.target.closest('a[href]');
        if (openLink && row.contains(openLink) && !openLink.classList.contains('fh-table__action-btn')) {
            // Allow modified clicks (new tab) to pass through without select hijack.
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                // Ctrl+click on link: Drive still multi-selects; block nav.
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggle(key);
                }
                // shift alone: range, block nav
                if (e.shiftKey && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    e.stopPropagation();
                    rangeSelect(key);
                }
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            // Delay select slightly so dblclick can cancel single-select flash? No —
            // Drive selects immediately on first click of a double-click too.
            if (e.shiftKey) {
                rangeSelect(key);
            } else {
                selectOnly(key);
            }
            return;
        }

        e.preventDefault();

        if (e.shiftKey) {
            rangeSelect(key);
            return;
        }
        if (e.ctrlKey || e.metaKey) {
            toggle(key);
            return;
        }
        selectOnly(key);
    }

    /**
     * @param {(href: string, row: HTMLElement) => void} openFn
     */
    function handleRowDblClick(e, openFn) {
        const browse = root.querySelector('[data-fh-panel="browse"]') || root;
        const row = e.target.closest('[data-fh-row][data-fh-entity][data-fh-id]');
        if (!row || !browse.contains(row)) {
            return;
        }
        if (isInteractiveTarget(e.target) && !e.target.closest('a[href]')) {
            return;
        }

        const link = row.querySelector('a.fh-table__name[href], .fh-file-card__name[href], td.fh-table__icon-cell a[href], .fh-file-card__icon a[href], a[href]');
        const href = link?.getAttribute('href');
        if (!href) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        if (typeof openFn === 'function') {
            openFn(href, row);
        }
    }

    function bind(openFn) {
        // Capture phase so we beat router link intercept for listing name/icon links.
        root.addEventListener('click', handleRowClick, true);
        root.addEventListener('dblclick', function (e) {
            handleRowDblClick(e, openFn);
        }, true);

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            if (!root.isConnected) {
                return;
            }
            // Don't clear while typing in inputs/modals.
            if (e.target && e.target.closest && e.target.closest('input, textarea, select, [contenteditable="true"], .modal.show')) {
                return;
            }
            clear();
        });
    }

    /** Re-apply selection classes after soft-refetch replaced DOM (caller clears Set usually). */
    function afterDomReplace() {
        // Soft-refetch rebuilds rows — selection is intentionally cleared by applyBrowsePayload checkboxes.
        // Keep Set in sync if caller already cleared; otherwise re-sync surviving keys.
        const live = new Set(orderedKeys());
        Array.from(selected).forEach((key) => {
            if (!live.has(key)) {
                selected.delete(key);
            }
        });
        syncDom();
    }

    return {
        selectOnly,
        toggle,
        rangeSelect,
        clear,
        ensureSelected,
        syncDom,
        afterDomReplace,
        orderedKeys,
        rowKey,
        parseKey,
        bind,
        isInteractiveTarget,
    };
}

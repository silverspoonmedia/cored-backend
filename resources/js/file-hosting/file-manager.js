/**
 * File Hosting — AJAX file manager controller.
 *
 * Wraps around direct-upload.js and adds:
 * - AJAX CRUD (rename, visibility, delete, restore, force-delete, move)
 * - Live search dropdown
 * - Grid/list view toggle (persisted in localStorage)
 * - Move modal with folder tree
 * - Drag-drop zone enhancement
 * - Toast notifications
 * - WebSocket live listing (soft-refetch + light DOM patch)
 *
 * All mutations go through web routes (FileManagerController) via fetch.
 */
import { browseUrlWithContext, fetchJson, initFileHostingRealtime } from './realtime';
import {
    applyBrowsePayload,
    applyFileStats,
    applyQuotaPayload,
    tryPatchListing,
    upsertFileRow,
} from './listing-ui';
import { createFhRouter, parseFhUrl } from './router';
import { applyTrashPayload } from './trash-ui';
import { applyShowPayload, patchShowFile } from './show-ui';
import { createSelectionController } from './selection';
import { createContextMenu } from './context-menu';
import { createDragMove, moveItemsRequest } from './drag-move';
import { createMicrostockPanel } from './microstock-panel';
import { formatCollectionProgress } from './microstock-ui';

const STORAGE_KEY = 'fh-view-mode';

function initFileManager(root) {
        if (!root || root.dataset.fhBound === '1') {
            return;
        }
        root.dataset.fhBound = '1';

        const config = JSON.parse(root.dataset.config || '{}');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const searchInput = root.querySelector('[data-fh-search-input]');
        const searchResults = root.querySelector('[data-fh-search-results]');
        const viewToggleBtns = root.querySelectorAll('[data-fh-view-btn]');
        const tableView = root.querySelector('[data-fh-view="table"]');
        const gridView = root.querySelector('[data-fh-view="grid"]');
        const dropzone = root.querySelector('[data-fh-dropzone]');
        const quotaMount = root.querySelector('[data-fh-quota]') || document.querySelector('[data-fh-quota]');
        const spaShell = root.hasAttribute('data-fh-spa') || config.spaShell === true;

        let listingBusy = false;
        let realtimeHandle = null;
        let router = null;
        let currentShowFileId = config.realtime?.fileId || null;
        let lastWorkspaces = Array.isArray(config.workspaces) ? config.workspaces : [];
        let microstockMount = null;
        let microstockPanel = null;
        const selected = new Set();
        let selection = null;
        let dragMove = null;
        let contextMenu = null;

        function setLoading(on) {
            root.classList.toggle('is-fh-loading', Boolean(on));
        }

        /** Mutation busy overlay (move/delete/forms) — distinct from soft-nav is-fh-loading. */
        let busyDepth = 0;
        let collectionPollTimer = null;
        function setBusy(on) {
            if (on) {
                busyDepth += 1;
            } else {
                busyDepth = Math.max(0, busyDepth - 1);
            }
            const active = busyDepth > 0;
            root.classList.toggle('is-fh-busy', active);
            root.setAttribute('aria-busy', active ? 'true' : 'false');
            ensureBusyOverlay();
        }

        function ensureBusyOverlay() {
            let overlay = root.querySelector('[data-fh-busy-overlay]');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'fh-busy-overlay';
                overlay.setAttribute('data-fh-busy-overlay', '');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.innerHTML =
                    '<div class="fh-busy-overlay__inner" role="status">'
                    + '<div class="spinner-border text-primary" aria-hidden="true"></div>'
                    + '<span class="fh-busy-overlay__label visually-hidden">Working…</span>'
                    + '</div>';
                root.appendChild(overlay);
            }
        }

        function showPanel(name) {
            root.querySelectorAll('[data-fh-panel]').forEach((panel) => {
                const match = panel.getAttribute('data-fh-panel') === name;
                panel.hidden = !match;
                panel.classList.toggle('d-none', !match);
            });
            // Toolbar chrome only for browse.
            root.querySelectorAll('[data-fh-browse-chrome]').forEach((el) => {
                const show = name === 'browse';
                el.hidden = !show;
                el.classList.toggle('d-none', !show);
            });
            if (config.realtime) {
                config.realtime.page = name === 'show' ? 'show' : name;
            }
        }

        async function softRefetchListing() {
            if (!config.browseUrl) {
                return;
            }
            // Coalesce concurrent WS + upload-complete refresh into one in-flight request.
            if (listingBusy) {
                softRefetchListing.pending = true;
                return;
            }
            listingBusy = true;
            try {
                do {
                    softRefetchListing.pending = false;
                    const url = browseUrlWithContext(config.browseUrl, config);
                    const json = await fetchJson(url, csrfToken);
                    if (json?.data) {
                        if (json.data.workspace) {
                            config.currentWorkspace = json.data.workspace;
                        }
                        applyBrowsePayload(root, json.data, config);
                        if (json.data.folder?.id !== undefined) {
                            config.currentFolderId = json.data.folder?.id || null;
                        }
                        syncCollectionUploadGate(root, config, json.data.folder);
                        updateCollectionUi(json.data.folder ? json.data.folder.collection_status : null);
                        // Soft-refetch replaces rows — drop stale selection + re-enable DnD.
                        selected.clear();
                        if (selection) {
                            selection.syncDom();
                        }
                        if (dragMove) {
                            dragMove.makeRowsDraggable();
                        }
                    }
                } while (softRefetchListing.pending);
            } finally {
                listingBusy = false;
            }
        }

        async function softRefetchQuota() {
            if (!config.quotaUrl || !quotaMount) {
                return;
            }
            const json = await fetchJson(config.quotaUrl, csrfToken);
            if (json?.data) {
                applyQuotaPayload(quotaMount, json.data, config.quotaLabels || {});
                if (json.data.state) {
                    config.planState = json.data.state;
                }
            }
        }

        async function softRefetchTrash() {
            if (!config.trashUrl) {
                return;
            }
            const json = await fetchJson(config.trashUrl, csrfToken);
            if (json?.data) {
                applyTrashPayload(root, json.data, config);
            }
        }

        async function softRefetchShow() {
            if (!currentShowFileId || !config.fileShowBase) {
                return;
            }
            const params = new URLSearchParams();
            if (config.ownerUserId) {
                params.set('user_id', config.ownerUserId);
            }
            if (config.workspaceId) {
                params.set('workspace_id', config.workspaceId);
            }
            const url = `${config.fileShowBase}/${currentShowFileId}${params.toString() ? `?${params}` : ''}`;
            const json = await fetchJson(url, csrfToken);
            const mount = root.querySelector('[data-fh-panel="show"]') || root;
            if (json?.data) {
                applyShowPayload(mount, json.data, config);
                patchShowFile(mount, json.data);
            }
        }

        async function softRefetchMembers() {
            if (!config.membersUrl) {
                return;
            }
            const json = await fetchJson(config.membersUrl, csrfToken);
            const mount = document.querySelector('[data-fh-members-body]');
            if (!mount || !json?.data) {
                return;
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            const canManage = config.canManageMembers === true;
            const removeLabel = config.messages?.collab?.remove || 'Remove';
            const confirmRemove = config.messages?.collab?.confirmRemove || '';
            const wsId = config.workspaceId;
            mount.innerHTML = rows.map((m) => {
                const name = m.user?.email || m.user?.name || m.user_id || '';
                const role = m.role || '';
                const uid = m.user_id || '';
                const canRemove = canManage && role !== 'owner' && uid && wsId;
                const removeBtn = canRemove
                    ? `<form method="post" action="${escapeHtml(`/file-hosting/workspaces/${wsId}/members/${uid}`)}" class="d-inline" data-fh-ajax-form data-fh-success="members">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0"
                          data-fh-confirm="${escapeHtml(confirmRemove)}">${escapeHtml(removeLabel)}</button>
                      </form>`
                    : '';
                return `<tr data-fh-member-id="${escapeHtml(uid)}"><td class="text-break">${escapeHtml(name)}</td><td><span class="badge text-bg-light border">${escapeHtml(role)}</span></td><td class="text-end">${removeBtn}</td></tr>`;
            }).join('') || `<tr><td colspan="3" class="text-muted">${escapeHtml(config.messages?.collab?.noMembers || '')}</td></tr>`;
        }

        async function softRefetchActivity() {
            if (!config.activityUrl) {
                return;
            }
            const json = await fetchJson(config.activityUrl, csrfToken);
            const mount = document.querySelector('[data-fh-activity-list]');
            if (!mount || !json?.data) {
                return;
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            mount.innerHTML = rows.map((a) => {
                const desc = a.description || a.event || '';
                const when = a.created_at_human || a.created_at || '';
                return `<li class="list-group-item small"><div>${escapeHtml(desc)}</div><div class="text-muted">${escapeHtml(when)}</div></li>`;
            }).join('') || '';
        }

        function onListingPatch(payload) {
            if (config.realtime?.page === 'show' && payload?.entity_id && payload.entity_id === currentShowFileId) {
                softRefetchShow();
                return true;
            }
            return tryPatchListing(payload, root, config);
        }

        function onFileStats(payload) {
            applyFileStats(root, payload);
        }

        function onMembersChanged(payload) {
            if (payload?.action === 'archived' || payload?.action === 'removed') {
                const selfId = config.realtime?.userId;
                if (payload.action === 'archived' || (payload.action === 'removed' && payload.user_id === selfId)) {
                    if (router) {
                        router.go(config.browseUrl || '/file-hosting', { replace: true });
                    } else {
                        window.location.href = config.browseUrl || '/file-hosting';
                    }
                }
            }
        }

        function rebindRealtime() {
            if (realtimeHandle && typeof realtimeHandle.disconnect === 'function') {
                realtimeHandle.disconnect();
                realtimeHandle = null;
            }
            root.dataset.fhRealtimeBound = '0';
            if (!config.realtime?.enabled) {
                return;
            }
            try {
                realtimeHandle = initFileHostingRealtime({
                    root,
                    config,
                    csrf: csrfToken,
                    onListingRefresh: softRefetchListing,
                    onQuotaRefresh: softRefetchQuota,
                    onTrashRefresh: softRefetchTrash,
                    onMembersRefresh: softRefetchMembers,
                    onActivityRefresh: softRefetchActivity,
                    onListingPatch,
                    onFileStats,
                    onMembersChanged,
                });
            } catch (err) {
                console.warn('File Hosting realtime init failed; soft-refetch still available.', err);
                window.fileHostingRealtime = { enabled: false, connected: false, degraded: true };
            }
        }

        // Expose soft refresh BEFORE realtime init so upload complete never falls
        // through to hard reload if Echo setup throws.
        root.__fhSoftRefresh = function () {
            const page = config.realtime?.page || 'index';
            if (page === 'trash') {
                softRefetchTrash();
            } else if (page === 'show') {
                softRefetchShow();
            } else {
                softRefetchListing();
            }
            softRefetchQuota();
        };
        window.fileHostingSoftRefresh = root.__fhSoftRefresh;
        window.fileHostingUpsertFile = function (file) {
            const result = upsertFileRow(root, file, config);
            if (dragMove) {
                dragMove.makeRowsDraggable();
            }
            return result;
        };
        window.fileHostingNavigate = function (href, opts) {
            if (router) {
                return router.go(href, opts);
            }
            window.location.href = href;
        };

        rebindRealtime();

        const initSteps = [
            initViewToggle,
            initSearch,
            initDropzone,
            initModals,
            initAjaxActions,
            initAjaxForms,
            initCopyButtons,
            initBulkActions,
            initDriveLikeInteractions,
            initMicrostockSlotSection,
            initCollectionUi,
        ];
        initSteps.forEach(function (step) {
            try {
                step();
            } catch (e) {
                // A single failing init must not kill the rest of the UI.
                console.error('[file-manager] init step failed', step.name, e);
            }
        });
        if (spaShell) {
            try {
                initSpaRouter();
            } catch (e) {
                console.error('[file-manager] initSpaRouter failed', e);
            }
        }

        // ---- View toggle ----
        function initViewToggle() {
            const saved = localStorage.getItem(STORAGE_KEY) || 'table';
            applyViewMode(saved);

            viewToggleBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyViewMode(btn.dataset.fhViewBtn);
                });
            });
        }

        function applyViewMode(mode) {
            viewToggleBtns.forEach(function (btn) {
                btn.classList.toggle('is-active', btn.dataset.fhViewBtn === mode);
            });

            if (tableView) {
                tableView.classList.toggle('d-none', mode !== 'table');
            }
            if (gridView) {
                gridView.classList.toggle('d-none', mode !== 'grid');
            }

            localStorage.setItem(STORAGE_KEY, mode);
        }

        // ---- Live search ----
        function initSearch() {
            if (!searchInput || !searchResults) {
                return;
            }

            let debounceTimer = null;

            searchInput.addEventListener('input', function () {
                const query = searchInput.value.trim();

                clearTimeout(debounceTimer);

                if (query.length < 1) {
                    closeSearchResults();
                    return;
                }

                debounceTimer = setTimeout(function () {
                    performSearch(query);
                }, 300);
            });

            searchInput.addEventListener('focus', function () {
                if (searchInput.value.trim().length >= 1 && searchResults.children.length > 0) {
                    openSearchResults();
                }
            });

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    closeSearchResults();
                }
            });
        }

        async function performSearch(query) {
            const params = new URLSearchParams({ q: query });
            if (config.ownerUserId) {
                params.set('user_id', config.ownerUserId);
            }
            if (config.workspaceId) {
                params.set('workspace_id', config.workspaceId);
            }

            try {
                const response = await fetch(config.searchUrl + '?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json().catch(function () {
                    return { data: [] };
                });

                renderSearchResults(data.data || []);
            } catch (e) {
                // Network error — silently close
                closeSearchResults();
            }
        }

        function renderSearchResults(files) {
            searchResults.innerHTML = '';

            if (files.length === 0) {
                searchResults.innerHTML = '<div class="fh-search-result__empty">' + escapeHtml(config.messages.searchNoResults) + '</div>';
                openSearchResults();
                return;
            }

            files.forEach(function (file) {
                const item = document.createElement('a');
                item.className = 'fh-search-result';
                item.href = buildFileShowUrl(file.id);

                const iconClass = iconClassForMime(file.mime);
                item.innerHTML =
                    '<i class="bi ' + iconClass.icon + ' fh-search-result__icon ' + iconClass.color + '"></i>'
                    + '<div class="flex-grow-1 overflow-hidden">'
                    + '<div class="fh-search-result__name">' + escapeHtml(file.name || '') + '</div>'
                    + '</div>'
                    + '<span class="fh-search-result__meta">' + escapeHtml(formatSize(file.size)) + '</span>';

                searchResults.appendChild(item);
            });

            openSearchResults();
        }

        function openSearchResults() {
            searchResults.classList.add('is-open');
        }

        function closeSearchResults() {
            searchResults.classList.remove('is-open');
        }

        // ---- Dropzone ----
        function initDropzone() {
            if (!dropzone) {
                return;
            }

            const fileInput = dropzone.querySelector('[data-direct-upload-input]');
            if (!fileInput) {
                return;
            }

            dropzone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropzone.classList.add('is-dragging');
            });

            dropzone.addEventListener('dragleave', function (e) {
                if (e.target === dropzone) {
                    dropzone.classList.remove('is-dragging');
                }
            });

            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('is-dragging');

                if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            dropzone.addEventListener('click', function (e) {
                if (e.target === fileInput || e.target.closest('button, select, input')) {
                    return;
                }
                fileInput.click();
            });

            dropzone.addEventListener('keydown', function (e) {
                if (e.target === fileInput || e.target.closest('button, select, input')) {
                    return;
                }
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInput.click();
                }
            });
        }

        // ---- Modals (rename, share, move) ----
        function initModals() {
            bindPopulateModal('fhRenameFileModal', function (btn, modal) {
                modal.querySelector('#fhRenameFileForm').action = config.fileRenameBase + '/' + btn.dataset.id + '/rename';
                modal.querySelector('#fh-rename-file-name').value = btn.dataset.name || '';
            });

            bindPopulateModal('fhRenameFolderModal', function (btn, modal) {
                modal.querySelector('#fhRenameFolderForm').action = config.folderRenameBase + '/' + btn.dataset.id + '/rename';
                modal.querySelector('#fh-rename-folder-name').value = btn.dataset.name || '';
            });

            bindPopulateModal('fhShareFileModal', function (btn, modal) {
                const form = modal.querySelector('#fhShareFileForm');
                form.action = config.fileRenameBase + '/' + btn.dataset.id + '/visibility';
                const visSelect = modal.querySelector('#fh-share-file-vis');
                visSelect.value = btn.dataset.visibility || 'private';
                modal.querySelector('#fh-share-file-link').value = btn.dataset.shareUrl || '';
                syncShareLinkVisibility(visSelect, modal.querySelector('#fh-share-file-link-wrap'));
            });

            bindPopulateModal('fhShareFolderModal', function (btn, modal) {
                const form = modal.querySelector('#fhShareFolderForm');
                form.action = config.folderRenameBase + '/' + btn.dataset.id + '/visibility';
                const visSelect = modal.querySelector('#fh-share-folder-vis');
                visSelect.value = btn.dataset.visibility || 'private';
                modal.querySelector('#fh-share-folder-link').value = btn.dataset.shareUrl || '';
                syncShareLinkVisibility(visSelect, modal.querySelector('#fh-share-folder-link-wrap'));
            });

            bindPopulateModal('fhMoveFileModal', function (btn, modal) {
                const form = modal.querySelector('#fhMoveFileForm');
                // Multi-move from context menu uses dataset.fhMultiMoveKeys; tree still loads.
                if (btn.dataset.id === '__multi__' || form?.dataset?.fhMultiMoveKeys) {
                    if (form) {
                        form.action = (config.fileRenameBase || '') + '/__multi__/move';
                    }
                    loadMoveTree(modal, 'folder_id', null);
                    return;
                }
                form.action = config.fileRenameBase + '/' + btn.dataset.id + '/move';
                loadMoveTree(modal, 'folder_id', btn.dataset.id);
            });

            bindPopulateModal('fhMoveFolderModal', function (btn, modal) {
                modal.querySelector('#fhMoveFolderForm').action = config.folderRenameBase + '/' + btn.dataset.id + '/move';
                loadMoveTree(modal, 'parent_id', btn.dataset.id);
            });

            // Share visibility select toggles link display
            bindShareSelect('fh-share-file-vis', 'fh-share-file-link-wrap');
            bindShareSelect('fh-share-folder-vis', 'fh-share-folder-link-wrap');
        }

        function bindPopulateModal(modalId, populateFn) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            modal.addEventListener('show.bs.modal', function (e) {
                const btn = e.relatedTarget;
                if (!btn) {
                    return;
                }
                populateFn(btn, modal);
            });
        }

        function syncShareLinkVisibility(visSelect, linkWrap) {
            if (visSelect.value === config.visLink && linkWrap) {
                linkWrap.classList.remove('d-none');
            } else if (linkWrap) {
                linkWrap.classList.add('d-none');
            }
        }

        function bindShareSelect(selectId, wrapId) {
            const select = document.getElementById(selectId);
            const wrap = document.getElementById(wrapId);
            if (!select || !wrap) {
                return;
            }

            select.addEventListener('change', function () {
                syncShareLinkVisibility(select, wrap);
            });
        }

        async function loadMoveTree(modal, inputName, currentId) {
            const treeContainer = modal.querySelector('[data-fh-move-tree]');
            if (!treeContainer) {
                return;
            }

            treeContainer.innerHTML = '<div class="text-muted text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';

            const params = new URLSearchParams();
            if (config.ownerUserId) {
                params.set('user_id', config.ownerUserId);
            }
            if (config.workspaceId) {
                params.set('workspace_id', config.workspaceId);
            }

            try {
                const response = await fetch(config.browseUrl + '?' + params.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const data = await response.json().catch(function () {
                    return { data: {} };
                });

                renderMoveTree(treeContainer, data.data || {}, inputName, currentId, modal);
            } catch (e) {
                treeContainer.innerHTML = '<div class="text-danger small p-2">' + escapeHtml(config.messages.loadFailed) + '</div>';
            }
        }

        function renderMoveTree(container, browseData, inputName, currentId, modal) {
            const folders = browseData.folders || [];
            const hiddenInput = modal.querySelector('[data-fh-move-target]');
            hiddenInput.name = inputName;
            hiddenInput.value = '';

            container.innerHTML = '';

            // Root option
            const rootItem = document.createElement('div');
            rootItem.className = 'fh-move-tree__item fh-move-tree__root is-selected';
            rootItem.setAttribute('role', 'button');
            rootItem.setAttribute('tabindex', '0');
            rootItem.setAttribute('aria-current', 'true');
            rootItem.innerHTML = '<i class="bi bi-folder2-open fh-move-tree__icon" aria-hidden="true"></i> ' + escapeHtml(config.messages.rootLabel);
            rootItem.addEventListener('click', function () {
                selectMoveItem(container, rootItem);
                hiddenInput.value = '';
            });
            rootItem.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectMoveItem(container, rootItem);
                    hiddenInput.value = '';
                }
            });
            container.appendChild(rootItem);

            // Flatten folder tree for simple list (API returns top-level children;
            // user can navigate by moving into subfolders step by step)
            function appendFolder(folder, depth) {
                const item = document.createElement('div');
                item.className = 'fh-move-tree__item';
                if (folder.id === currentId) {
                    item.classList.add('is-current');
                }
                item.style.paddingLeft = (0.5 + depth * 1.25) + 'rem';

                const isSelected = config.currentFolderId === folder.id;
                if (isSelected) {
                    item.classList.add('is-selected');
                    item.setAttribute('aria-current', 'true');
                    hiddenInput.value = folder.id;
                }

                item.innerHTML = '<i class="bi bi-folder-fill fh-move-tree__icon" aria-hidden="true"></i> ' + escapeHtml(folder.name || '');

                if (!item.classList.contains('is-current')) {
                    item.setAttribute('role', 'button');
                    item.setAttribute('tabindex', '0');
                    item.addEventListener('click', function () {
                        selectMoveItem(container, item);
                        hiddenInput.value = folder.id;
                    });
                    item.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            selectMoveItem(container, item);
                            hiddenInput.value = folder.id;
                        }
                    });
                } else {
                    item.setAttribute('aria-disabled', 'true');
                }

                container.appendChild(item);
            }

            folders.forEach(function (folder) {
                appendFolder(folder, 0);
            });
        }

        function selectMoveItem(container, item) {
            container.querySelectorAll('.fh-move-tree__item').forEach(function (el) {
                el.classList.remove('is-selected');
                el.removeAttribute('aria-current');
            });
            item.classList.add('is-selected');
            item.setAttribute('aria-current', 'true');
        }

        // ---- AJAX actions ----
        function initAjaxActions() {
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-fh-ajax-action]');
                if (!btn) {
                    return;
                }

                e.preventDefault();
                handleAjaxAction(btn);
            });
        }

        async function handleAjaxAction(btn) {
            const action = btn.dataset.fhAjaxAction;
            const url = btn.dataset.fhActionUrl;
            const method = (btn.dataset.fhMethod || 'POST').toUpperCase();
            const confirmMsg = btn.dataset.fhConfirm;
            const softNavAfter = btn.dataset.fhSoftNavAfter;
            const userId = btn.dataset.fhUserId || config.ownerUserId || '';

            if (confirmMsg && !window.confirm(confirmMsg)) {
                return;
            }

            btn.disabled = true;
            setBusy(true);

            try {
                const body = JSON.stringify({
                    user_id: userId || undefined,
                    workspace_id: config.workspaceId || undefined,
                });

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: body,
                });

                const data = await response.json().catch(function () {
                    return {};
                });

                if (response.ok) {
                    showToast(data.message || config.messages.success, 'success');
                    if (softNavAfter && router) {
                        setTimeout(function () {
                            router.go(softNavAfter, { replace: true });
                        }, 200);
                        return;
                    }
                    // Soft path: never hard-reload when shell bound.
                    const row = btn.closest('tr, [data-fh-row]');
                    if (row && (action || '').includes('delete')) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(function () {
                            row.remove();
                        }, 300);
                    }
                    setTimeout(function () {
                        if (typeof root.__fhSoftRefresh === 'function') {
                            root.__fhSoftRefresh();
                        }
                    }, 250);
                } else {
                    showToast(data.message || config.messages.error, 'error');
                }
            } catch (e) {
                showToast(config.messages.networkError, 'error');
            } finally {
                btn.disabled = false;
                setBusy(false);
            }
        }

        // ---- AJAX forms (create folder, rename, share, move, workspace, show visibility) ----
        function initAjaxForms() {
            document.addEventListener('submit', function (e) {
                const form = e.target.closest('form');
                if (!form || form.hasAttribute('data-fh-native')) {
                    return;
                }
                const isFhForm = form.hasAttribute('data-fh-ajax-form')
                    || form.id === 'fhRenameFileForm'
                    || form.id === 'fhRenameFolderForm'
                    || form.id === 'fhShareFileForm'
                    || form.id === 'fhShareFolderForm'
                    || form.id === 'fhMoveFileForm'
                    || form.id === 'fhMoveFolderForm'
                    || form.closest('#fhNewFolderModal')
                    || form.closest('#fhCreateWorkspaceModal')
                    || form.closest('#fhAcceptInviteInline')
                    || form.closest('#fhTeamOffcanvas')
                    || form.closest('[data-fh-panel="show"]');
                if (!isFhForm) {
                    return;
                }
                e.preventDefault();
                submitFhForm(form);
            });
        }

        async function submitFhForm(form) {
            const confirmMsg = form.dataset.fhConfirm || form.querySelector('[data-fh-confirm]')?.dataset?.fhConfirm;
            if (confirmMsg && !window.confirm(confirmMsg)) {
                return;
            }

            // Context-menu multi-move: fan-out sequential PATCH to chosen folder.
            if (form.id === 'fhMoveFileForm' && form.dataset.fhMultiMoveKeys) {
                let keys = [];
                try {
                    keys = JSON.parse(form.dataset.fhMultiMoveKeys || '[]');
                } catch (err) {
                    keys = [];
                }
                const targetInput = form.querySelector('[data-fh-move-target]');
                const targetFolderId = targetInput?.value || null;
                const submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                setBusy(true);
                try {
                    const result = await moveItemsRequest({
                        keys,
                        targetFolderId: targetFolderId || null,
                        config,
                        csrfToken,
                        parseKey: function (key) {
                            return selection ? selection.parseKey(key) : null;
                        },
                    });
                    if (result.ok) {
                        showToast(result.message || config.messages.moved || config.messages.success, 'success');
                        const modalEl = form.closest('.modal');
                        if (modalEl && window.bootstrap?.Modal) {
                            window.bootstrap.Modal.getInstance(modalEl)?.hide();
                        }
                        delete form.dataset.fhMultiMoveKeys;
                        selected.clear();
                        if (selection) {
                            selection.syncDom();
                        }
                        root.__fhSoftRefresh();
                    } else {
                        showToast(result.message || config.messages.moveFailed || config.messages.error, 'error');
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    setBusy(false);
                }
                return;
            }

            const action = form.getAttribute('action') || window.location.href;
            const methodInput = form.querySelector('input[name="_method"]');
            const method = (methodInput?.value || form.method || 'POST').toUpperCase();
            const fd = new FormData(form);
            // Ensure workspace/user context on mutations.
            if (config.ownerUserId && !fd.has('user_id')) {
                fd.set('user_id', config.ownerUserId);
            }
            if (config.workspaceId && !fd.has('workspace_id')) {
                fd.set('workspace_id', config.workspaceId);
            }
            if (form.closest('#fhNewFolderModal')) {
                if (config.currentFolderId) {
                    fd.set('parent_id', config.currentFolderId);
                } else {
                    fd.delete('parent_id');
                }
            }

            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            setBusy(true);

            try {
                const response = await fetch(action, {
                    method: method === 'GET' ? 'GET' : 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: method === 'GET' ? undefined : fd,
                });
                const data = await response.json().catch(function () {
                    return {};
                });

                if (response.ok) {
                    showToast(data.message || config.messages.success, 'success');
                    // Close bootstrap modal if any.
                    const modalEl = form.closest('.modal');
                    if (modalEl && window.bootstrap?.Modal) {
                        window.bootstrap.Modal.getInstance(modalEl)?.hide();
                    }
                    form.reset?.();

                    const successMode = form.dataset.fhSuccess || '';
                    if (successMode === 'members') {
                        softRefetchMembers();
                        softRefetchActivity();
                        return;
                    }
                    if (successMode === 'soft-refresh-show') {
                        softRefetchShow();
                        return;
                    }

                    // Workspace create / accept invite → soft-nav into new workspace.
                    const newWs = data?.data?.id || data?.data?.workspace_id;
                    if (newWs && (action.includes('/workspaces') && !action.includes('/invites') || action.includes('invites/accept'))) {
                        const href = (config.browseUrl || '/file-hosting') + `?workspace_id=${encodeURIComponent(newWs)}`;
                        if (router) {
                            router.go(href, { replace: true });
                        } else {
                            window.location.href = href;
                        }
                        return;
                    }
                    if (action.includes('/archive')) {
                        if (router) {
                            router.go(config.browseUrl || '/file-hosting', { replace: true });
                        } else {
                            window.location.href = config.browseUrl || '/file-hosting';
                        }
                        return;
                    }

                    if (typeof root.__fhSoftRefresh === 'function') {
                        root.__fhSoftRefresh();
                    }
                    softRefetchMembers();
                    softRefetchActivity();
                } else {
                    const msg = data.message
                        || (data.errors && Object.values(data.errors).flat()[0])
                        || config.messages.error;
                    showToast(msg, 'error');
                }
            } catch (err) {
                showToast(config.messages.networkError, 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                setBusy(false);
            }
        }

        // ---- SPA client router ----
        function initSpaRouter() {
            router = createFhRouter({
                root: document,
                onNavigate: handleSpaNavigate,
            });
            // Initial route from current URL (SSR already painted; just sync state).
            const initial = parseFhUrl();
            syncConfigFromRoute(initial);
            if (initial.name === 'trash') {
                showPanel('trash');
            } else if (initial.name === 'show') {
                showPanel('show');
                currentShowFileId = initial.fileId;
            } else {
                showPanel('browse');
            }
            refreshWorkspaceSwitcher();
        }

        function syncConfigFromRoute(route) {
            config.currentFolderId = route.folderId || null;
            config.workspaceId = route.workspaceId || null;
            if (route.userId) {
                config.ownerUserId = route.userId;
            }
            config.membersUrl = config.workspaceId
                ? `/file-hosting/workspaces/${config.workspaceId}/members`
                : null;
            config.activityUrl = config.workspaceId
                ? `/file-hosting/workspaces/${config.workspaceId}/activity`
                : null;
            // Keep direct-upload context in sync via the flat data-* attrs the
            // uploader reads live per upload (buildInitContext). Empty -> remove attr
            // so it reads as null (root), never a stale folder.
            root.querySelectorAll('[data-direct-upload]').forEach((el) => {
                if (config.currentFolderId) {
                    el.dataset.folderId = config.currentFolderId;
                } else {
                    delete el.dataset.folderId;
                }
                if (config.workspaceId) {
                    el.dataset.workspaceId = config.workspaceId;
                } else {
                    delete el.dataset.workspaceId;
                }
                if (config.ownerUserId) {
                    el.dataset.userId = config.ownerUserId;
                }
            });
            const folderHidden = root.querySelector('#fhNewFolderModal input[name="parent_id"], #fhNewFolderModal [name="parent_id"]');
            if (folderHidden) {
                folderHidden.value = config.currentFolderId || '';
            }
            root.querySelectorAll('input[name="workspace_id"]').forEach((el) => {
                if (el.closest('form')) {
                    el.value = config.workspaceId || '';
                }
            });
        }

        // Keep the direct-upload root-upload/lock gate in sync with the browse
        // payload (microstock collections: no root upload, no upload into a
        // locked collection). Reads live data-* attrs the uploader checks.
        function syncCollectionUploadGate(rootEl, cfg, folder) {
            const kind = cfg?.currentWorkspace?.kind || '';
            const locked = folder && folder.collection_locked === true ? '1' : '0';
            rootEl.querySelectorAll('[data-direct-upload]').forEach((el) => {
                if (kind) {
                    el.dataset.workspaceKind = kind;
                } else {
                    delete el.dataset.workspaceKind;
                }
                el.dataset.collectionLocked = folder ? locked : '0';
            });
        }

        // ---- Workspace switcher client re-render (SPA soft-nav) ----
        function switcherBrowseUrl(wsId) {
            const base = config.browseUrl || '/file-hosting';
            const sep = base.indexOf('?') === -1 ? '?' : '&';
            return base + sep + 'workspace_id=' + encodeURIComponent(wsId);
        }

        function roleLabelFor(role) {
            if (!role) {
                return '';
            }
            const map = config.roleLabels || {};
            return map[role] || role;
        }

        // Rebuild dynamic workspace <a> items from lastWorkspaces (keeps personal
        // item + collab/microstock sections). Called after every switcher refetch
        // so newly created workspaces appear without a hard reload.
        function renderWorkspaceMenuItems() {
            const menu = document.querySelector('.fh-workspace-switcher .dropdown-menu');
            if (!menu) {
                return;
            }

            // Remove previously rendered dynamic items (keep personal "").
            menu.querySelectorAll('.dropdown-item[data-fh-workspace-id]').forEach(function (el) {
                if ((el.dataset.fhWorkspaceId || '') !== '') {
                    el.remove();
                }
            });
            menu.querySelectorAll('[data-fh-dynamic-workspaces-divider]').forEach(function (el) {
                el.remove();
            });

            const list = Array.isArray(lastWorkspaces) ? lastWorkspaces : [];
            if (list.length === 0) {
                return;
            }

            // Group: Microstock first, Team (and other kinds) second. Inserted
            // before the microstock slot mount; each group gets its own divider.
            const anchor = menu.querySelector('[data-fh-microstock-slot]') || null;
            const microstock = [];
            const team = [];
            list.forEach(function (w) {
                if (!w || !w.id) {
                    return;
                }
                ((w.kind || 'team') === 'microstock' ? microstock : team).push(w);
            });

            const groups = [microstock, team].filter(function (g) { return g.length > 0; });

            groups.forEach(function (group, gi) {
                const divider = document.createElement('div');
                divider.className = 'dropdown-divider';
                divider.setAttribute('data-fh-dynamic-workspaces-divider', '');
                menu.insertBefore(divider, anchor);

                group.forEach(function (w) {
                    const kind = w.kind || 'team';
                    const isOwner = w.is_owner === true;
                    const wsId = w.id;

                    const a = document.createElement('a');
                    a.href = switcherBrowseUrl(wsId);
                    a.className = 'dropdown-item d-flex align-items-center justify-content-between gap-2';
                    a.dataset.fhWorkspaceId = wsId;
                    a.dataset.fhWorkspaceName = w.name || 'Workspace';
                    a.dataset.fhWorkspaceKind = kind;

                    const left = document.createElement('span');
                    left.className = 'text-truncate';
                    const i = document.createElement('i');
                    i.className = 'bi ' + (kind === 'microstock' ? 'bi-images' : 'bi-people') + ' me-2';
                    left.appendChild(i);
                    left.appendChild(document.createTextNode(w.name || 'Workspace'));
                    a.appendChild(left);

                    const right = document.createElement('span');
                    right.className = 'd-flex gap-1 flex-shrink-0';
                    if (w.my_role) {
                        const roleBadge = document.createElement('span');
                        roleBadge.className = 'badge text-bg-secondary';
                        roleBadge.textContent = roleLabelFor(w.my_role);
                        right.appendChild(roleBadge);
                    }
                    const ownBadge = document.createElement('span');
                    ownBadge.className = 'badge ' + (isOwner ? 'text-bg-primary' : 'text-bg-info');
                    ownBadge.textContent = isOwner ? (config.badgeYours || 'Yours') : (config.badgeJoined || 'Joined');
                    right.appendChild(ownBadge);
                    a.appendChild(right);

                    menu.insertBefore(a, anchor);
                });
            });
        }

        function renderWorkspaceSwitcher() {
            const root2 = document;
            const label = root2.querySelector('.fh-workspace-switcher [data-fh-workspace-label]');
            const icon = root2.querySelector('.fh-workspace-switcher [data-fh-workspace-icon]');
            if (!label && !icon) {
                return;
            }
            const wsId = config.workspaceId || null;
            const personalLabel = config.personalLabel || 'Personal';

            if (wsId) {
                const entry = lastWorkspaces.find(function (w) { return w && w.id === wsId; }) || null;
                const item = root2.querySelector('.fh-workspace-switcher .dropdown-item[data-fh-workspace-id="' + wsId + '"]');
                const name = (entry && entry.name) || item?.dataset?.fhWorkspaceName || config.currentWorkspace?.name || null;
                const kind = (entry && entry.kind) || item?.dataset?.fhWorkspaceKind || config.currentWorkspace?.kind || 'team';
                if (label && name) {
                    label.textContent = name;
                }
                if (icon) {
                    icon.classList.remove('bi-person', 'bi-people', 'bi-images');
                    icon.classList.add(kind === 'microstock' ? 'bi-images' : 'bi-people');
                }
            } else {
                if (label) {
                    label.textContent = personalLabel;
                }
                if (icon) {
                    icon.classList.remove('bi-person', 'bi-people', 'bi-images');
                    icon.classList.add('bi-person');
                }
            }

            root2.querySelectorAll('.fh-workspace-switcher .dropdown-item[data-fh-workspace-id]').forEach(function (el) {
                const itemId = el.dataset.fhWorkspaceId || null;
                el.classList.toggle('active', (itemId || null) === (wsId || null));
            });
        }

        async function refreshWorkspaceSwitcher() {
            if (config.workspacesUrl) {
                const json = await fetchJson(config.workspacesUrl, csrfToken);
                const list = json?.data;
                if (Array.isArray(list)) {
                    lastWorkspaces = list;
                    config.workspaces = list;
                }
            }
            renderWorkspaceMenuItems();
            renderWorkspaceSwitcher();
            if (microstockMount) {
                refreshMicrostockSlotSection(microstockMount);
            }
        }

        async function handleSpaNavigate(route) {
            setLoading(true);
            try {
                const prevWs = config.workspaceId;
                syncConfigFromRoute(route);

                if (route.name === 'browse') {
                    // Cold load of show/trash may lack listing shell — hard nav once.
                    if (!root.querySelector('[data-fh-view="table"] tbody')) {
                        window.location.href = route.href;
                        return;
                    }
                    showPanel('browse');
                    if (config.realtime) {
                        config.realtime.page = 'index';
                        config.realtime.fileId = null;
                    }
                    currentShowFileId = null;
                    await softRefetchListing();
                    await softRefetchQuota();
                    if (prevWs !== config.workspaceId) {
                        rebindRealtime();
                        refreshWorkspaceSwitcher();
                        if (config.workspaceId) {
                            softRefetchMembers();
                            softRefetchActivity();
                        }
                    }
                    return;
                }

                if (route.name === 'trash') {
                    if (!root.querySelector('[data-fh-trash-body]')) {
                        window.location.href = route.href;
                        return;
                    }
                    showPanel('trash');
                    if (config.realtime) {
                        config.realtime.page = 'trash';
                        config.realtime.fileId = null;
                    }
                    currentShowFileId = null;
                    await softRefetchTrash();
                    if (prevWs !== config.workspaceId) {
                        rebindRealtime();
                        refreshWorkspaceSwitcher();
                    }
                    return;
                }

                if (route.name === 'show' && route.fileId) {
                    if (!root.querySelector('[data-fh-panel="show"]')) {
                        window.location.href = route.href;
                        return;
                    }
                    showPanel('show');
                    currentShowFileId = route.fileId;
                    if (config.realtime) {
                        config.realtime.page = 'show';
                        config.realtime.fileId = route.fileId;
                    }
                    await softRefetchShow();
                    // Channel is workspace/user based; page/fileId are live-read — no rebind needed.
                    return;
                }
            } finally {
                setLoading(false);
            }
        }

        // ---- Microstock standalone slot section (switcher dropdown) ----
        function initMicrostockSlotSection() {
            const mount = document.querySelector('[data-fh-microstock-slot]');
            if (!mount || !mount.dataset.slotsUrl) {
                return;
            }

            microstockMount = mount;
            refreshMicrostockSlotSection(mount);

            mount.addEventListener('click', async function (e) {
                const btn = e.target.closest('[data-fh-microstock-create]');
                if (!btn) {
                    return;
                }
                e.preventDefault();
                openMicrostockCreateModal();
            });

            const modalSubmit = document.querySelector('[data-fh-microstock-modal-submit]');
            if (modalSubmit) {
                modalSubmit.addEventListener('click', function (e) {
                    e.preventDefault();
                    const name = document.getElementById('fhMicrostockName')?.value?.trim() || '';
                    submitMicrostockCreate(mount, name);
                });
            }
        }

        async function refreshMicrostockSlotSection(mount) {
            const json = await fetchJson(mount.dataset.slotsUrl, csrfToken);
            const status = json?.data;
            mount.innerHTML = '';
            if (!status) {
                return;
            }

            if (!status.activated) {
                mount.innerHTML = '<a href="' + escapeHtml(mount.dataset.servicesUrl) + '" class="dropdown-item small">'
                    + '<i class="bi bi-images me-2"></i>' + escapeHtml(mount.dataset.labelActivate) + '</a>';
                return;
            }

            if (status.can_create) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'dropdown-item';
                btn.setAttribute('data-fh-microstock-create', '');
                btn.innerHTML = '<i class="bi bi-plus-lg me-2"></i>' + escapeHtml(mount.dataset.labelCreate);
                mount.appendChild(btn);
            } else {
                mount.innerHTML = '<span class="dropdown-item disabled text-muted small">'
                    + '<i class="bi bi-lock me-2"></i>' + escapeHtml(mount.dataset.labelFull) + '</span>';
            }
        }

        // Open the name modal instead of posting immediately.
        function openMicrostockCreateModal() {
            const modalEl = document.getElementById('fhCreateMicrostockModal');
            if (!modalEl) {
                return false;
            }
            const input = modalEl.querySelector('#fhMicrostockName');
            if (input) {
                input.value = '';
            }
            if (window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                if (input) {
                    modalEl.addEventListener('shown.bs.modal', function onShown() {
                        modalEl.removeEventListener('shown.bs.modal', onShown);
                        input.focus();
                    });
                }
            }
            return true;
        }

        function submitMicrostockCreate(mount, name) {
            const submitBtn = document.querySelector('[data-fh-microstock-modal-submit]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            setBusy(true);
            return fetch(mount.dataset.createUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name: name || null }),
            })
                .then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (json) {
                        return { ok: res.ok, json: json };
                    });
                })
                .then(function (out) {
                    const json = out.json;
                    if (out.ok && json?.data?.id) {
                        const modalEl = document.getElementById('fhCreateMicrostockModal');
                        if (modalEl && window.bootstrap?.Modal) {
                            window.bootstrap.Modal.getInstance(modalEl)?.hide();
                        }
                        showToast(json.message || config.messages.success, 'success');
                        const href = (config.browseUrl || '/file-hosting') + '?workspace_id=' + encodeURIComponent(json.data.id);
                        if (router) {
                            router.go(href, { replace: true });
                        } else {
                            window.location.href = href;
                        }
                    } else {
                        showToast(json.message || config.messages.error, 'error');
                        refreshMicrostockSlotSection(mount);
                    }
                })
                .catch(function () {
                    showToast(config.messages.networkError, 'error');
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    setBusy(false);
                });
        }

        // ---- Microstock collection UI (lock & describe / status / export) ----
        function collectionEls() {
            return {
                action: root.querySelector('[data-fh-collection-action]'),
                primary: root.querySelector('[data-fh-collection-primary]'),
                primaryLabel: root.querySelector('[data-fh-collection-primary-label]'),
                banner: root.querySelector('[data-fh-collection-banner]'),
                bannerText: root.querySelector('[data-fh-collection-banner-text]'),
                review: root.querySelector('[data-fh-collection-review]'),
            };
        }

        function isMicrostockContext() {
            return config.currentWorkspace?.kind === 'microstock';
        }

        function stopCollectionPolling() {
            if (collectionPollTimer !== null) {
                clearInterval(collectionPollTimer);
                collectionPollTimer = null;
            }
        }

        function startCollectionPolling() {
            if (collectionPollTimer !== null) {
                return;
            }
            collectionPollTimer = setInterval(function () {
                refreshCollectionStatus();
            }, 3000);
        }

        function progressText(processed, total, failed) {
            return formatCollectionProgress(processed, total, failed, {
                progress: config.microstockPanel?.progress,
                progressFailed: config.collectionLabels?.progressFailed,
            });
        }

        // Render the collection action button + status banner for the current
        // folder. `status` = browse.folder.collection_status (null => draft).
        // `counts` = { processed, total, failed, phase } for the processing progress line.
        function updateCollectionUi(status, counts) {
            const els = collectionEls();
            if (!els.action) {
                return;
            }
            const inCollection = isMicrostockContext() && !!config.currentFolderId;
            els.action.classList.toggle('d-none', !inCollection);

            if (!inCollection) {
                els.banner?.classList.add('d-none');
                stopCollectionPolling();
                return;
            }

            const labels = config.collectionLabels || {};
            const st = status || 'draft';
            config.collectionStatus = st;

            if (st === 'processing') {
                startCollectionPolling();
            } else {
                stopCollectionPolling();
            }

            // Banner text per status.
            let bannerText = null;
            let primaryLabel = labels.lockDescribe;
            let primaryMode = 'lock';
            if (st === 'processing') {
                const p = counts?.processed ?? null;
                const t = counts?.total ?? null;
                const f = counts?.failed ?? 0;
                bannerText = (t !== null && t > 0)
                    ? (labels.processing + ' — ' + progressText(p, t, f))
                    : labels.processing;
                if (counts?.phase === 'degraded') {
                    bannerText += ' ' + (labels.processingDegraded || '');
                }
                primaryLabel = labels.processing;
                primaryMode = 'none';
            } else if (st === 'review') {
                bannerText = labels.review;
                primaryLabel = labels.exportCsv;
                primaryMode = 'export';
            } else if (st === 'exported') {
                bannerText = labels.exported;
                primaryLabel = labels.downloadCsv;
                primaryMode = 'download';
            } else if (st === 'failed') {
                bannerText = labels.failed;
                primaryLabel = labels.lockDescribe;
                primaryMode = 'lock';
            } else if (st === 'locked') {
                bannerText = labels.locked;
                primaryLabel = labels.analyze || labels.processing;
                primaryMode = 'analyze';
            }

            if (els.banner && els.bannerText) {
                if (bannerText) {
                    const spinner = st === 'processing'
                        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'
                        : '';
                    els.bannerText.innerHTML = spinner;
                    els.bannerText.appendChild(document.createTextNode(bannerText));
                    els.banner.classList.remove('d-none');
                    els.banner.classList.toggle('alert-warning', st === 'processing' && counts?.phase === 'degraded');
                    els.banner.classList.toggle('alert-secondary', !(st === 'processing' && counts?.phase === 'degraded'));
                } else {
                    els.banner.classList.add('d-none');
                }
            }

            if (els.primary && els.primaryLabel) {
                els.primaryLabel.textContent = primaryLabel;
                els.primary.dataset.mode = primaryMode;
                els.primary.disabled = primaryMode === 'none';
                // Data-API fallback opens the modal only in lock mode.
                if (primaryMode === 'lock') {
                    els.primary.setAttribute('data-bs-toggle', 'modal');
                    els.primary.setAttribute('data-bs-target', '#fhCollectionDescribeModal');
                } else {
                    els.primary.removeAttribute('data-bs-toggle');
                    els.primary.removeAttribute('data-bs-target');
                }
            }

            // Secondary "Review files" button appears only in review state.
            if (els.review) {
                els.review.classList.toggle('d-none', st !== 'review');
            }
        }

        function collectionUrl(suffix) {
            return (config.collectionBase || '/file-hosting/microstock/collections')
                + '/' + encodeURIComponent(config.currentFolderId) + suffix;
        }

        function collectionPost(suffix, body) {
            return fetch(collectionUrl(suffix), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : null,
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (json) {
                    return { ok: res.ok, json: json };
                });
            });
        }

        function refreshCollectionStatus() {
            if (!config.currentFolderId) {
                return;
            }
            fetchJson(collectionUrl('/status'), csrfToken)
                .then(function (json) {
                    if (json?.data) {
                        updateCollectionUi(json.data.status, {
                            processed: json.data.processed,
                            total: json.data.total,
                            failed: json.data.failed,
                            phase: json.data.phase,
                        });
                        if (json.data.status === 'processing') {
                            softRefetchListing();
                        }
                    }
                })
                .catch(function () { /* best-effort */ });
        }

        function countFilesInListing() {
            return root.querySelectorAll('[data-fh-row][data-fh-entity="file"]').length;
        }

        function fetchMicrostockBilling() {
            const url = config.billingUrl || '/file-hosting/microstock/billing';
            return fetchJson(url, csrfToken).then(function (json) {
                return json?.data || null;
            }).catch(function () {
                return null;
            });
        }

        function renderCollectionBillingLine(billing, fileCount) {
            const el = document.querySelector('[data-fh-collection-billing]');
            if (!el || !billing) {
                return;
            }
            const tmpl = config.collectionLabels?.billingLine
                || 'Balance :balance · est. :estimate per file';
            const estimateTotal = (billing.estimated_per_analysis_usd || 0) * Math.max(1, fileCount);
            el.textContent = tmpl
                .replace(':balance', String(billing.balance_usd ?? '0'))
                .replace(':estimate', String(billing.estimated_per_analysis_usd ?? '0'))
                .replace(':total', estimateTotal.toFixed(2));
            el.classList.remove('d-none');
        }

        function initCollectionUi() {
            updateCollectionUi(config.collectionStatus || null);

            microstockPanel = createMicrostockPanel(config, csrfToken, {
                toast: showToast,
                onSaved: function () { refreshCollectionStatus(); },
            });

            // Per-file "Review" button in listing rows opens the panel.
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-fh-ms-review]');
                if (btn) {
                    e.preventDefault();
                    microstockPanel.open(btn.dataset.fhMsReview);
                    return;
                }
            });

            // Secondary "Review files" (review state): open first listed file.
            const reviewBtn = root.querySelector('[data-fh-collection-review]');
            if (reviewBtn) {
                reviewBtn.addEventListener('click', function () {
                    const first = root.querySelector('[data-fh-ms-review]');
                    if (first) {
                        microstockPanel.open(first.dataset.fhMsReview);
                    }
                });
            }

            const els = collectionEls();
            if (els.primary) {
                els.primary.addEventListener('click', function () {
                    const mode = els.primary.dataset.mode || 'lock';
                    if (mode === 'lock') {
                        const modalEl = document.getElementById('fhCollectionDescribeModal');
                        if (modalEl) {
                            const input = modalEl.querySelector('#fhCollectionHint');
                            if (input) {
                                input.value = '';
                            }
                            if (window.bootstrap?.Modal) {
                                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                            } else if (window.bootstrap?.Modal === undefined && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                new bootstrap.Modal(modalEl).show();
                            } else {
                                // Last-resort: reveal via data-API fallback classes.
                                modalEl.classList.add('show');
                                modalEl.style.display = 'block';
                                modalEl.removeAttribute('aria-hidden');
                            }
                        }
                    } else if (mode === 'export') {
                        setBusy(true);
                        collectionPost('/export')
                            .then(function (out) {
                                if (out.ok) {
                                    showToast(out.json.message || config.messages.success, 'success');
                                    refreshCollectionStatus();
                                } else {
                                    showToast(out.json.message || config.messages.error, 'error');
                                }
                            })
                            .catch(function () { showToast(config.messages.networkError, 'error'); })
                            .finally(function () { setBusy(false); });
                    } else if (mode === 'download') {
                        window.location.href = collectionUrl('/download');
                    } else if (mode === 'analyze') {
                        setBusy(true);
                        collectionPost('/analyze')
                            .then(function (out) {
                                if (out.ok) {
                                    showToast(out.json.message || config.messages.success, 'success');
                                    refreshCollectionStatus();
                                    softRefetchListing();
                                } else {
                                    showToast(out.json.message || config.messages.error, 'error');
                                }
                            })
                            .catch(function () { showToast(config.messages.networkError, 'error'); })
                            .finally(function () { setBusy(false); });
                    }
                });
            }

            const describeModal = document.getElementById('fhCollectionDescribeModal');
            if (describeModal) {
                describeModal.addEventListener('show.bs.modal', function () {
                    const fileCount = countFilesInListing();
                    fetchMicrostockBilling().then(function (billing) {
                        renderCollectionBillingLine(billing, fileCount);
                    });
                });
            }

            const describeSubmit = document.querySelector('[data-fh-collection-describe-submit]');
            if (describeSubmit) {
                describeSubmit.addEventListener('click', function () {
                    const hint = document.getElementById('fhCollectionHint')?.value?.trim() || '';
                    if (hint === '') {
                        showToast(config.messages.error, 'error');
                        return;
                    }
                    const fileCount = Math.max(1, countFilesInListing());
                    describeSubmit.disabled = true;
                    setBusy(true);
                    fetchMicrostockBilling()
                        .then(function (billing) {
                            if (billing) {
                                const requiredMicro = (billing.estimated_per_analysis_micro_usd || 0) * fileCount;
                                if ((billing.balance_micro_usd || 0) < requiredMicro) {
                                    const msg = config.collectionLabels?.insufficientCredits
                                        || 'Insufficient AI credits.';
                                    showToast(msg, 'error');
                                    describeSubmit.disabled = false;
                                    setBusy(false);
                                    return null;
                                }
                            }
                            return collectionPost('/lock', { hint: hint });
                        })
                        .then(function (out) {
                            if (out === null) {
                                return;
                            }
                            if (!out.ok) {
                                showToast(out.json.message || config.messages.error, 'error');
                                return;
                            }
                            return collectionPost('/analyze').then(function (a) {
                                const modalEl = document.getElementById('fhCollectionDescribeModal');
                                if (modalEl && window.bootstrap?.Modal) {
                                    window.bootstrap.Modal.getInstance(modalEl)?.hide();
                                }
                                if (a.ok) {
                                    showToast(a.json.message || config.messages.success, 'success');
                                } else {
                                    showToast(a.json.message || config.messages.error, 'error');
                                }
                                refreshCollectionStatus();
                                softRefetchListing();
                                syncCollectionUploadGate(root, config, { collection_locked: true });
                            });
                        })
                        .catch(function () { showToast(config.messages.networkError, 'error'); })
                        .finally(function () {
                            describeSubmit.disabled = false;
                            setBusy(false);
                        });
                });
            }
        }

        // ---- Copy buttons ----
        function initCopyButtons() {
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-fh-copy]');
                if (!btn) {
                    return;
                }

                e.preventDefault();
                const targetId = btn.dataset.fhCopy;
                const input = document.getElementById(targetId);
                if (!input || !input.value) {
                    return;
                }

                navigator.clipboard.writeText(input.value).then(function () {
                    showToast(config.messages.linkCopied, 'success');
                }).catch(function () {
                    input.select();
                    document.execCommand('copy');
                    showToast(config.messages.linkCopied, 'success');
                });
            });
        }

        // ---- Drive-like select / context menu / drag-move ----
        function initDriveLikeInteractions() {
            selection = createSelectionController(selected, root, {
                onChange: updateBulkBar,
            });

            async function moveItems(keys, targetFolderId) {
                return moveItemsRequest({
                    keys,
                    targetFolderId,
                    config,
                    csrfToken,
                    parseKey: function (key) {
                        return selection.parseKey(key);
                    },
                });
            }

            function openHref(href) {
                if (router && typeof router.go === 'function') {
                    router.go(href);
                } else if (typeof window.fileHostingNavigate === 'function') {
                    window.fileHostingNavigate(href);
                } else {
                    window.location.href = href;
                }
            }

            selection.bind(function (href) {
                openHref(href);
            });

            dragMove = createDragMove({
                root,
                selected,
                config,
                selection,
                moveItems,
                showToast,
                softRefresh: function () {
                    root.__fhSoftRefresh();
                },
                setBusy,
            });
            dragMove.bind();

            contextMenu = createContextMenu({
                root,
                selected,
                config,
                selection,
                openHref,
                moveItems,
                bulkFetch,
                showToast,
                softRefresh: function () {
                    root.__fhSoftRefresh();
                },
                csrfToken,
                loadMoveTree,
                setBusy,
            });
            contextMenu.bind();
        }

        // ---- Bulk selection ----
        function initBulkActions() {
            document.addEventListener('change', function (e) {
                const cb = e.target.closest('[data-fh-select]');
                if (!cb || !root.contains(cb)) {
                    return;
                }

                const key = cb.value;
                if (cb.checked) {
                    selected.add(key);
                } else {
                    selected.delete(key);
                }
                if (selection) {
                    selection.syncDom();
                } else {
                    syncRowState(cb);
                    updateBulkBar();
                }
            });

            document.addEventListener('change', function (e) {
                const selectAll = e.target.closest('[data-fh-select-all]');
                if (!selectAll || !root.contains(selectAll)) {
                    return;
                }

                root.querySelectorAll('[data-fh-select]').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                    const key = cb.value;
                    if (selectAll.checked) {
                        selected.add(key);
                    } else {
                        selected.delete(key);
                    }
                });
                if (selection) {
                    selection.syncDom();
                } else {
                    updateBulkBar();
                }
            });

            document.addEventListener('click', async function (e) {
                const btn = e.target.closest('[data-fh-bulk-action]');
                if (!btn) {
                    return;
                }

                const action = btn.dataset.fhBulkAction;
                if (selected.size === 0) {
                    return;
                }

                // Move: same multi-move modal path as context menu (no confirm dialog).
                if (action === 'move') {
                    if (contextMenu && typeof contextMenu.openMultiMovePicker === 'function') {
                        contextMenu.openMultiMovePicker(Array.from(selected));
                    } else {
                        showToast(config.messages?.error || 'Error', 'error');
                    }
                    return;
                }

                const confirmMsg = btn.dataset.fhConfirm;
                if (confirmMsg && !window.confirm(confirmMsg.replace('{n}', selected.size))) {
                    return;
                }

                const fileIds = [];
                const folderIds = [];
                selected.forEach(function (key) {
                    const sep = key.indexOf(':');
                    const type = key.slice(0, sep);
                    const id = key.slice(sep + 1);
                    if (type === 'file') {
                        fileIds.push(id);
                    } else if (type === 'folder') {
                        folderIds.push(id);
                    }
                });

                btn.disabled = true;
                setBusy(true);
                try {
                    const promises = [];

                    if (action === 'share' || action === 'unshare') {
                        const visibility = action === 'share' ? 'anyone_with_link' : 'private';
                        if (fileIds.length) {
                            promises.push(bulkFetch(config.bulkFileVisibilityUrl, 'POST', { ids: fileIds, visibility: visibility }));
                        }
                        if (folderIds.length) {
                            promises.push(bulkFetch(config.bulkFolderVisibilityUrl, 'POST', { ids: folderIds, visibility: visibility }));
                        }
                    } else if (action === 'delete') {
                        if (fileIds.length) {
                            promises.push(bulkFetch(config.bulkFileDeleteUrl, 'DELETE', { ids: fileIds }));
                        }
                        if (folderIds.length) {
                            promises.push(bulkFetch(config.bulkFolderDeleteUrl, 'DELETE', { ids: folderIds }));
                        }
                    }

                    const responses = await Promise.all(promises);
                    const data = responses.length ? (await responses[0].json().catch(function () { return {}; })) : {};
                    if (responses.every(function (r) { return r.ok; })) {
                        showToast(data.message || config.messages.success, 'success');
                        selected.clear();
                        setTimeout(function () {
                            if (typeof root.__fhSoftRefresh === 'function') {
                                root.__fhSoftRefresh();
                            } else {
                                window.location.reload();
                            }
                        }, 400);
                    } else {
                        showToast(data.message || config.messages.error, 'error');
                    }
                } catch (err) {
                    showToast(config.messages.networkError, 'error');
                } finally {
                    btn.disabled = false;
                    setBusy(false);
                }
            });
        }

        function bulkFetch(url, method, body) {
            return fetch(url, {
                method: method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({
                    user_id: config.ownerUserId || '',
                    workspace_id: config.workspaceId || '',
                }, body)),
            });
        }

        function syncRowState(cb) {
            const row = cb.closest('tr, .fh-file-card');
            if (row) {
                row.classList.toggle('is-selected', cb.checked);
            }
        }

        function updateBulkBar() {
            const bar = document.querySelector('[data-fh-bulk-bar]');
            if (!bar) {
                return;
            }
            const count = selected.size;
            bar.style.display = count > 0 ? '' : 'none';
            const countEl = bar.querySelector('[data-fh-bulk-count]');
            if (countEl) {
                countEl.textContent = count;
            }
        }

        // ---- Toast ----
        function showToast(message, type) {
            let container = document.querySelector('.fh-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'fh-toast-container';
                container.setAttribute('role', 'status');
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'false');
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'fh-toast is-' + (type || 'success');
            toast.setAttribute('role', 'status');

            const icon = type === 'error' ? 'bi-x-circle-fill' : 'bi-check-circle-fill';
            toast.innerHTML =
                '<i class="bi ' + icon + ' fh-toast__icon" aria-hidden="true"></i>'
                + '<div class="fh-toast__body">' + escapeHtml(message) + '</div>';

            container.appendChild(toast);

            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(function () {
                    toast.remove();
                }, 300);
            }, 3500);
        }

        // ---- Helpers ----
        function buildFileShowUrl(fileId) {
            const params = new URLSearchParams();
            if (config.ownerUserId) {
                params.set('user_id', config.ownerUserId);
            }
            if (config.workspaceId) {
                params.set('workspace_id', config.workspaceId);
            }
            const query = params.toString();
            return config.fileShowBase + '/' + fileId + (query ? '?' + query : '');
        }

        function iconClassForMime(mime) {
            mime = (mime || '').toLowerCase();
            if (mime.startsWith('image/')) {
                return { icon: 'bi-file-earmark-image', color: 'fh-icon--image' };
            }
            if (mime === 'application/pdf' || mime.startsWith('application/pdf')) {
                return { icon: 'bi-file-earmark-pdf', color: 'fh-icon--pdf' };
            }
            if (mime.startsWith('video/')) {
                return { icon: 'bi-file-earmark-play', color: 'fh-icon--video' };
            }
            if (mime.startsWith('audio/')) {
                return { icon: 'bi-file-earmark-music', color: 'fh-icon--audio' };
            }
            if (mime.includes('zip') || mime.includes('rar') || mime.includes('tar') || mime.includes('7z') || mime.includes('gz')) {
                return { icon: 'bi-file-earmark-zip', color: 'fh-icon--archive' };
            }
            if (mime.includes('spreadsheet') || mime.includes('excel') || mime.includes('csv') || mime.includes('sheet')) {
                return { icon: 'bi-file-earmark-spreadsheet', color: 'fh-icon--sheet' };
            }
            if (mime.includes('word') || mime.includes('document') || mime.includes('text/plain') || mime.includes('text/')) {
                return { icon: 'bi-file-earmark-text', color: 'fh-icon--doc' };
            }
            if (mime.includes('json') || mime.includes('javascript') || mime.includes('xml') || mime.includes('html') || mime.includes('css') || mime.includes('php')) {
                return { icon: 'bi-file-earmark-code', color: 'fh-icon--code' };
            }
            return { icon: 'bi-file-earmark', color: 'fh-icon--file' };
        }

        function formatSize(bytes) {
            bytes = parseInt(bytes, 10) || 0;
            if (bytes === 0) {
                return '—';
            }
            if (bytes < 1024) {
                return bytes + ' B';
            }
            if (bytes < 1024 * 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            }
            if (bytes < 1024 * 1024 * 1024) {
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            }
            return (bytes / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');
        }
}

document.querySelectorAll('[data-file-manager]').forEach(function (root) {
    initFileManager(root);
});

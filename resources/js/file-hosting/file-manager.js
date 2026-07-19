/**
 * File Hosting — AJAX file manager controller.
 *
 * Wraps around direct-upload.js (unchanged) and adds:
 * - AJAX CRUD (rename, visibility, delete, restore, force-delete, move)
 * - Live search dropdown
 * - Grid/list view toggle (persisted in localStorage)
 * - Move modal with folder tree
 * - Drag-drop zone enhancement
 * - Toast notifications
 *
 * All mutations go through web routes (FileManagerController) via fetch.
 * MonolithApi.handleResponse handles redirect/validation/error envelope.
 */
(function () {
    'use strict';

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

        initViewToggle();
        initSearch();
        initDropzone();
        initModals();
        initAjaxActions();
        initCopyButtons();
        initBulkActions();

        const selected = new Set();

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
                modal.querySelector('#fhMoveFileForm').action = config.fileRenameBase + '/' + btn.dataset.id + '/move';
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
            rootItem.innerHTML = '<i class="bi bi-folder2-open fh-move-tree__icon"></i> ' + escapeHtml(config.messages.rootLabel);
            rootItem.addEventListener('click', function () {
                selectMoveItem(container, rootItem);
                hiddenInput.value = '';
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
                    hiddenInput.value = folder.id;
                }

                item.innerHTML = '<i class="bi bi-folder-fill fh-move-tree__icon"></i> ' + escapeHtml(folder.name || '');

                if (!item.classList.contains('is-current')) {
                    item.addEventListener('click', function () {
                        selectMoveItem(container, item);
                        hiddenInput.value = folder.id;
                    });
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
            });
            item.classList.add('is-selected');
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
            const redirectAfter = btn.dataset.fhRedirect;
            const userId = btn.dataset.fhUserId || config.ownerUserId || '';

            if (confirmMsg && !window.confirm(confirmMsg)) {
                return;
            }

            btn.disabled = true;

            try {
                const body = userId ? JSON.stringify({ user_id: userId }) : undefined;

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
                    if (redirectAfter) {
                        setTimeout(function () {
                            window.location.href = redirectAfter;
                        }, 600);
                    } else if (data.redirect) {
                        setTimeout(function () {
                            window.location.href = data.redirect;
                        }, 600);
                    } else {
                        // Remove row or reload
                        const row = btn.closest('tr, [data-fh-row]');
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(function () {
                                row.remove();
                            }, 300);
                        } else {
                            setTimeout(function () {
                                window.location.reload();
                            }, 600);
                        }
                    }
                } else {
                    showToast(data.message || config.messages.error, 'error');
                }
            } catch (e) {
                showToast(config.messages.networkError, 'error');
            } finally {
                btn.disabled = false;
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

        // ---- Bulk selection ----
        function initBulkActions() {
            document.addEventListener('change', function (e) {
                const cb = e.target.closest('[data-fh-select]');
                if (!cb) {
                    return;
                }

                const key = cb.value;
                if (cb.checked) {
                    selected.add(key);
                } else {
                    selected.delete(key);
                }
                syncRowState(cb);
                updateBulkBar();
            });

            document.addEventListener('change', function (e) {
                const selectAll = e.target.closest('[data-fh-select-all]');
                if (!selectAll) {
                    return;
                }

                document.querySelectorAll('[data-fh-select]').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                    const key = cb.value;
                    if (selectAll.checked) {
                        selected.add(key);
                    } else {
                        selected.delete(key);
                    }
                    syncRowState(cb);
                });
                updateBulkBar();
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
                        setTimeout(function () { window.location.reload(); }, 600);
                    } else {
                        showToast(data.message || config.messages.error, 'error');
                    }
                } catch (err) {
                    showToast(config.messages.networkError, 'error');
                } finally {
                    btn.disabled = false;
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
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'fh-toast is-' + (type || 'success');

            const icon = type === 'error' ? 'bi-x-circle-fill' : 'bi-check-circle-fill';
            toast.innerHTML =
                '<i class="bi ' + icon + ' fh-toast__icon"></i>'
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
})();

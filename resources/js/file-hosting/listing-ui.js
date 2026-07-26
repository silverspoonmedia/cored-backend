/**
 * File Hosting listing DOM helpers — soft-refetch floor + light single-entity patch.
 *
 * Table column order MUST stay in lockstep with Blade SSR thead:
 * select | icon | name | type | size | region | visibility | actions
 */

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function formatBytes(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) {
        return `${n} B`;
    }
    if (n < 1024 * 1024) {
        return `${(n / 1024).toFixed(1)} KB`;
    }
    if (n < 1024 * 1024 * 1024) {
        return `${(n / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${(n / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}

/**
 * Soft-refetch: full page navigation is correct but heavy.
 * Prefer replacing tbody + grid from browse JSON when containers exist.
 *
 * @param {HTMLElement} root
 * @param {object} browseData  API browse payload
 * @param {object} config
 */
export function applyBrowsePayload(root, browseData, config) {
    if (!browseData || !root) {
        return false;
    }

    // Keep workspace context fresh across SPA soft-nav so microstock gating
    // (badges, review buttons, collection UI) reflects the folder being viewed.
    if (browseData.workspace) {
        config.currentWorkspace = browseData.workspace;
    }

    const folders = browseData.folders || [];
    const files = browseData.files || [];
    const crumbs = browseData.breadcrumb || [];
    // Derive parent for "..." row (second-to-last crumb when nested).
    const parentMeta = parentFolderMeta(config, crumbs);
    config.parentFolderId = parentMeta.show ? parentMeta.parentId : null;

    const tbody = root.querySelector('[data-fh-view="table"] tbody');
    const grid = root.querySelector('[data-fh-view="grid"]');

    if (!tbody && !grid) {
        // No listing surface on this page — do not hard-reload (caller decides).
        return false;
    }

    if (tbody) {
        tbody.innerHTML = renderTableRows(folders, files, config, crumbs);
    }
    if (grid) {
        const cards = renderGridCards(folders, files, config, crumbs);
        if (folders.length === 0 && files.length === 0 && !parentMeta.show) {
            grid.innerHTML = emptyStateHtml(config);
        } else if (folders.length === 0 && files.length === 0 && parentMeta.show) {
            grid.innerHTML = `<div class="fh-grid">${cards}</div>`;
        } else {
            grid.innerHTML = `<div class="fh-grid">${cards}</div>`;
        }
    }

    applyBreadcrumb(root, crumbs, config);

    // Clear bulk selection after full replace
    root.querySelectorAll('[data-fh-select]').forEach((cb) => {
        cb.checked = false;
    });
    const selectAll = root.querySelector('[data-fh-select-all]');
    if (selectAll) {
        selectAll.checked = false;
    }
    const bulkBar = root.querySelector('[data-fh-bulk-bar]');
    if (bulkBar) {
        bulkBar.style.display = 'none';
    }

    return true;
}

/**
 * Soft-update breadcrumb trail after folder soft-nav.
 *
 * @param {HTMLElement} root
 * @param {Array<{id: string|null, name: string}>} crumbs
 * @param {object} config
 */
export function applyBreadcrumb(root, crumbs, config) {
    const nav = root.querySelector('[data-fh-breadcrumb]');
    if (!nav) {
        return;
    }

    const browseBase = config.browseUrl || '/file-hosting';
    const params = new URLSearchParams();
    if (config.ownerUserId) {
        params.set('user_id', config.ownerUserId);
    }
    if (config.workspaceId) {
        params.set('workspace_id', config.workspaceId);
    }
    const qs = (extra) => {
        const p = new URLSearchParams(params);
        if (extra?.folder_id) {
            p.set('folder_id', extra.folder_id);
        }
        const s = p.toString();
        return s ? `?${s}` : '';
    };

    const rootLabel = config.messages?.rootLabel || 'My Files';
    const parts = [];
    const list = Array.isArray(crumbs) ? crumbs : [];

    // First crumb is often root (id null). Non-active crumbs are drag-move drop targets.
    list.forEach((crumb, index) => {
        const isLast = index === list.length - 1;
        const id = crumb?.id ?? null;
        const name = crumb?.name || (id ? '' : rootLabel);
        const icon = index === 0
            ? '<i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>'
            : '';
        if (index > 0) {
            parts.push('<i class="bi bi-chevron-right fh-breadcrumb__sep"></i>');
        }
        if (isLast) {
            parts.push(
                `<span class="fh-breadcrumb__item is-active">${icon}${escapeHtml(name)}</span>`,
            );
        } else {
            const href = browseBase + qs(id ? { folder_id: id } : {});
            const drop = id ? escapeHtml(String(id)) : 'root';
            parts.push(
                `<a href="${escapeHtml(href)}" class="fh-breadcrumb__item" data-fh-soft-nav data-fh-drop-folder="${drop}">${icon}${escapeHtml(name)}</a>`,
            );
        }
    });

    if (parts.length === 0) {
        parts.push(
            `<span class="fh-breadcrumb__item is-active"><i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>${escapeHtml(rootLabel)}</span>`,
        );
    }

    nav.innerHTML = parts.join('');
}

/**
 * Insert or update a single file row from complete-upload JSON (optimistic SPA patch).
 *
 * @param {HTMLElement} root
 * @param {object} file  HostedFileResource shape
 * @param {object} config
 * @returns {boolean}
 */
export function upsertFileRow(root, file, config) {
    if (!root || !file?.id) {
        return false;
    }

    const existing = root.querySelector(
        `[data-fh-entity="file"][data-fh-id="${CSS.escape(String(file.id))}"]`,
    );
    if (existing) {
        const nameEl = existing.querySelector('[data-fh-name], .fh-table__name-text, .fh-file-card__name');
        if (nameEl && file.name) {
            nameEl.textContent = file.name;
        }
        return true;
    }

    const tbody = root.querySelector('[data-fh-view="table"] tbody');
    if (tbody) {
        const empty = tbody.querySelector('[data-fh-empty]');
        if (empty) {
            empty.remove();
        }
        // Drop SSR empty row (colspan row without data-fh-empty)
        if (!tbody.querySelector('[data-fh-row]')) {
            tbody.innerHTML = '';
        }
        tbody.insertAdjacentHTML('beforeend', tableFileRow(file, config));
    }

    const grid = root.querySelector('[data-fh-view="grid"]');
    if (grid) {
        let shell = grid.querySelector('.fh-grid');
        if (!shell) {
            grid.innerHTML = '<div class="fh-grid"></div>';
            shell = grid.querySelector('.fh-grid');
        }
        const empty = grid.querySelector('.fh-empty, [data-fh-empty]');
        if (empty) {
            empty.remove();
        }
        shell.insertAdjacentHTML('beforeend', gridFileCard(file, config));
    }

    return Boolean(tbody || grid);
}

/**
 * @param {object} payload  listing.changed event
 * @param {HTMLElement} root
 * @param {object} config
 * @returns {boolean} true if handled without full refetch
 */
export function tryPatchListing(payload, root, config) {
    if (!payload || !root) {
        return false;
    }

    const action = payload.action || '';
    const entityType = payload.entity_type || '';
    const entityId = payload.entity_id;
    const currentFolder = config.currentFolderId || null;
    const eventFolder = payload.folder_id ?? null;
    const fromFolder = payload.from_folder_id ?? null;

    // Bulk / cascade / missing id → refetch
    if (action.startsWith('bulk_') || !entityId || Array.isArray(payload.entity_ids)) {
        return false;
    }

    // Move across folders: only patch if current view is source or destination
    if (action === 'move') {
        const inView = sameFolder(currentFolder, eventFolder) || sameFolder(currentFolder, fromFolder);
        if (!inView) {
            return true; // ignore — other folder
        }
        return false; // refetch current folder
    }

    // Soft-delete / force-delete: remove rows if present
    if (action === 'soft_delete' || action === 'force_delete') {
        return removeEntityRows(root, entityType, entityId);
    }

    // Restore / create: only if parent matches current folder
    if (action === 'create' || action === 'restore') {
        if (!sameFolder(currentFolder, eventFolder)) {
            return true; // ignore
        }
        // Prefer refetch so actions/permissions match Blade
        return false;
    }

    // Rename / visibility: update in place if row exists
    if (action === 'rename' || action === 'visibility') {
        const resource = payload.resource;
        if (!resource) {
            return false;
        }
        return updateEntityNameVisibility(root, entityType, entityId, resource, config);
    }

    return false;
}

function sameFolder(a, b) {
    const na = a == null || a === '' ? null : String(a);
    const nb = b == null || b === '' ? null : String(b);
    return na === nb;
}

function removeEntityRows(root, entityType, entityId) {
    const rows = root.querySelectorAll(
        `[data-fh-entity="${entityType}"][data-fh-id="${CSS.escape(String(entityId))}"]`,
    );
    if (!rows.length) {
        return false;
    }
    rows.forEach((row) => {
        row.style.transition = 'opacity 0.2s';
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 200);
    });
    return true;
}

function updateEntityNameVisibility(root, entityType, entityId, resource, config) {
    const rows = root.querySelectorAll(
        `[data-fh-entity="${entityType}"][data-fh-id="${CSS.escape(String(entityId))}"]`,
    );
    if (!rows.length) {
        return false;
    }

    const name = resource.name || '';
    const visibility = resource.visibility || 'private';

    rows.forEach((row) => {
        const nameEl = row.querySelector('[data-fh-name], .fh-table__name-text, .fh-file-card__name');
        if (nameEl && name) {
            nameEl.textContent = name;
        }

        const badgeHtml = visibilityBadgeHtml(visibility, config);
        const tableBadgeCell = row.querySelector('[data-fh-visibility-cell]');
        if (tableBadgeCell) {
            tableBadgeCell.innerHTML = badgeHtml;
            return;
        }
        const cardBadge = row.querySelector('.fh-file-card__badge');
        if (cardBadge) {
            cardBadge.innerHTML = badgeHtml;
        }
    });

    return true;
}

export function applyQuotaPayload(mount, quota, labels) {
    if (!mount || !quota) {
        return;
    }

    const used = Number(quota.used_bytes) || 0;
    const limit = Number(quota.quota_bytes) || 0;
    const state = String(quota.state || 'free');
    const pct = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;
    const fillClass = pct >= 90 ? 'is-danger' : (pct >= 75 ? 'is-warn' : '');
    const usedLabel = quota.used_bytes_human || formatBytes(used);
    const limitLabel = quota.quota_bytes_human || formatBytes(limit);

    const stateLabel = labels?.states?.[state]
        || labels?.states?.free
        || state;
    const stateClass = state === 'paid'
        ? 'text-bg-success'
        : (state === 'grace' ? 'text-bg-warning' : 'text-bg-secondary');

    const unlimited = labels?.unlimited || 'Unlimited';
    const usedText = labels?.used || 'Used';

    mount.innerHTML = `
<div class="fh-quota">
  <div class="fh-quota__header">
    <span class="fh-quota__label">
      ${escapeHtml(usedText)}
      <span class="badge ${stateClass} ms-1" style="font-size: .65rem; vertical-align: middle;">${escapeHtml(stateLabel)}</span>
    </span>
    <span class="fh-quota__value">
      ${escapeHtml(usedLabel)}
      / ${limit > 0 ? escapeHtml(limitLabel) : escapeHtml(unlimited)}
    </span>
  </div>
  <div class="fh-quota__track">
    <div class="fh-quota__fill ${fillClass}" style="width: ${limit > 0 ? pct : 3}%"></div>
  </div>
</div>`;
}

export function applyFileStats(root, payload) {
    if (!payload) {
        return;
    }
    const views = root.querySelector('[data-fh-stat="views"]');
    const downloads = root.querySelector('[data-fh-stat="downloads"]');
    if (views && payload.view_count != null) {
        views.textContent = String(payload.view_count);
    }
    if (downloads && payload.download_count != null) {
        downloads.textContent = String(payload.download_count);
    }
}

function emptyStateHtml(config) {
    const msg = config.messages?.emptyFolder || config.messages?.searchNoResults || 'Empty';
    return `<div class="fh-empty" data-fh-empty>
  <i class="bi bi-folder2-open fh-empty__icon"></i>
  <div class="fh-empty__text">${escapeHtml(msg)}</div>
</div>`;
}

function showRegionColumn(config) {
    return config.showRegion === true;
}

function tableColspan(config) {
    // select | icon | name | type | size | [region] | visibility | actions
    return showRegionColumn(config) ? 8 : 7;
}

/**
 * Virtual "..." parent row metadata.
 * Parent id = second-to-last breadcrumb crumb (null = drive root).
 *
 * @param {object} config
 * @param {Array<{id: string|null, name?: string}>} [crumbs]
 * @returns {{ show: boolean, parentId: string|null, href: string, drop: string, title: string }}
 */
export function parentFolderMeta(config, crumbs) {
    const currentId = config.currentFolderId || null;
    const title = config.messages?.parentFolder || 'Parent folder';
    if (!currentId) {
        return { show: false, parentId: null, href: rootHref(config), drop: 'root', title };
    }

    const list = Array.isArray(crumbs) ? crumbs : [];
    let parentId = null;
    if (list.length >= 2) {
        parentId = list[list.length - 2]?.id ?? null;
    }
    // Normalize empty string to null (root).
    if (parentId === '' || parentId === undefined) {
        parentId = null;
    }

    const href = parentId ? folderHref(parentId, config) : rootHref(config);
    const drop = parentId ? String(parentId) : 'root';

    return { show: true, parentId, href, drop, title };
}

function rootHref(config) {
    const base = config.browseUrl || window.location.pathname;
    const u = new URL(base, window.location.origin);
    u.searchParams.delete('folder_id');
    if (config.workspaceId) {
        u.searchParams.set('workspace_id', config.workspaceId);
    } else {
        u.searchParams.delete('workspace_id');
    }
    if (config.ownerUserId) {
        u.searchParams.set('user_id', config.ownerUserId);
    } else {
        u.searchParams.delete('user_id');
    }
    return u.pathname + u.search;
}

function tableParentRow(meta, config) {
    const href = escapeHtml(meta.href);
    const drop = escapeHtml(meta.drop);
    const title = escapeHtml(meta.title);
    const region = folderRegionCellHtml(config);
    return `<tr data-fh-row data-fh-entity="parent" data-fh-drop-folder="${drop}" class="fh-parent-row" title="${title}">
<td class="fh-table__select-cell"></td>
<td class="fh-table__icon-cell"><a href="${href}" data-fh-soft-nav aria-label="${title}"><i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i></a></td>
<td><a href="${href}" class="fh-table__name" data-fh-soft-nav title="${title}"><span class="fh-table__name-text">...</span></a></td>
<td class="fh-table__type text-muted">${escapeHtml(title)}</td>
<td class="fh-table__size">&mdash;</td>
${region}
<td></td>
<td class="fh-table__actions"></td>
</tr>`;
}

function gridParentCard(meta, config) {
    const href = escapeHtml(meta.href);
    const drop = escapeHtml(meta.drop);
    const title = escapeHtml(meta.title);
    return `<div class="fh-file-card is-parent" data-fh-row data-fh-entity="parent" data-fh-drop-folder="${drop}" title="${title}">
<div class="fh-file-card__icon"><a href="${href}" data-fh-soft-nav aria-label="${title}"><i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i></a></div>
<a href="${href}" class="fh-file-card__name" data-fh-soft-nav title="${title}">...</a>
<div class="fh-file-card__meta">${escapeHtml(title)}</div>
</div>`;
}

function renderTableRows(folders, files, config, crumbs) {
    const parts = [];
    const meta = parentFolderMeta(config, crumbs);
    if (meta.show) {
        parts.push(tableParentRow(meta, config));
    }
    folders.forEach((f) => {
        parts.push(tableFolderRow(f, config));
    });
    files.forEach((f) => {
        parts.push(tableFileRow(f, config));
    });
    if (parts.length === 0 || (parts.length === 1 && meta.show && folders.length === 0 && files.length === 0)) {
        if (!meta.show) {
            const msg = escapeHtml(config.messages?.emptyFolder || 'Empty');
            return `<tr data-fh-empty><td colspan="${tableColspan(config)}">
  <div class="fh-empty">
    <i class="bi bi-folder2-open fh-empty__icon"></i>
    <div class="fh-empty__text">${msg}</div>
  </div>
</td></tr>`;
        }
        // Nested empty: keep "..." + empty hint row.
        const msg = escapeHtml(config.messages?.emptyFolder || 'Empty');
        parts.push(`<tr data-fh-empty><td colspan="${tableColspan(config)}">
  <div class="fh-empty fh-empty--compact">
    <div class="fh-empty__text">${msg}</div>
  </div>
</td></tr>`);
    }
    return parts.join('');
}

function renderGridCards(folders, files, config, crumbs) {
    const parts = [];
    const meta = parentFolderMeta(config, crumbs);
    if (meta.show) {
        parts.push(gridParentCard(meta, config));
    }
    folders.forEach((f) => {
        parts.push(gridFolderCard(f, config));
    });
    files.forEach((f) => {
        parts.push(gridFileCard(f, config));
    });
    return parts.join('');
}

function folderHref(id, config) {
    const base = config.browseUrl || window.location.pathname;
    const u = new URL(base, window.location.origin);
    u.searchParams.set('folder_id', id);
    if (config.workspaceId) {
        u.searchParams.set('workspace_id', config.workspaceId);
    }
    if (config.ownerUserId) {
        u.searchParams.set('user_id', config.ownerUserId);
    }
    return u.pathname + u.search;
}

function fileHref(id, config) {
    const base = config.fileShowBase || '/file-hosting/files';
    const u = new URL(`${base}/${id}`, window.location.origin);
    if (config.workspaceId) {
        u.searchParams.set('workspace_id', config.workspaceId);
    }
    if (config.ownerUserId) {
        u.searchParams.set('user_id', config.ownerUserId);
    }
    return u.pathname + u.search;
}

function visibilityBadgeHtml(visibility, config) {
    const visLink = config.visLink || 'anyone_with_link';
    const isLink = visibility === visLink;
    const labels = config.messages?.visibility || {};
    if (isLink) {
        const label = escapeHtml(labels.anyone_with_link || 'Anyone with the link');
        return `<span class="fh-badge fh-badge--link" data-fh-visibility="${escapeHtml(visibility)}">
  <i class="bi bi-globe"></i> ${label}
</span>`;
    }
    const label = escapeHtml(labels.private || 'Restricted');
    return `<span class="fh-badge fh-badge--private" data-fh-visibility="${escapeHtml(visibility || 'private')}">
  <i class="bi bi-lock"></i> ${label}
</span>`;
}

function regionCellHtml(file, config) {
    if (!showRegionColumn(config)) {
        return '';
    }
    const key = file.region || null;
    if (!key) {
        return '<td class="fh-table__region"><span class="text-muted">—</span></td>';
    }
    const label = config.messages?.regions?.[key] || key;
    return `<td class="fh-table__region"><span class="badge text-bg-secondary fh-region-badge">${escapeHtml(label)}</span></td>`;
}

function folderRegionCellHtml(config) {
    if (!showRegionColumn(config)) {
        return '';
    }
    return '<td class="fh-table__region"><span class="text-muted">—</span></td>';
}

function mimeIcon(mime, isFolder) {
    if (isFolder) {
        return { icon: 'bi-folder-fill', color: 'fh-icon--folder' };
    }
    const m = String(mime || '').toLowerCase();
    if (m.startsWith('image/')) {
        return { icon: 'bi-file-earmark-image', color: 'fh-icon--image' };
    }
    if (m === 'application/pdf') {
        return { icon: 'bi-file-earmark-pdf', color: 'fh-icon--pdf' };
    }
    if (m.startsWith('video/')) {
        return { icon: 'bi-file-earmark-play', color: 'fh-icon--video' };
    }
    if (m.startsWith('audio/')) {
        return { icon: 'bi-file-earmark-music', color: 'fh-icon--audio' };
    }
    if (['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'].includes(m)) {
        return { icon: 'bi-file-earmark-zip', color: 'fh-icon--archive' };
    }
    if (['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'].includes(m)) {
        return { icon: 'bi-file-earmark-spreadsheet', color: 'fh-icon--sheet' };
    }
    if (['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'].includes(m)) {
        return { icon: 'bi-file-earmark-text', color: 'fh-icon--doc' };
    }
    if (['application/json', 'text/html', 'text/css', 'application/xml', 'text/xml', 'application/javascript', 'text/javascript', 'application/x-httpd-php'].includes(m)) {
        return { icon: 'bi-file-earmark-code', color: 'fh-icon--code' };
    }
    return { icon: 'bi-file-earmark', color: 'fh-icon--file' };
}

function iconHtml(mime, isFolder) {
    const { icon, color } = mimeIcon(mime, isFolder);
    return `<i class="bi ${icon} ${color}"></i>`;
}

function actionLabel(config, key, fallback) {
    return escapeHtml(config.messages?.actions?.[key] || fallback);
}

function entityBase(config, entityType) {
    if (entityType === 'folder') {
        return config.folderBase || config.folderRenameBase || '/file-hosting/folders';
    }
    return config.fileBase || config.fileRenameBase || config.fileShowBase || '/file-hosting/files';
}

function destroyUrl(entityType, id, config) {
    return `${entityBase(config, entityType)}/${id}`;
}

function listingRedirectUrl(config) {
    const base = config.browseUrl || window.location.pathname;
    const u = new URL(base, window.location.origin);
    if (config.currentFolderId) {
        u.searchParams.set('folder_id', config.currentFolderId);
    } else {
        u.searchParams.delete('folder_id');
    }
    if (config.workspaceId) {
        u.searchParams.set('workspace_id', config.workspaceId);
    }
    if (config.ownerUserId) {
        u.searchParams.set('user_id', config.ownerUserId);
    }
    return u.pathname + u.search;
}

function actionBtn({ title, icon, classes = '', attrs = {} }) {
    const attrStr = Object.entries(attrs)
        .map(([k, v]) => `${k}="${escapeHtml(v)}"`)
        .join(' ');
    return `<button type="button" class="fh-table__action-btn${classes ? ` ${classes}` : ''}" title="${title}" ${attrStr}><i class="bi ${icon}"></i></button>`;
}

/**
 * Table actions — mirrors Blade index row (view / rename / move / share / delete).
 * Grid keeps the lighter set Blade uses (view + rename + delete).
 */
function folderActionsHtml(folder, config, { compact = false } = {}) {
    const perms = config.permissions || {};
    const id = String(folder.id);
    const name = folder.name || '';
    const vis = folder.visibility || 'private';
    const shareUrl = folder.share_url || '';
    const parts = [];

    if (perms.editFolder) {
        parts.push(actionBtn({
            title: actionLabel(config, 'rename', 'Rename'),
            icon: 'bi-pencil',
            attrs: {
                'data-bs-toggle': 'modal',
                'data-bs-target': '#fhRenameFolderModal',
                'data-id': id,
                'data-name': name,
            },
        }));
        if (!compact) {
            parts.push(actionBtn({
                title: actionLabel(config, 'move', 'Move'),
                icon: 'bi-folder-symlink',
                attrs: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#fhMoveFolderModal',
                    'data-id': id,
                },
            }));
            parts.push(actionBtn({
                title: actionLabel(config, 'share', 'Share'),
                icon: 'bi-share',
                attrs: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#fhShareFolderModal',
                    'data-id': id,
                    'data-visibility': vis,
                    'data-share-url': shareUrl,
                },
            }));
        }
    }

    if (perms.deleteFolder) {
        parts.push(actionBtn({
            title: actionLabel(config, 'delete', 'Delete'),
            icon: 'bi-trash',
            classes: 'is-danger',
            attrs: {
                'data-fh-ajax-action': 'delete-folder',
                'data-fh-action-url': destroyUrl('folder', id, config),
                'data-fh-method': 'DELETE',
                'data-fh-confirm': config.messages?.actions?.confirmDeleteFolder || 'Move this folder to trash?',
                'data-fh-redirect': listingRedirectUrl(config),
            },
        }));
    }

    return parts.join('');
}

function fileActionsHtml(file, config, { compact = false } = {}) {
    const perms = config.permissions || {};
    const id = String(file.id);
    const name = file.name || '';
    const vis = file.visibility || 'private';
    const shareUrl = file.share_url || '';
    const href = fileHref(file.id, config);
    const parts = [];

    parts.push(
        `<a href="${href}" class="fh-table__action-btn" title="${actionLabel(config, 'view', 'View')}"><i class="bi bi-eye"></i></a>`,
    );

    if (perms.editFile) {
        parts.push(actionBtn({
            title: actionLabel(config, 'rename', 'Rename'),
            icon: 'bi-pencil',
            attrs: {
                'data-bs-toggle': 'modal',
                'data-bs-target': '#fhRenameFileModal',
                'data-id': id,
                'data-name': name,
            },
        }));
        if (!compact) {
            parts.push(actionBtn({
                title: actionLabel(config, 'move', 'Move'),
                icon: 'bi-folder-symlink',
                attrs: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#fhMoveFileModal',
                    'data-id': id,
                },
            }));
            parts.push(actionBtn({
                title: actionLabel(config, 'share', 'Share'),
                icon: 'bi-share',
                attrs: {
                    'data-bs-toggle': 'modal',
                    'data-bs-target': '#fhShareFileModal',
                    'data-id': id,
                    'data-visibility': vis,
                    'data-share-url': shareUrl,
                },
            }));
        }
    }

    if (perms.deleteFile) {
        parts.push(actionBtn({
            title: actionLabel(config, 'delete', 'Delete'),
            icon: 'bi-trash',
            classes: 'is-danger',
            attrs: {
                'data-fh-ajax-action': 'delete-file',
                'data-fh-action-url': destroyUrl('file', id, config),
                'data-fh-method': 'DELETE',
                'data-fh-confirm': config.messages?.actions?.confirmDeleteFile || 'Move this file to trash?',
                'data-fh-redirect': listingRedirectUrl(config),
            },
        }));
    }

    return parts.join('');
}

function tableFolderRow(folder, config) {
    const id = escapeHtml(folder.id);
    const name = escapeHtml(folder.name || '');
    const href = folderHref(folder.id, config);
    const vis = folder.visibility || 'private';
    return `<tr data-fh-row data-fh-entity="folder" data-fh-id="${id}">
<td class="fh-table__select-cell"><input type="checkbox" class="form-check-input" data-fh-select value="folder:${id}"></td>
<td class="fh-table__icon-cell"><a href="${href}">${iconHtml(null, true)}</a></td>
<td><a href="${href}" class="fh-table__name"><span class="fh-table__name-text" data-fh-name>${name}</span></a></td>
<td class="fh-table__type">${escapeHtml(config.messages?.typeFolder || 'Folder')}</td>
<td class="fh-table__size">—</td>
${folderRegionCellHtml(config)}
<td data-fh-visibility-cell>${visibilityBadgeHtml(vis, config)}</td>
<td class="fh-table__actions">${folderActionsHtml(folder, config, { compact: false })}</td>
</tr>`;
}

function isMicrostockCtx(config) {
    return config.currentWorkspace?.kind === 'microstock';
}

function microstockBadgeHtml(file, config) {
    if (!isMicrostockCtx(config)) {
        return '';
    }
    const labels = config.microstockPanel || {};
    const ms = file.microstock;
    let label = labels.noMetadata || 'No metadata';
    let cls = 'text-bg-secondary';
    if (ms && ms.status) {
        if (ms.status === 'finalized') {
            label = labels.statusFinalized || 'Finalized';
            cls = 'text-bg-success';
        } else if (ms.status === 'generated') {
            label = labels.statusGenerated || 'Generated';
            cls = 'text-bg-info';
        } else {
            label = labels.statusPending || 'Pending';
            cls = 'text-bg-warning';
        }
    }
    return `<span class="badge ${cls} fh-ms-badge" data-fh-ms-status>${escapeHtml(label)}</span>`;
}

function microstockReviewBtnHtml(file, config) {
    if (!isMicrostockCtx(config)) {
        return '';
    }
    const id = escapeHtml(file.id);
    const title = escapeHtml(config.microstockPanel?.review || 'Review');
    return `<button type="button" class="fh-table__action-btn" title="${title}" data-fh-ms-review="${id}"><i class="bi bi-stars"></i></button>`;
}

function tableFileRow(file, config) {
    const id = escapeHtml(file.id);
    const name = escapeHtml(file.name || '');
    const href = fileHref(file.id, config);
    const size = escapeHtml(file.size_human || formatBytes(file.size));
    const mime = file.mime || config.messages?.typeFile || 'File';
    const vis = file.visibility || 'private';
    return `<tr data-fh-row data-fh-entity="file" data-fh-id="${id}">
<td class="fh-table__select-cell"><input type="checkbox" class="form-check-input" data-fh-select value="file:${id}"></td>
<td class="fh-table__icon-cell"><a href="${href}">${iconHtml(file.mime, false)}</a></td>
<td><a href="${href}" class="fh-table__name" title="${name}"><span class="fh-table__name-text" data-fh-name>${name}</span></a> ${microstockBadgeHtml(file, config)}</td>
<td class="fh-table__type">${escapeHtml(mime)}</td>
<td class="fh-table__size">${size}</td>
${regionCellHtml(file, config)}
<td data-fh-visibility-cell>${visibilityBadgeHtml(vis, config)}</td>
<td class="fh-table__actions">${microstockReviewBtnHtml(file, config)}${fileActionsHtml(file, config, { compact: false })}</td>
</tr>`;
}

function gridFolderCard(folder, config) {
    const id = escapeHtml(folder.id);
    const name = escapeHtml(folder.name || '');
    const href = folderHref(folder.id, config);
    const vis = folder.visibility || 'private';
    return `<div class="fh-file-card" data-fh-row data-fh-entity="folder" data-fh-id="${id}">
<input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="folder:${id}">
<div class="fh-file-card__badge">${visibilityBadgeHtml(vis, config)}</div>
<div class="fh-file-card__icon"><a href="${href}">${iconHtml(null, true)}</a></div>
<a href="${href}" class="fh-file-card__name" title="${name}" data-fh-name>${name}</a>
<div class="fh-file-card__meta">${escapeHtml(config.messages?.typeFolder || 'Folder')}</div>
<div class="fh-file-card__actions">${folderActionsHtml(folder, config, { compact: true })}</div>
</div>`;
}

function gridFileCard(file, config) {
    const id = escapeHtml(file.id);
    const name = escapeHtml(file.name || '');
    const href = fileHref(file.id, config);
    const size = escapeHtml(file.size_human || formatBytes(file.size));
    const vis = file.visibility || 'private';
    let meta = size;
    if (showRegionColumn(config)) {
        const regionKey = file.region || null;
        const regionLabel = regionKey
            ? (config.messages?.regions?.[regionKey] || regionKey)
            : null;
        if (regionLabel) {
            meta = `${size} · <span class="fh-file-card__region">${escapeHtml(regionLabel)}</span>`;
        }
    }
    return `<div class="fh-file-card" data-fh-row data-fh-entity="file" data-fh-id="${id}">
<input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="file:${id}">
<div class="fh-file-card__badge">${visibilityBadgeHtml(vis, config)} ${microstockBadgeHtml(file, config)}</div>
<div class="fh-file-card__icon"><a href="${href}">${iconHtml(file.mime, false)}</a></div>
<a href="${href}" class="fh-file-card__name" title="${name}" data-fh-name>${name}</a>
<div class="fh-file-card__meta">${meta}</div>
<div class="fh-file-card__actions">${microstockReviewBtnHtml(file, config)}${fileActionsHtml(file, config, { compact: true })}</div>
</div>`;
}

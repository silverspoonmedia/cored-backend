/**
 * File Hosting listing DOM helpers — soft-refetch floor + light single-entity patch.
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

    const folders = browseData.folders || [];
    const files = browseData.files || [];
    const tbody = root.querySelector('[data-fh-view="table"] tbody');
    const grid = root.querySelector('[data-fh-view="grid"]');

    if (!tbody && !grid) {
        // No listing surface on this page — do not hard-reload (caller decides).
        return false;
    }

    if (tbody) {
        tbody.innerHTML = renderTableRows(folders, files, config);
    }
    if (grid) {
        // Normalize grid shell: empty state OR .fh-grid of cards
        if (folders.length === 0 && files.length === 0) {
            grid.innerHTML = emptyStateHtml(config);
        } else {
            grid.innerHTML = `<div class="fh-grid">${renderGridCards(folders, files, config)}</div>`;
        }
    }

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
        const removed = removeEntityRows(root, entityType, entityId);
        return removed;
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
        if (!sameFolder(currentFolder, eventFolder) && action !== 'visibility') {
            // visibility may still show in current folder
        }
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
        `[data-fh-entity="${entityType}"][data-fh-id="${CSS.escape(entityId)}"]`,
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
        `[data-fh-entity="${entityType}"][data-fh-id="${CSS.escape(entityId)}"]`,
    );
    if (!rows.length) {
        return false;
    }

    const name = resource.name || '';
    const visibility = resource.visibility || 'private';
    const visLink = config.visLink || 'anyone_with_link';

    rows.forEach((row) => {
        const nameEl = row.querySelector('[data-fh-name], .fh-table__name-text, .fh-file-card__name');
        if (nameEl && name) {
            nameEl.textContent = name;
        }
        const badge = row.querySelector('[data-fh-visibility], .fh-visibility-badge, .badge');
        // Best-effort: toggle a data attribute for CSS; full badge HTML stays SSR quality on next refetch
        if (badge) {
            badge.dataset.visibility = visibility;
            if (visibility === visLink) {
                badge.classList.add('is-shared');
            } else {
                badge.classList.remove('is-shared');
            }
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
    return `<div class="fh-empty text-muted py-4 text-center" data-fh-empty>${escapeHtml(msg)}</div>`;
}

function renderTableRows(folders, files, config) {
    const parts = [];
    folders.forEach((f) => {
        parts.push(tableFolderRow(f, config));
    });
    files.forEach((f) => {
        parts.push(tableFileRow(f, config));
    });
    if (!parts.length) {
        return `<tr data-fh-empty><td colspan="7" class="text-muted text-center py-4">${escapeHtml(config.messages?.emptyFolder || 'Empty')}</td></tr>`;
    }
    return parts.join('');
}

function renderGridCards(folders, files, config) {
    const parts = [];
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
    return `${config.fileShowBase || '/file-hosting/files'}/${id}`;
}

function tableFolderRow(folder, config) {
    const id = folder.id;
    const name = escapeHtml(folder.name || '');
    const href = folderHref(id, config);
    return `<tr data-fh-row data-fh-entity="folder" data-fh-id="${escapeHtml(id)}">
<td class="fh-table__select-cell"><input type="checkbox" class="form-check-input" data-fh-select value="folder:${escapeHtml(id)}"></td>
<td class="fh-table__icon-cell"><a href="${href}"><i class="bi bi-folder-fill text-warning"></i></a></td>
<td><a href="${href}" class="fh-table__name"><span class="fh-table__name-text" data-fh-name>${name}</span></a></td>
<td class="fh-table__type">${escapeHtml(config.messages?.typeFolder || 'Folder')}</td>
<td class="fh-table__size">—</td>
<td data-fh-visibility="${escapeHtml(folder.visibility || 'private')}">${escapeHtml(folder.visibility || 'private')}</td>
<td class="fh-table__actions"></td>
</tr>`;
}

function tableFileRow(file, config) {
    const id = file.id;
    const name = escapeHtml(file.name || '');
    const href = fileHref(id, config);
    const size = escapeHtml(file.size_human || formatBytes(file.size));
    return `<tr data-fh-row data-fh-entity="file" data-fh-id="${escapeHtml(id)}">
<td class="fh-table__select-cell"><input type="checkbox" class="form-check-input" data-fh-select value="file:${escapeHtml(id)}"></td>
<td class="fh-table__icon-cell"><a href="${href}"><i class="bi bi-file-earmark"></i></a></td>
<td><a href="${href}" class="fh-table__name"><span class="fh-table__name-text" data-fh-name>${name}</span></a></td>
<td class="fh-table__type">${escapeHtml(file.mime || config.messages?.typeFile || 'File')}</td>
<td class="fh-table__size">${size}</td>
<td data-fh-visibility="${escapeHtml(file.visibility || 'private')}">${escapeHtml(file.visibility || 'private')}</td>
<td class="fh-table__actions"></td>
</tr>`;
}

function gridFolderCard(folder, config) {
    const id = folder.id;
    const name = escapeHtml(folder.name || '');
    const href = folderHref(id, config);
    return `<div class="fh-file-card" data-fh-row data-fh-entity="folder" data-fh-id="${escapeHtml(id)}">
<input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="folder:${escapeHtml(id)}">
<a href="${href}" class="fh-file-card__link">
  <div class="fh-file-card__icon"><i class="bi bi-folder-fill text-warning"></i></div>
  <div class="fh-file-card__name" data-fh-name>${name}</div>
</a>
</div>`;
}

function gridFileCard(file, config) {
    const id = file.id;
    const name = escapeHtml(file.name || '');
    const href = fileHref(id, config);
    return `<div class="fh-file-card" data-fh-row data-fh-entity="file" data-fh-id="${escapeHtml(id)}">
<input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="file:${escapeHtml(id)}">
<a href="${href}" class="fh-file-card__link">
  <div class="fh-file-card__icon"><i class="bi bi-file-earmark"></i></div>
  <div class="fh-file-card__name" data-fh-name>${name}</div>
</a>
</div>`;
}

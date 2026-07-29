/**
 * Trash listing soft renderer — kills hard reload on WS / restore / force-delete.
 */

import { joinFhPath, resolveFhBase } from './router.js';

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function iconHtml(isFolder, mime) {
    if (isFolder) {
        return '<i class="bi bi-folder-fill fh-file-icon is-folder"></i>';
    }
    const m = String(mime || '');
    let icon = 'bi-file-earmark';
    if (m.startsWith('image/')) {
        icon = 'bi-file-earmark-image';
    } else if (m.startsWith('video/')) {
        icon = 'bi-file-earmark-play';
    } else if (m.includes('pdf')) {
        icon = 'bi-file-earmark-pdf';
    } else if (m.includes('zip') || m.includes('compressed')) {
        icon = 'bi-file-earmark-zip';
    }
    return `<i class="bi ${icon} fh-file-icon"></i>`;
}

function actionButtons(entity, id, config) {
    const fh = resolveFhBase(config);
    const base = entity === 'folder'
        ? (config.folderBase || joinFhPath(fh, 'folders'))
        : (config.fileBase || joinFhPath(fh, 'files'));
    const perms = config.permissions || {};
    const canEdit = entity === 'folder' ? perms.editFolder : perms.editFile;
    const canDelete = entity === 'folder' ? perms.deleteFolder : perms.deleteFile;
    const msgs = config.messages || {};
    const parts = [];

    if (canEdit && base) {
        const restoreUrl = joinFhPath(base, id, 'restore');
        if (restoreUrl) {
            parts.push(
                `<button type="button" class="fh-table__action-btn" title="${escapeHtml(msgs.restore || 'Restore')}"`
                + ` data-fh-ajax-action="restore-${entity}"`
                + ` data-fh-action-url="${escapeHtml(restoreUrl)}"`
                + ` data-fh-method="POST"`
                + ` data-fh-confirm="${escapeHtml(msgs.confirmRestore || '')}">`
                + '<i class="bi bi-arrow-counterclockwise"></i></button>',
            );
        }
    }
    if (canDelete && base) {
        const forceUrl = joinFhPath(base, id, 'force');
        if (forceUrl) {
            parts.push(
                `<button type="button" class="fh-table__action-btn is-danger" title="${escapeHtml(msgs.forceDelete || 'Delete forever')}"`
                + ` data-fh-ajax-action="force-delete-${entity}"`
                + ` data-fh-action-url="${escapeHtml(forceUrl)}"`
                + ` data-fh-method="DELETE"`
                + ` data-fh-confirm="${escapeHtml(msgs.confirmForceDelete || '')}">`
                + '<i class="bi bi-x-octagon"></i></button>',
            );
        }
    }
    return parts.join('');
}

function rowHtml(entity, item, config) {
    const id = item.id || '';
    const name = item.name || '';
    const isFolder = entity === 'folder';
    const typeLabel = isFolder
        ? (config.messages?.typeFolder || 'Folder')
        : (item.mime || config.messages?.typeFile || 'File');
    const deleted = item.deleted_at_human || item.deleted_at_formatted || item.deleted_at || '';

    return (
        `<tr data-fh-row data-fh-entity="${entity}" data-fh-id="${escapeHtml(id)}">`
        + `<td class="fh-table__icon-cell">${iconHtml(isFolder, item.mime)}</td>`
        + `<td><span class="fh-table__name"><span class="fh-table__name-text">${escapeHtml(name)}</span></span></td>`
        + `<td class="fh-table__type">${escapeHtml(typeLabel)}</td>`
        + `<td class="fh-table__size">${escapeHtml(deleted)}</td>`
        + `<td class="fh-table__actions">${actionButtons(entity, id, config)}</td>`
        + '</tr>'
    );
}

/**
 * @param {HTMLElement} root
 * @param {object} trashData  { folders: [], files: [] }
 * @param {object} config
 */
export function applyTrashPayload(root, trashData, config) {
    if (!root || !trashData) {
        return false;
    }
    const tbody = root.querySelector('[data-fh-trash-body]')
        || root.querySelector('[data-fh-view="trash"] tbody')
        || root.querySelector('table.fh-table tbody');
    if (!tbody) {
        return false;
    }

    const folders = trashData.folders || [];
    const files = trashData.files || [];

    if (folders.length === 0 && files.length === 0) {
        const empty = config.messages?.trashEmpty || config.messages?.emptyFolder || '';
        tbody.innerHTML = (
            '<tr><td colspan="5"><div class="fh-empty">'
            + '<i class="bi bi-trash3 fh-empty__icon"></i>'
            + `<div class="fh-empty__text">${escapeHtml(empty)}</div>`
            + '</div></td></tr>'
        );
        return true;
    }

    tbody.innerHTML = [
        ...folders.map((f) => rowHtml('folder', f, config)),
        ...files.map((f) => rowHtml('file', f, config)),
    ].join('');

    return true;
}

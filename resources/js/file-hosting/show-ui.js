/**
 * Soft file detail view renderer for SPA shell.
 */

import { joinFhPath, resolveFhBase } from './router.js';

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
    return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

function iconForMime(mime) {
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

/**
 * @param {HTMLElement} mount
 * @param {object} file
 * @param {object} config
 */
export function applyShowPayload(mount, file, config) {
    if (!mount || !file) {
        return false;
    }

    const msgs = config.messages || {};
    const labels = config.showLabels || {};
    const id = file.id || '';
    const name = file.name || '';
    const mime = file.mime || msgs.typeFile || 'File';
    const size = file.size_human || formatBytes(file.size);
    const vis = file.visibility || 'private';
    const shareUrl = file.share_url || '';
    const backQuery = new URLSearchParams();
    if (config.ownerUserId) {
        backQuery.set('user_id', config.ownerUserId);
    }
    if (file.workspace_id || config.workspaceId) {
        backQuery.set('workspace_id', file.workspace_id || config.workspaceId);
    }
    if (file.folder_id) {
        backQuery.set('folder_id', file.folder_id);
    }
    const base = resolveFhBase(config);
    const fileBase = config.fileBase || joinFhPath(base, 'files');
    const browseRoot = config.browseUrl || base;
    const backHref = browseRoot
        ? (browseRoot + (backQuery.toString() ? `?${backQuery}` : ''))
        : '';
    const downloadHref = fileBase ? joinFhPath(fileBase, id, 'download') : '';
    const visPrivate = labels.private || 'Private';
    const visLink = labels.anyone_with_link || 'Anyone with the link';
    const canPublicShare = config.planState === 'paid';

    mount.innerHTML = `
<div class="row g-3" data-fh-show-root data-fh-id="${escapeHtml(id)}">
  <div class="col-lg-8">
    <div class="card card-outline card-primary">
      <div class="card-body">
        <div class="fh-detail__hero">
          <div class="fh-detail__hero-icon">${iconForMime(mime)}</div>
          <div class="flex-grow-1 min-w-0">
            <div class="fh-detail__hero-name" data-fh-show-name>${escapeHtml(name)}</div>
            <div class="fh-detail__hero-meta">${escapeHtml(mime)} · ${escapeHtml(size)}</div>
          </div>
        </div>
        <h6 class="text-uppercase text-muted small mb-3">${escapeHtml(labels.stats || 'Stats')}</h6>
        <div class="fh-stats">
          <div class="fh-stat"><div class="fh-stat__icon"><i class="bi bi-eye"></i></div>
            <div class="fh-stat__body">
              <div class="fh-stat__value" data-fh-stat="views">${Number(file.view_count || 0)}</div>
              <div class="fh-stat__label">${escapeHtml(labels.views || 'Views')}</div>
            </div>
          </div>
          <div class="fh-stat"><div class="fh-stat__icon"><i class="bi bi-download"></i></div>
            <div class="fh-stat__body">
              <div class="fh-stat__value" data-fh-stat="downloads">${Number(file.download_count || 0)}</div>
              <div class="fh-stat__label">${escapeHtml(labels.downloads || 'Downloads')}</div>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <dl class="row mb-0">
          <dt class="col-sm-3">${escapeHtml(labels.name || 'Name')}</dt>
          <dd class="col-sm-9" data-fh-show-name-dd>${escapeHtml(name)}</dd>
          <dt class="col-sm-3">${escapeHtml(labels.type || 'Type')}</dt>
          <dd class="col-sm-9">${escapeHtml(mime)}</dd>
          <dt class="col-sm-3">${escapeHtml(labels.size || 'Size')}</dt>
          <dd class="col-sm-9">${escapeHtml(size)}</dd>
          <dt class="col-sm-3">${escapeHtml(labels.visibility || 'Visibility')}</dt>
          <dd class="col-sm-9" data-fh-show-visibility>${escapeHtml(vis === 'anyone_with_link' ? visLink : visPrivate)}</dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body d-grid gap-2">
        <a href="${escapeHtml(backHref)}" class="btn btn-outline-secondary btn-sm" data-fh-soft-nav>
          <i class="bi bi-arrow-left me-1"></i>${escapeHtml(labels.back || 'Back')}
        </a>
        <a href="${escapeHtml(downloadHref)}" class="btn btn-primary btn-sm">
          <i class="bi bi-download me-1"></i>${escapeHtml(labels.download || 'Download')}
        </a>
        <form method="POST" action="${escapeHtml(fileBase + '/' + id + '/visibility')}"
              class="border rounded p-2" data-fh-ajax-form data-fh-success="soft-refresh-show">
          <input type="hidden" name="_token" value="">
          <input type="hidden" name="_method" value="PATCH">
          ${config.ownerUserId ? `<input type="hidden" name="user_id" value="${escapeHtml(config.ownerUserId)}">` : ''}
          ${config.workspaceId ? `<input type="hidden" name="workspace_id" value="${escapeHtml(config.workspaceId)}">` : ''}
          <label class="form-label small mb-1">${escapeHtml(labels.visibility || 'Visibility')}</label>
          <select name="visibility" class="form-select form-select-sm mb-2" ${canPublicShare ? '' : 'data-fh-paid-only'}>
            <option value="private" ${vis === 'private' ? 'selected' : ''}>${escapeHtml(visPrivate)}</option>
            <option value="anyone_with_link" ${vis === 'anyone_with_link' ? 'selected' : ''} ${canPublicShare ? '' : 'disabled'}>${escapeHtml(visLink)}</option>
          </select>
          ${shareUrl ? `<input type="text" class="form-control form-control-sm mb-2" readonly value="${escapeHtml(shareUrl)}" id="fh-show-share-url">` : ''}
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">${escapeHtml(labels.save || 'Save')}</button>
        </form>
        <button type="button" class="btn btn-outline-danger btn-sm"
                data-fh-ajax-action="delete-file"
                data-fh-action-url="${escapeHtml(fileBase + '/' + id)}"
                data-fh-method="DELETE"
                data-fh-confirm="${escapeHtml(msgs.actions?.confirmDeleteFile || '')}"
                data-fh-soft-nav-after="${escapeHtml(backHref)}">
          <i class="bi bi-trash me-1"></i>${escapeHtml(msgs.actions?.delete || 'Delete')}
        </button>
      </div>
    </div>
  </div>
</div>`;

    // Inject CSRF into generated form.
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    mount.querySelectorAll('input[name="_token"]').forEach((el) => {
        el.value = csrf;
    });

    return true;
}

/**
 * Patch name/visibility on existing show mount without full re-render.
 */
export function patchShowFile(mount, file) {
    if (!mount || !file) {
        return;
    }
    mount.querySelectorAll('[data-fh-show-name], [data-fh-show-name-dd]').forEach((el) => {
        el.textContent = file.name || '';
    });
    if (file.view_count != null) {
        const el = mount.querySelector('[data-fh-stat="views"]');
        if (el) {
            el.textContent = String(file.view_count);
        }
    }
    if (file.download_count != null) {
        const el = mount.querySelector('[data-fh-stat="downloads"]');
        if (el) {
            el.textContent = String(file.download_count);
        }
    }
}

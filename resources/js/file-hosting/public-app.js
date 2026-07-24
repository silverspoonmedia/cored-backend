/**
 * Public share SPA shell — soft-nav between /s/f/{id} and /s/d/{id}.
 * Download stays full navigation (signed hop). No WebSocket.
 */
import {
    createFhRouter,
    isPublicShareUrl,
    parsePublicShareUrl,
} from './router';

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

async function fetchPublic(url) {
    const res = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
        const err = new Error(json.message || 'Not found');
        err.status = res.status;
        throw err;
    }
    return json;
}

function renderFile(mount, payload, labels) {
    const file = payload.file || payload;
    const downloadUrl = payload.downloadUrl || payload.download_url || '#';
    mount.innerHTML = `
<div class="fh-public__card">
  <div class="fh-public__card-body">
    <div class="fh-public__label">${escapeHtml(labels.sharedFile || 'Shared file')}</div>
    <div class="fh-public__file-icon"><i class="bi bi-file-earmark fh-file-icon"></i></div>
    <h1 class="fh-public__file-name">${escapeHtml(file.name || '')}</h1>
    <div class="fh-public__meta">
      <div class="fh-public__meta-label">${escapeHtml(labels.type || 'Type')}</div>
      <div class="fh-public__meta-value">${escapeHtml(file.mime || '—')}</div>
      <div class="fh-public__meta-label">${escapeHtml(labels.size || 'Size')}</div>
      <div class="fh-public__meta-value">${escapeHtml(file.size_human || formatBytes(file.size))}</div>
    </div>
    <div class="fh-public__actions">
      <a href="${escapeHtml(downloadUrl)}" class="btn btn-primary btn-lg">
        <i class="bi bi-download me-1"></i>${escapeHtml(labels.download || 'Download')}
      </a>
    </div>
  </div>
</div>`;
}

function renderFolder(mount, browse, labels) {
    const folder = browse.folder || {};
    const folders = browse.folders || [];
    const files = browse.files || [];
    const crumbs = browse.breadcrumb || [];

    const crumbHtml = [
        `<span class="fh-breadcrumb__item is-active"><i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>${escapeHtml(folder.name || '')}</span>`,
        ...crumbs.map((c, i) => {
            const sep = '<i class="bi bi-chevron-right fh-breadcrumb__sep"></i>';
            if (i === crumbs.length - 1) {
                return `${sep}<span class="fh-breadcrumb__item is-active">${escapeHtml(c.name || '')}</span>`;
            }
            return `${sep}<a href="/s/d/${escapeHtml(c.id)}" class="fh-breadcrumb__item">${escapeHtml(c.name || '')}</a>`;
        }),
    ].join('');

    let body;
    if (folders.length === 0 && files.length === 0) {
        body = `<div class="fh-empty"><i class="bi bi-folder2-open fh-empty__icon"></i><div class="fh-empty__text">${escapeHtml(labels.empty || '')}</div></div>`;
    } else {
        const rows = [
            ...folders.map((f) => `
<tr>
  <td class="fh-table__icon-cell"><a href="/s/d/${escapeHtml(f.id)}"><i class="bi bi-folder-fill fh-file-icon is-folder"></i></a></td>
  <td><a href="/s/d/${escapeHtml(f.id)}" class="fh-table__name"><span class="fh-table__name-text">${escapeHtml(f.name || '')}</span></a></td>
  <td class="fh-table__type">${escapeHtml(labels.folder || 'Folder')}</td>
  <td class="fh-table__size">—</td>
</tr>`),
            ...files.map((f) => `
<tr>
  <td class="fh-table__icon-cell"><a href="/s/f/${escapeHtml(f.id)}"><i class="bi bi-file-earmark fh-file-icon"></i></a></td>
  <td><a href="/s/f/${escapeHtml(f.id)}" class="fh-table__name"><span class="fh-table__name-text">${escapeHtml(f.name || '')}</span></a></td>
  <td class="fh-table__type">${escapeHtml(f.mime || labels.file || 'File')}</td>
  <td class="fh-table__size">${escapeHtml(f.size_human || formatBytes(f.size))}</td>
</tr>`),
        ].join('');
        body = `
<div class="table-responsive">
  <table class="table fh-table">
    <thead><tr>
      <th class="fh-table__icon-cell"></th>
      <th>${escapeHtml(labels.name || 'Name')}</th>
      <th>${escapeHtml(labels.type || 'Type')}</th>
      <th>${escapeHtml(labels.size || 'Size')}</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>
</div>`;
    }

    mount.innerHTML = `
<div class="fh-public__card" style="max-width: 800px;">
  <div class="fh-public__card-body">
    <div class="fh-public__label">${escapeHtml(labels.sharedFolder || 'Shared folder')}</div>
    <nav class="fh-breadcrumb" aria-label="breadcrumb">${crumbHtml}</nav>
    ${body}
  </div>
</div>`;
}

function renderError(mount, message, labels) {
    mount.innerHTML = `
<div class="fh-public__card">
  <div class="fh-public__card-body text-center">
    <div class="fh-public__label text-danger">${escapeHtml(labels.unavailable || 'Unavailable')}</div>
    <p class="text-muted">${escapeHtml(message || labels.notFound || 'Not found')}</p>
  </div>
</div>`;
}

function initPublicApp(root) {
    if (!root || root.dataset.fhBound === '1') {
        return;
    }
    root.dataset.fhBound = '1';
    const config = JSON.parse(root.dataset.config || '{}');
    const labels = config.labels || {};
    const mount = root.querySelector('[data-fh-public-mount]') || root;

    async function navigateTo(href) {
        const parsed = parsePublicShareUrl(href);
        if (!parsed.kind || !parsed.id) {
            return;
        }
        root.classList.add('is-fh-loading');
        try {
            if (parsed.kind === 'file') {
                const json = await fetchPublic(`/s/f/${parsed.id}`);
                const data = json.data || json;
                renderFile(mount, data, labels);
                document.title = `${data.file?.name || labels.sharedFile || 'File'} | ${config.appName || ''}`;
            } else {
                const json = await fetchPublic(`/s/d/${parsed.id}`);
                const data = json.data || json;
                const browse = data.browse || data;
                renderFolder(mount, browse, labels);
                document.title = `${browse.folder?.name || labels.sharedFolder || 'Folder'} | ${config.appName || ''}`;
            }
        } catch (err) {
            renderError(mount, err.message, labels);
        } finally {
            root.classList.remove('is-fh-loading');
        }
    }

    createFhRouter({
        root: document,
        shouldIntercept: isPublicShareUrl,
        async onNavigate() {
            await navigateTo(window.location.pathname + window.location.search);
        },
    });
}

document.querySelectorAll('[data-fh-public-app]').forEach(initPublicApp);

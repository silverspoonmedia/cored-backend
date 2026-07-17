/**
 * Browser-direct chunked uploads for File Hosting (S3-compatible presigned URLs).
 */
import { runWithConcurrency } from './upload-queue.js';

export function initDirectUpload(root) {
    if (!root || root.dataset.directUploadBound === '1') {
        return;
    }

    root.dataset.directUploadBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const config = {
        initUrl: root.dataset.initUrl,
        statusUrl: root.dataset.statusUrl,
        completeUrl: root.dataset.completeUrl,
        abortUrl: root.dataset.abortUrl,
        signPartsUrl: root.dataset.signPartsUrl,
        folderId: root.dataset.folderId || null,
        userId: root.dataset.userId || null,
        reloadOnComplete: root.dataset.reloadOnComplete === '1',
        maxFiles: parseInt(root.dataset.maxFiles || '20', 10),
        maxConcurrent: Math.max(1, parseInt(root.dataset.maxConcurrent || '2', 10)),
        messages: JSON.parse(root.dataset.messages || '{}'),
    };

    const fileInput = root.querySelector('[data-direct-upload-input]');
    const queue = root.querySelector('[data-direct-upload-queue]');
    const stagedContainer = root.querySelector('[data-direct-upload-staged]');
    const startButton = root.querySelector('[data-direct-upload-start]');
    const regionSelect = root.querySelector('[data-direct-upload-region]');
    const form = root.closest('form');

    /** @type {Map<string, {file: File, state: string}>} */
    const active = new Map();

    /** @type {Array<{file: File, row: HTMLElement, rowId: string}>} */
    let pendingQueue = [];

    let batchRunning = false;

    fileInput?.addEventListener('change', function () {
        const files = Array.from(fileInput.files || []);

        if (files.length === 0) {
            return;
        }

        const stagedCount = stagedContainer?.querySelectorAll('[data-staged-id]').length ?? 0;
        const queuedCount = pendingQueue.length;
        const uploadingCount = countUploading();

        if (stagedCount + queuedCount + uploadingCount + files.length > config.maxFiles) {
            window.alert(config.messages.tooManyFiles || 'Too many files.');
            fileInput.value = '';

            return;
        }

        files.forEach(function (file) {
            const row = createRow(file, 'pending');
            const rowId = row.dataset.rowId;
            queue?.appendChild(row);
            pendingQueue.push({ file, row, rowId });
        });

        fileInput.value = '';
        updateStartButton();
    });

    startButton?.addEventListener('click', function () {
        if (pendingQueue.length === 0 || batchRunning) {
            return;
        }

        void startBatch();
    });

    form?.addEventListener('submit', function (event) {
        if (batchRunning) {
            event.preventDefault();
            window.alert(config.messages.waitForUploads || 'Please wait for uploads to finish.');

            return;
        }

        for (const entry of active.values()) {
            if (entry.state === 'uploading') {
                event.preventDefault();
                window.alert(config.messages.waitForUploads || 'Please wait for uploads to finish.');

                return;
            }
        }
    });

    function updateStartButton() {
        if (!startButton) {
            return;
        }

        startButton.disabled = pendingQueue.length === 0 || batchRunning;
    }

    function countUploading() {
        let count = 0;

        for (const entry of active.values()) {
            if (entry.state === 'uploading') {
                count += 1;
            }
        }

        return count;
    }

    async function startBatch() {
        const items = pendingQueue.splice(0);
        batchRunning = true;
        updateStartButton();

        items.forEach(function (item, index) {
            if (index >= config.maxConcurrent) {
                setRowStatus(item.row, 'waiting');
            }
        });

        try {
            await runWithConcurrency(items, config.maxConcurrent, async function (item) {
                setRowStatus(item.row, 'uploading');
                active.set(item.rowId, { file: item.file, state: 'uploading' });

                try {
                    await uploadFile(item.file, item.row, item.rowId);
                } catch (error) {
                    console.error('Direct upload failed', error);
                    markRowError(item.row, config.messages.uploadFailed || 'Upload failed.');
                    active.delete(item.rowId);
                }
            });
        } finally {
            batchRunning = false;
            updateStartButton();
        }
    }

    async function uploadFile(file, row, rowId) {
        const initBody = {
            name: file.name,
            mime: file.type || 'application/octet-stream',
            size: file.size,
        };

        if (config.folderId) {
            initBody.folder_id = config.folderId;
        }

        if (config.userId) {
            initBody.user_id = config.userId;
        }

        if (regionSelect && regionSelect.value) {
            initBody.disk = regionSelect.value;
        }

        const session = await apiJson(config.initUrl, 'POST', initBody);

        const data = session.data;
        const uploadId = data.upload_id;
        const parts = [];

        if (data.strategy === 'single') {
            const part = data.parts[0];
            const etag = await putPart(file, part.url, part.headers || {}, file.type || 'application/octet-stream');
            parts.push({ part_number: 1, etag: etag, size: file.size });
            updateProgress(row, 100);
        } else {
            const chunkSize = data.chunk_size;
            const partCount = data.part_count;
            let signedParts = data.parts || [];
            const uploaded = new Map();

            for (let partNumber = 1; partNumber <= partCount; partNumber++) {
                let signed = signedParts.find(function (p) {
                    return p.part_number === partNumber;
                });

                if (!signed) {
                    const refreshed = await apiJson(
                        replaceToken(config.signPartsUrl, uploadId),
                        'POST',
                        { part_numbers: [partNumber] },
                    );
                    signed = (refreshed.data.parts || [])[0];
                }

                if (!signed) {
                    throw new Error('Missing presigned URL for part ' + partNumber);
                }

                const start = (partNumber - 1) * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);
                const etag = await putPart(chunk, signed.url, signed.headers || {});
                uploaded.set(partNumber, { part_number: partNumber, etag: etag, size: chunk.size });
                updateProgress(row, Math.round((partNumber / partCount) * 100));
            }

            uploaded.forEach(function (value) {
                parts.push(value);
            });
        }

        const completed = await apiJson(
            replaceToken(config.completeUrl, uploadId),
            'POST',
            { parts: parts },
        );

        const label = completed.data?.file?.name || file.name;
        markRowDone(row, label);
        active.set(rowId, { file, state: 'done' });

        if (config.reloadOnComplete) {
            window.location.reload();
        }
    }

    async function putPart(body, url, headers, contentType) {
        const requestHeaders = new Headers();

        // Server may send AWS-style multi-value maps { Key: ["v"] } or flat { Key: "v" }.
        Object.entries(headers || {}).forEach(function ([key, values]) {
            if (Array.isArray(values) && values[0] != null && values[0] !== '') {
                requestHeaders.set(key, String(values[0]));
            } else if (typeof values === 'string' && values !== '') {
                requestHeaders.set(key, values);
            }
        });

        if (contentType && !requestHeaders.has('Content-Type')) {
            requestHeaders.set('Content-Type', contentType);
        }

        const response = await fetch(url, {
            method: 'PUT',
            body: body,
            headers: requestHeaders,
        });

        if (!response.ok) {
            throw new Error('Storage PUT failed with status ' + response.status);
        }

        const etag = response.headers.get('ETag') || response.headers.get('etag');

        if (!etag) {
            throw new Error('Missing ETag header from storage response');
        }

        return etag.replaceAll('"', '');
    }

    function addStagedInput(uploadId, label) {
        if (!stagedContainer) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.dataset.stagedId = uploadId;
        wrapper.className = 'd-flex align-items-center gap-2 mb-1';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'staged_attachments[]';
        input.value = uploadId;

        const text = document.createElement('span');
        text.className = 'small text-success';
        text.textContent = label;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm';
        remove.textContent = config.messages.remove || 'Remove';
        remove.addEventListener('click', function () {
            apiJson(replaceToken(config.abortUrl, uploadId), 'DELETE').catch(function () {
                // Best-effort abort; staged input is removed regardless.
            });
            wrapper.remove();
        });

        wrapper.appendChild(input);
        wrapper.appendChild(text);
        wrapper.appendChild(remove);
        stagedContainer.appendChild(wrapper);
    }

    function createRow(file, status) {
        const row = document.createElement('div');
        row.className = 'mb-2';
        row.dataset.rowId = createRowId();

        const statusLabel = statusLabelFor(status);

        row.innerHTML =
            '<div class="d-flex justify-content-between align-items-center small mb-1">'
            + '<span>' + escapeHtml(file.name) + '</span>'
            + '<span class="d-flex align-items-center gap-2">'
            + '<span data-upload-status>' + escapeHtml(statusLabel) + '</span>'
            + (status === 'pending'
                ? '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-upload-remove-pending title="'
                    + escapeHtml(config.messages.remove || 'Remove') + '">&times;</button>'
                : '')
            + '</span>'
            + '</div>'
            + '<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="progress-bar" data-upload-progress style="width: 0%"></div>'
            + '</div>';

        if (status === 'pending') {
            row.querySelector('[data-upload-remove-pending]')?.addEventListener('click', function () {
                pendingQueue = pendingQueue.filter(function (item) {
                    return item.rowId !== row.dataset.rowId;
                });
                row.remove();
                updateStartButton();
            });
        }

        return row;
    }

    function statusLabelFor(status) {
        switch (status) {
            case 'pending':
                return config.messages.pending || 'Pending';
            case 'waiting':
                return config.messages.waiting || 'Waiting…';
            case 'uploading':
                return config.messages.uploading || 'Uploading…';
            default:
                return config.messages.uploading || 'Uploading…';
        }
    }

    function setRowStatus(row, status) {
        const statusEl = row.querySelector('[data-upload-status]');
        if (statusEl) {
            statusEl.textContent = statusLabelFor(status);
            statusEl.classList.remove('text-danger');
        }

        const removeBtn = row.querySelector('[data-upload-remove-pending]');
        if (removeBtn && status !== 'pending') {
            removeBtn.remove();
        }
    }

    function updateProgress(row, percent) {
        const bar = row.querySelector('[data-upload-progress]');
        if (bar) {
            bar.style.width = percent + '%';
            bar.textContent = percent + '%';
        }
    }

    function markRowDone(row, label) {
        const status = row.querySelector('[data-upload-status]');
        if (status) {
            status.textContent = config.messages.done || 'Done';
        }
        updateProgress(row, 100);
        row.querySelector('.progress-bar')?.classList.add('bg-success');
    }

    function markRowError(row, message) {
        const status = row.querySelector('[data-upload-status]');
        if (status) {
            status.textContent = message;
            status.classList.add('text-danger');
        }
        row.querySelector('.progress-bar')?.classList.add('bg-danger');
    }

    async function apiJson(url, method, body) {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined,
        });

        const data = await response.json().catch(function () {
            return {};
        });

        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    function replaceToken(template, uploadId) {
        return template.replace('__UPLOAD__', uploadId);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    /**
     * crypto.randomUUID() requires a secure context (HTTPS / localhost).
     * http://*.test often throws — fall back for non-secure origins.
     */
    function createRowId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            try {
                return crypto.randomUUID();
            } catch {
                // Not a secure context.
            }
        }

        return 'row-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 11);
    }
}

document.querySelectorAll('[data-direct-upload]').forEach(function (root) {
    initDirectUpload(root);
});

/**
 * Browser-direct chunked uploads for order attachments (S3-compatible presigned URLs).
 */
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
        maxFiles: parseInt(root.dataset.maxFiles || '10', 10),
        messages: JSON.parse(root.dataset.messages || '{}'),
    };

    const fileInput = root.querySelector('[data-direct-upload-input]');
    const queue = root.querySelector('[data-direct-upload-queue]');
    const stagedContainer = root.querySelector('[data-direct-upload-staged]');
    const form = root.closest('form');

    /** @type {Map<string, {file: File, state: string}>} */
    const active = new Map();

    fileInput?.addEventListener('change', function () {
        const files = Array.from(fileInput.files || []);

        if (files.length === 0) {
            return;
        }

        const stagedCount = stagedContainer?.querySelectorAll('[data-staged-id]').length ?? 0;

        if (stagedCount + files.length > config.maxFiles) {
            window.alert(config.messages.tooManyFiles || 'Too many files.');
            fileInput.value = '';

            return;
        }

        files.forEach(function (file) {
            startUpload(file);
        });

        fileInput.value = '';
    });

    form?.addEventListener('submit', function (event) {
        for (const entry of active.values()) {
            if (entry.state === 'uploading') {
                event.preventDefault();
                window.alert(config.messages.waitForUploads || 'Please wait for uploads to finish.');

                return;
            }
        }
    });

    async function startUpload(file) {
        const row = createRow(file);
        queue?.appendChild(row);
        const rowId = row.dataset.rowId;
        active.set(rowId, { file, state: 'uploading' });

        try {
            const session = await apiJson(config.initUrl, 'POST', {
                name: file.name,
                mime: file.type || 'application/octet-stream',
                size: file.size,
            });

            const data = session.data;
            const uploadId = data.upload_id;
            const parts = [];

            if (data.strategy === 'single') {
                const part = data.parts[0];
                const etag = await putPart(file, part.url, part.headers || {}, file.type || 'application/octet-stream');
                parts.push({ part_number: 1, etag: etag, size: file.size });
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

            addStagedInput(uploadId, file.name);
            markRowDone(row, completed.data?.metadata?.name || file.name);
            active.set(rowId, { file, state: 'done' });
        } catch (error) {
            console.error('Direct upload failed', error);
            markRowError(row, config.messages.uploadFailed || 'Upload failed.');
            active.delete(rowId);
        }
    }

    async function putPart(body, url, headers, contentType) {
        const requestHeaders = new Headers();

        Object.entries(headers || {}).forEach(function ([key, values]) {
            if (Array.isArray(values) && values[0]) {
                requestHeaders.set(key, values[0]);
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

    function createRow(file) {
        const row = document.createElement('div');
        row.className = 'mb-2';
        row.dataset.rowId = crypto.randomUUID();
        row.innerHTML =
            '<div class="d-flex justify-content-between small mb-1">'
            + '<span>' + escapeHtml(file.name) + '</span>'
            + '<span data-upload-status>' + escapeHtml(config.messages.uploading || 'Uploading…') + '</span>'
            + '</div>'
            + '<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="progress-bar" data-upload-progress style="width: 0%"></div>'
            + '</div>';

        return row;
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
}

document.querySelectorAll('[data-direct-upload]').forEach(function (root) {
    initDirectUpload(root);
});

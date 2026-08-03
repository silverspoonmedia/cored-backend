/**
 * Browser-direct chunked uploads for File Hosting (S3-compatible presigned URLs).
 */
import { runWithConcurrency } from './upload-queue.js';
import {
    createPartProgressTracker,
    uploadPartsParallel,
} from './part-uploader.js';
import {
    computeByteProgressPercent,
    createRateSampler,
    formatTransferRate,
} from './upload-progress.js';

function monolithAlert(message) {
    if (window.MonolithSwal?.alert) {
        window.MonolithSwal.alert(message);
    } else {
        window.alert(message);
    }
}

/**
 * Resolve mutable upload context (folder / user / workspace) from a live dataset.
 *
 * For batch uploads, prefer {@link snapshotBatchContext} so SPA navigation mid-batch
 * cannot retarget files to a different folder.
 *
 * @param {DOMStringMap} dataset
 * @returns {{ folder_id?: string, user_id?: string, workspace_id?: string }}
 */
export function buildInitContext(dataset) {
    const context = {};
    const folderId = dataset?.folderId || null;
    const userId = dataset?.userId || null;
    const workspaceId = dataset?.workspaceId || null;

    if (folderId) {
        context.folder_id = folderId;
    }
    if (workspaceId) {
        context.workspace_id = workspaceId;
    }
    if (userId) {
        context.user_id = userId;
    }

    return context;
}

/**
 * Freeze upload context at batch start so mid-batch SPA navigation cannot retarget files.
 *
 * @param {DOMStringMap} dataset
 * @returns {{
 *   init: { folder_id?: string, user_id?: string, workspace_id?: string },
 *   workspaceKind: string | null,
 *   collectionLocked: boolean,
 * }}
 */
export function snapshotBatchContext(dataset) {
    return {
        init: buildInitContext(dataset),
        workspaceKind: dataset?.workspaceKind || null,
        collectionLocked: dataset?.collectionLocked === '1',
    };
}

const EXTENSION_MIME = {
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.webp': 'image/webp',
    '.gif': 'image/gif',
};

/**
 * Resolve MIME for upload init. Browsers sometimes leave `file.type` empty for valid images.
 *
 * @param {File} file
 * @returns {string}
 */
export function resolveMimeType(file) {
    const declared = file?.type ? String(file.type).trim() : '';
    if (declared && declared !== 'application/octet-stream') {
        return declared;
    }

    const name = file?.name ? String(file.name).toLowerCase() : '';
    const dot = name.lastIndexOf('.');
    if (dot >= 0) {
        const ext = name.slice(dot);
        if (EXTENSION_MIME[ext]) {
            return EXTENSION_MIME[ext];
        }
    }

    return declared || 'application/octet-stream';
}

/**
 * @param {unknown} error
 * @returns {boolean}
 */
export function isTooManyConcurrent(error) {
    const message = error && error.message ? String(error.message) : '';
    return /too many concurrent uploads/i.test(message);
}

/**
 * @param {unknown} error
 * @returns {boolean}
 */
export function isRetriablePutError(error) {
    if (isAuthExpired(error)) {
        return true;
    }
    const message = error && error.message ? String(error.message) : '';
    return /network error|status 5\d\d|status 520/i.test(message);
}

/**
 * How long a successful (Done) upload row stays in the queue before removal.
 * Failed rows are never auto-dismissed.
 */
export const DONE_ROW_DISMISS_MS = 5000;

/** Fade-out duration at the end of {@link DONE_ROW_DISMISS_MS} (part of the total, not extra). */
export const DONE_ROW_FADE_MS = 300;

/**
 * @param {number} ms
 * @returns {Promise<void>}
 */
export function sleep(ms) {
    return new Promise(function (resolve) {
        setTimeout(resolve, ms);
    });
}

/**
 * After a successful upload, keep the Done row visible briefly, then remove it
 * so the queue only shows ongoing + failed items.
 *
 * Total visible time ≈ `delayMs` (default 5s). Fade class is applied in the
 * last `fadeMs` ms before DOM removal.
 *
 * @param {HTMLElement|null|undefined} row
 * @param {{
 *   active?: Map<string, unknown>,
 *   rowId?: string,
 *   delayMs?: number,
 *   fadeMs?: number,
 *   onBeforeRemove?: (row: HTMLElement) => void,
 * }} [options]
 * @returns {{ cancel: () => void } | null}
 */
export function scheduleDoneRowDismiss(row, options = {}) {
    if (!row) {
        return null;
    }

    const active = options.active;
    const rowId = options.rowId;
    const delayMs = typeof options.delayMs === 'number' ? options.delayMs : DONE_ROW_DISMISS_MS;
    const fadeMs = typeof options.fadeMs === 'number' ? options.fadeMs : DONE_ROW_FADE_MS;
    const safeDelay = Math.max(0, delayMs);
    const safeFade = Math.max(0, Math.min(fadeMs, safeDelay));
    const waitBeforeFade = safeDelay - safeFade;

    let cancelled = false;
    let fadeTimer = null;
    let removeTimer = null;

    const clearTimers = function () {
        if (fadeTimer != null) {
            clearTimeout(fadeTimer);
            fadeTimer = null;
        }
        if (removeTimer != null) {
            clearTimeout(removeTimer);
            removeTimer = null;
        }
    };

    const remove = function () {
        if (cancelled) {
            return;
        }
        if (typeof options.onBeforeRemove === 'function') {
            options.onBeforeRemove(row);
        }
        if (typeof row.remove === 'function') {
            row.remove();
        } else if (row.parentNode) {
            row.parentNode.removeChild(row);
        }
        if (active && rowId != null) {
            active.delete(rowId);
        }
    };

    const startFade = function () {
        if (cancelled) {
            return;
        }
        if (safeFade > 0 && row.classList) {
            row.classList.add('is-dismissing');
            removeTimer = setTimeout(remove, safeFade);
            return;
        }
        remove();
    };

    fadeTimer = setTimeout(startFade, waitBeforeFade);

    return {
        cancel: function () {
            cancelled = true;
            clearTimers();
            if (row.classList) {
                row.classList.remove('is-dismissing');
            }
        },
    };
}

/**
 * @param {{
 *   workspaceKind: string | null,
 *   collectionLocked: boolean,
 *   init: { folder_id?: string },
 * }} batchContext
 * @param {{ requiresFolder?: string, collectionLocked?: string }} messages
 */
export function assertMicrostockUploadAllowed(batchContext, messages) {
    if (batchContext.workspaceKind !== 'microstock') {
        return;
    }
    if (!batchContext.init.folder_id) {
        throw new Error(messages.requiresFolder || 'Create a folder (collection) first, then upload inside it.');
    }
    if (batchContext.collectionLocked) {
        throw new Error(messages.collectionLocked || 'This collection is locked and no longer accepts uploads.');
    }
}

/**
 * Best-effort abort of a pending upload session so it cannot strand a
 * concurrent-upload slot. Never rejects: a failed abort (network blip, already
 * gone) must not mask the original upload error. The hourly prune scheduler is
 * the backstop for sessions this cannot reach (e.g. tab closed mid-upload).
 *
 * @param {{ abortUrl: string, apiJson: (url: string, method: string) => Promise<unknown> }} deps
 * @returns {Promise<void>}
 */
export async function abortUploadSession({ abortUrl, apiJson }) {
    if (!abortUrl) {
        return;
    }

    try {
        await apiJson(abortUrl, 'DELETE');
    } catch {
        // Swallowed on purpose.
    }
}

/**
 * Detect a storage auth/expiry failure worth a re-sign retry (403 / SignatureDoesNotMatch / expired).
 * putPart throws `Storage PUT failed with status <n>`.
 */
export function isAuthExpired(error) {
    const message = error && error.message ? error.message : '';
    return /status 403|SignatureDoesNotMatch|expired|Request has expired/i.test(message);
}

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
        // folder/user/workspace are read live per upload via buildInitContext(root.dataset).
        reloadOnComplete: root.dataset.reloadOnComplete === '1',
        maxFiles: parseInt(root.dataset.maxFiles || '20', 10),
        maxConcurrent: Math.max(1, parseInt(root.dataset.maxConcurrent || '2', 10)),
        maxConcurrentParts: Math.max(1, parseInt(root.dataset.maxConcurrentParts || '3', 10)),
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

        // Microstock collection gate: uploads must target a folder (collection)
        // and never a locked collection. Read live so soft-nav stays accurate.
        if (root.dataset.workspaceKind === 'microstock') {
            if (!root.dataset.folderId) {
                monolithAlert(config.messages.requiresFolder || 'Create a folder (collection) first, then upload inside it.');
                fileInput.value = '';
                return;
            }
            if (root.dataset.collectionLocked === '1') {
                monolithAlert(config.messages.collectionLocked || 'This collection is locked and no longer accepts uploads.');
                fileInput.value = '';
                return;
            }
        }

        const stagedCount = stagedContainer?.querySelectorAll('[data-staged-id]').length ?? 0;
        const queuedCount = pendingQueue.length;
        const uploadingCount = countUploading();

        if (stagedCount + queuedCount + uploadingCount + files.length > config.maxFiles) {
            monolithAlert(config.messages.tooManyFiles || 'Too many files.');
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

    root.querySelector('[data-direct-upload-browse]')?.addEventListener('click', function () {
        fileInput?.click();
    });

    form?.addEventListener('submit', function (event) {
        if (batchRunning) {
            event.preventDefault();
            monolithAlert(config.messages.waitForUploads || 'Please wait for uploads to finish.');

            return;
        }

        for (const entry of active.values()) {
            if (entry.state === 'uploading') {
                event.preventDefault();
                monolithAlert(config.messages.waitForUploads || 'Please wait for uploads to finish.');

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
        const batchContext = snapshotBatchContext(root.dataset);
        batchRunning = true;
        updateStartButton();

        items.forEach(function (item, index) {
            if (index >= config.maxConcurrent) {
                setRowStatus(item.row, 'waiting');
            }
        });

        let anySucceeded = false;

        try {
            await runWithConcurrency(items, config.maxConcurrent, async function (item) {
                setRowStatus(item.row, 'uploading');
                active.set(item.rowId, { file: item.file, state: 'uploading' });

                try {
                    await uploadFile(item.file, item.row, item.rowId, batchContext);
                    anySucceeded = true;
                } catch (error) {
                    console.error('Direct upload failed', error);
                    markRowError(item.row, config.messages.uploadFailed || 'Upload failed.');
                    active.delete(item.rowId);
                }
            });
        } finally {
            batchRunning = false;
            updateStartButton();
            // One soft-refresh after the whole batch — never mid-file, never hard reload
            // when the file manager is on the page.
            if (anySucceeded && config.reloadOnComplete) {
                refreshListingAfterUpload();
            }
        }
    }

    /**
     * Soft-refresh listing/quota after uploads. Prefer manager soft-refetch; never
     * hard-reload when `[data-file-manager]` is present (SPA-ish contract).
     */
    function refreshListingAfterUpload() {
        const manager = document.querySelector('[data-file-manager]');
        const soft = typeof window.fileHostingSoftRefresh === 'function'
            ? window.fileHostingSoftRefresh
            : (manager && typeof manager.__fhSoftRefresh === 'function'
                ? manager.__fhSoftRefresh.bind(manager)
                : null);

        if (soft) {
            soft();
            return;
        }

        // Manager not bound yet (script order race) — short retry, then give up.
        // Still no hard reload on FH pages: listing will catch up via WS / next action.
        if (manager) {
            let attempts = 0;
            const timer = setInterval(function () {
                attempts += 1;
                if (typeof window.fileHostingSoftRefresh === 'function') {
                    clearInterval(timer);
                    window.fileHostingSoftRefresh();
                    return;
                }
                if (attempts >= 10) {
                    clearInterval(timer);
                }
            }, 100);
            return;
        }

        // No file manager on this page (standalone dropzone) — hard reload is OK.
        window.location.reload();
    }

    async function uploadFile(file, row, rowId, batchContext) {
        assertMicrostockUploadAllowed(batchContext, config.messages);

        const mime = resolveMimeType(file);
        const initBody = {
            name: file.name,
            mime: mime,
            size: file.size,
            ...batchContext.init,
        };

        if (regionSelect && regionSelect.value) {
            initBody.disk = regionSelect.value;
        }

        const session = await initUploadWithRetry(initBody);

        const data = session.data;
        const uploadId = data.upload_id;
        const parts = [];
        const totalBytes = file.size;
        // Aggregate progress across parallel parts: per-part loaded bytes keyed by part
        // number, so re-PUTs / out-of-order completion never double-count.
        const tracker = createPartProgressTracker();
        const sampler = createRateSampler(5000);

        function reportProgress() {
            const uploaded = tracker.total();
            const percent = computeByteProgressPercent(uploaded, totalBytes);
            sampler.add(uploaded);
            updateProgress(row, percent, sampler.rate());
        }

        /**
         * PUT one part, re-signing once on 403 (presigned URL expired mid-upload).
         * Init presigns every part upfront with a fixed TTL, so a slow upload can outlive
         * it — a stale URL must trigger a fresh sign + retry, not a silent hard-fail.
         */
        async function uploadOnePart(partNumber, chunkSize) {
            const start = (partNumber - 1) * chunkSize;
            const end = Math.min(start + chunkSize, file.size);
            const chunk = file.slice(start, end);

            async function sign() {
                const refreshed = await apiJson(
                    replaceToken(config.signPartsUrl, uploadId),
                    'POST',
                    { part_numbers: [partNumber] },
                );
                const signed = (refreshed.data.parts || []).find(function (p) {
                    return p.part_number === partNumber;
                });
                if (!signed) {
                    throw new Error('Missing presigned URL for part ' + partNumber);
                }
                return signed;
            }

            // Use the init-provided URL when present; only hit sign-parts when missing/expired.
            let signed = (data.parts || []).find(function (p) {
                return p.part_number === partNumber;
            }) || (await sign());

            async function put() {
                tracker.set(partNumber, 0);
                const etag = await putPartWithRetry(
                    chunk,
                    signed.url,
                    signed.headers || {},
                    undefined,
                    function (event) {
                        tracker.set(partNumber, event.loaded);
                        reportProgress();
                    },
                    async function () {
                        signed = await sign();
                        return { url: signed.url, headers: signed.headers || {} };
                    },
                );
                return etag;
            }

            let etag;
            try {
                etag = await put();
            } catch (error) {
                if (!isAuthExpired(error)) {
                    throw error;
                }
                signed = await sign();
                tracker.set(partNumber, 0);
                etag = await put();
            }

            tracker.set(partNumber, chunk.size);
            reportProgress();
            return { part_number: partNumber, etag: etag, size: chunk.size };
        }

        try {
            if (data.strategy === 'single') {
                const part = data.parts[0];
                const etag = await putPartWithRetry(
                    file,
                    part.url,
                    part.headers || {},
                    mime,
                    function (event) {
                        tracker.set(1, event.loaded);
                        reportProgress();
                    },
                    async function () {
                        const refreshed = await apiJson(
                            replaceToken(config.signPartsUrl, uploadId),
                            'POST',
                            { part_numbers: [1] },
                        );
                        const signed = (refreshed.data.parts || []).find(function (p) {
                            return p.part_number === 1;
                        });
                        if (!signed) {
                            throw new Error('Missing presigned URL for part 1');
                        }
                        part.url = signed.url;
                        part.headers = signed.headers || {};
                        return { url: signed.url, headers: signed.headers || {} };
                    },
                );
                parts.push({ part_number: 1, etag: etag, size: file.size });
                tracker.set(1, file.size);
                reportProgress();
            } else {
                const chunkSize = data.chunk_size;
                const uploaded = await uploadPartsParallel({
                    partCount: data.part_count,
                    concurrency: config.maxConcurrentParts,
                    uploadOne: async function (partNumber) {
                        return uploadOnePart(partNumber, chunkSize);
                    },
                });
                uploaded.forEach(function (p) {
                    parts.push(p);
                });
            }

            // Bytes are on storage; complete merges parts / promotes the file on Monolith.
            setRowStatus(row, 'completing');
            updateProgress(row, 100);

            const completed = await apiJson(
                replaceToken(config.completeUrl, uploadId),
                'POST',
                { parts: parts },
            );

            const label = completed.data?.file?.name || file.name;
            markRowDone(row, label);
            active.set(rowId, { file, state: 'done' });
            scheduleDoneRowDismiss(row, { active: active, rowId: rowId });

            // Per-file: optional optimistic row inject. Full soft-refresh runs once at batch end.
            const hosted = completed.data?.file;
            if (hosted && typeof window.fileHostingUpsertFile === 'function') {
                window.fileHostingUpsertFile(hosted);
            }
        } catch (error) {
            await abortUploadSession({
                abortUrl: replaceToken(config.abortUrl, uploadId),
                apiJson: apiJson,
            });
            throw error;
        }
    }

    /**
     * @param {Record<string, unknown>} initBody
     * @returns {Promise<{ data: Record<string, unknown> }>}
     */
    async function initUploadWithRetry(initBody) {
        const maxAttempts = 3;
        let lastError;

        for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
            try {
                return await apiJson(config.initUrl, 'POST', initBody);
            } catch (error) {
                lastError = error;
                if (!isTooManyConcurrent(error) || attempt >= maxAttempts) {
                    throw error;
                }
                await sleep(500 * Math.pow(2, attempt - 1));
            }
        }

        throw lastError;
    }

    /**
     * @param {Blob|File} body
     * @param {string} url
     * @param {Record<string, string|string[]>} headers
     * @param {string} [contentType]
     * @param {(event: { loaded: number, total: number }) => void} [onProgress]
     * @param {() => Promise<{ url?: string, headers?: Record<string, string|string[]> } | void>} [onResign]
     * @returns {Promise<string>}
     */
    async function putPartWithRetry(body, url, headers, contentType, onProgress, onResign) {
        const maxAttempts = 3;
        let currentUrl = url;
        let currentHeaders = headers;
        let lastError;

        for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
            try {
                return await putPart(body, currentUrl, currentHeaders, contentType, onProgress);
            } catch (error) {
                lastError = error;
                if (!isRetriablePutError(error) || attempt >= maxAttempts) {
                    throw error;
                }
                if (typeof onResign === 'function') {
                    const refreshed = await onResign();
                    if (refreshed?.url) {
                        currentUrl = refreshed.url;
                        currentHeaders = refreshed.headers || {};
                    }
                }
                await sleep(400 * attempt);
            }
        }

        throw lastError;
    }

    /**
     * PUT one part via XHR so upload.onprogress can drive byte-level progress + speed.
     *
     * @param {Blob|File} body
     * @param {string} url
     * @param {Record<string, string|string[]>} headers
     * @param {string} [contentType]
     * @param {(event: { loaded: number, total: number }) => void} [onProgress]
     * @returns {Promise<string>} ETag without quotes
     */
    function putPart(body, url, headers, contentType, onProgress) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url);

            // Server may send AWS-style multi-value maps { Key: ["v"] } or flat { Key: "v" }.
            let hasContentType = false;
            Object.entries(headers || {}).forEach(function ([key, values]) {
                let value = null;
                if (Array.isArray(values) && values[0] != null && values[0] !== '') {
                    value = String(values[0]);
                } else if (typeof values === 'string' && values !== '') {
                    value = values;
                }
                if (value == null) {
                    return;
                }
                xhr.setRequestHeader(key, value);
                if (key.toLowerCase() === 'content-type') {
                    hasContentType = true;
                }
            });

            if (contentType && !hasContentType) {
                xhr.setRequestHeader('Content-Type', contentType);
            }

            if (typeof onProgress === 'function' && xhr.upload) {
                xhr.upload.onprogress = function (event) {
                    if (event.lengthComputable || event.loaded > 0) {
                        onProgress({
                            loaded: event.loaded,
                            total: event.total || 0,
                        });
                    }
                };
            }

            xhr.onload = function () {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error('Storage PUT failed with status ' + xhr.status));
                    return;
                }

                const etag = xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag');

                if (!etag) {
                    reject(new Error('Missing ETag header from storage response'));
                    return;
                }

                resolve(etag.replaceAll('"', ''));
            };

            xhr.onerror = function () {
                reject(new Error('Storage PUT network error'));
            };

            xhr.onabort = function () {
                reject(new Error('Storage PUT aborted'));
            };

            xhr.send(body);
        });
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
        row.className = 'mb-2 fh-upload-row';
        row.dataset.rowId = createRowId();

        const statusLabel = statusLabelFor(status);
        const sizeLabel = formatUploadSize(file.size);

        row.innerHTML =
            '<div class="d-flex justify-content-between align-items-center small mb-1 fh-upload-row__header">'
            + '<span class="fh-upload-row__name text-truncate me-2" title="' + escapeHtml(file.name) + '">'
            + escapeHtml(file.name)
            + ' <span class="fh-upload-row__size text-muted">(' + escapeHtml(sizeLabel) + ')</span>'
            + '</span>'
            + '<span class="d-flex align-items-center gap-2 flex-shrink-0">'
            + '<span data-upload-status class="fh-upload-row__status">' + escapeHtml(statusLabel) + '</span>'
            + (status === 'pending'
                ? '<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-upload-remove-pending title="'
                    + escapeHtml(config.messages.remove || 'Remove') + '">&times;</button>'
                : '')
            + '</span>'
            + '</div>'
            + '<div class="d-flex justify-content-between align-items-center small mb-1">'
            + '<div class="progress flex-grow-1 me-2 fh-upload-row__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">'
            + '<div class="progress-bar fh-upload-row__progress-bar" data-upload-progress style="width: 0%">0%</div>'
            + '</div>'
            + '<span class="text-muted text-nowrap" data-upload-speed></span>'
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
            case 'completing':
                return config.messages.completing || 'Finalizing…';
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

    /**
     * @param {HTMLElement} row
     * @param {number} percent
     * @param {number} [bytesPerSecond]
     */
    function updateProgress(row, percent, bytesPerSecond) {
        const bar = row.querySelector('[data-upload-progress]');
        if (bar) {
            bar.style.width = percent + '%';
            bar.textContent = percent + '%';
            bar.setAttribute('aria-valuenow', String(percent));
        }

        const speedEl = row.querySelector('[data-upload-speed]');
        if (speedEl) {
            if (bytesPerSecond != null && bytesPerSecond > 0 && percent < 100) {
                speedEl.textContent = formatTransferRate(bytesPerSecond);
            } else if (percent >= 100) {
                speedEl.textContent = '';
            }
        }
    }

    function markRowDone(row, label) {
        const status = row.querySelector('[data-upload-status]');
        if (status) {
            status.textContent = config.messages.done || 'Done';
        }
        updateProgress(row, 100);
        const speedEl = row.querySelector('[data-upload-speed]');
        if (speedEl) {
            speedEl.textContent = '';
        }
        row.querySelector('.progress-bar')?.classList.add('bg-success');
    }

    function markRowError(row, message) {
        const status = row.querySelector('[data-upload-status]');
        if (status) {
            status.textContent = message;
            status.classList.add('text-danger');
        }
        const speedEl = row.querySelector('[data-upload-speed]');
        if (speedEl) {
            speedEl.textContent = '';
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

    function formatUploadSize(bytes) {
        const n = Number(bytes) || 0;
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1024 * 1024) {
            return (n / 1024).toFixed(n < 10 * 1024 ? 1 : 0) + ' KB';
        }
        if (n < 1024 * 1024 * 1024) {
            return (n / (1024 * 1024)).toFixed(n < 10 * 1024 * 1024 ? 1 : 0) + ' MB';
        }
        return (n / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
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

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-direct-upload]').forEach(function (root) {
        initDirectUpload(root);
    });
}

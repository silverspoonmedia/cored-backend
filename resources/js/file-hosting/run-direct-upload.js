/**
 * Framework-agnostic direct-upload protocol (init → PUT parts → complete).
 * Used by vanilla initDirectUpload and the Vue useFhUpload composable.
 */
import {
    abortUploadSession,
    assertMicrostockUploadAllowed,
    isAuthExpired,
    isRetriablePutError,
    isTooManyConcurrent,
    resolveMimeType,
    sleep,
} from './direct-upload.js';
import {
    createPartProgressTracker,
    uploadPartsParallel,
} from './part-uploader.js';
import {
    computeByteProgressPercent,
    createRateSampler,
} from './upload-progress.js';

/**
 * @param {string} template
 * @param {string} uploadId
 */
export function replaceUploadToken(template, uploadId) {
    return String(template || '').replace('__UPLOAD__', uploadId);
}

/**
 * @param {string} csrfToken
 */
export function createUploadApiJson(csrfToken) {
    return async function apiJson(url, method, body) {
        const response = await fetch(url, {
            method: method,
            headers: {
                Accept: 'application/json',
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
    };
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
export function putPart(body, url, headers, contentType, onProgress) {
    return new Promise(function (resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url);

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
 * Run one file through init → storage PUT(s) → complete.
 *
 * @param {object} opts
 * @param {File} opts.file
 * @param {{
 *   initUrl: string,
 *   completeUrl: string,
 *   abortUrl: string,
 *   signPartsUrl: string,
 *   maxConcurrentParts?: number,
 *   messages?: Record<string, string>,
 * }} opts.config
 * @param {{
 *   init: { folder_id?: string, user_id?: string, workspace_id?: string },
 *   workspaceKind: string | null,
 *   collectionLocked: boolean,
 * }} opts.batchContext
 * @param {string} [opts.region]
 * @param {string} opts.csrfToken
 * @param {(percent: number, bytesPerSecond?: number) => void} [opts.onProgress]
 * @param {(status: 'uploading'|'completing') => void} [opts.onStatus]
 * @returns {Promise<{ file: object|null, label: string }>}
 */
export async function runDirectUploadFile(opts) {
    const {
        file,
        config,
        batchContext,
        region,
        csrfToken,
        onProgress,
        onStatus,
    } = opts;

    const messages = config.messages || {};
    const maxConcurrentParts = Math.max(1, parseInt(String(config.maxConcurrentParts || 3), 10));
    const apiJson = createUploadApiJson(csrfToken);

    assertMicrostockUploadAllowed(batchContext, messages);

    const mime = resolveMimeType(file);
    const initBody = {
        name: file.name,
        mime: mime,
        size: file.size,
        ...batchContext.init,
    };

    if (region) {
        initBody.disk = region;
    }

    if (typeof onStatus === 'function') {
        onStatus('uploading');
    }

    const session = await initUploadWithRetry(apiJson, config.initUrl, initBody);
    const data = session.data;
    const uploadId = data.upload_id;
    const parts = [];
    const totalBytes = file.size;
    const tracker = createPartProgressTracker();
    const sampler = createRateSampler(5000);

    function reportProgress() {
        const uploaded = tracker.total();
        const percent = computeByteProgressPercent(uploaded, totalBytes);
        sampler.add(uploaded);
        if (typeof onProgress === 'function') {
            onProgress(percent, sampler.rate());
        }
    }

    async function uploadOnePart(partNumber, chunkSize) {
        const start = (partNumber - 1) * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        async function sign() {
            const refreshed = await apiJson(
                replaceUploadToken(config.signPartsUrl, uploadId),
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
                        replaceUploadToken(config.signPartsUrl, uploadId),
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
                concurrency: maxConcurrentParts,
                uploadOne: async function (partNumber) {
                    return uploadOnePart(partNumber, chunkSize);
                },
            });
            uploaded.forEach(function (p) {
                parts.push(p);
            });
        }

        if (typeof onStatus === 'function') {
            onStatus('completing');
        }
        if (typeof onProgress === 'function') {
            onProgress(100);
        }

        const completed = await apiJson(
            replaceUploadToken(config.completeUrl, uploadId),
            'POST',
            { parts: parts },
        );

        const hosted = completed.data?.file || null;
        const label = hosted?.name || file.name;
        return { file: hosted, label };
    } catch (error) {
        await abortUploadSession({
            abortUrl: replaceUploadToken(config.abortUrl, uploadId),
            apiJson: apiJson,
        });
        throw error;
    }
}

/**
 * @param {(url: string, method: string, body?: unknown) => Promise<{ data: Record<string, unknown> }>} apiJson
 * @param {string} initUrl
 * @param {Record<string, unknown>} initBody
 */
async function initUploadWithRetry(apiJson, initUrl, initBody) {
    const maxAttempts = 3;
    let lastError;

    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        try {
            return await apiJson(initUrl, 'POST', initBody);
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

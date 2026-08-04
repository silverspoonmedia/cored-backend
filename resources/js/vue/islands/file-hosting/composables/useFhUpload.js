/**
 * Vue-owned upload queue for the auth FH island.
 * Protocol stays in run-direct-upload.js; queue UI state lives here.
 */
import { computed, ref, watch } from 'vue';
import {
    DONE_ROW_DISMISS_MS,
    DONE_ROW_FADE_MS,
    snapshotBatchContext,
} from '../../../../file-hosting/direct-upload.js';
import { runDirectUploadFile } from '../../../../file-hosting/run-direct-upload.js';
import { runWithConcurrency } from '../../../../file-hosting/upload-queue.js';
import { formatTransferRate } from '../../../../file-hosting/upload-progress.js';
import { csrfToken } from './useFhApi.js';

function monolithAlert(message) {
    if (window.MonolithSwal?.alert) {
        window.MonolithSwal.alert(message);
    } else {
        window.alert(message);
    }
}

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

function formatUploadSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(n < 10 * 1024 ? 1 : 0) + ' KB';
    if (n < 1024 * 1024 * 1024) return (n / (1024 * 1024)).toFixed(n < 10 * 1024 * 1024 ? 1 : 0) + ' MB';
    return (n / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
}

function resolveOpt(val) {
    if (typeof val === 'function') return val();
    if (val && typeof val === 'object' && 'value' in val) return val.value;
    return val;
}

/**
 * @param {object} opts
 * @param {() => object|object} opts.upload
 * @param {object} opts.config — mutable FH shell config
 * @param {() => void|Promise<void>} opts.softRefresh
 * @param {(file: object) => void} opts.upsertFile
 * @param {() => boolean|boolean} opts.collectionLocked
 */
export function useFhUpload(opts) {
    const { softRefresh, upsertFile } = opts;

    const rows = ref([]);
    const batchRunning = ref(false);
    const isOsDragging = ref(false);
    const region = ref('');
    const fileInputEl = ref(null);

    const upload = computed(() => resolveOpt(opts.upload) || {});
    const collectionLocked = computed(() => !!resolveOpt(opts.collectionLocked));

    const messages = computed(() => upload.value?.messages || {});
    const maxFiles = computed(() => Math.max(1, parseInt(String(upload.value?.maxFiles || 20), 10)));
    const maxConcurrent = computed(() => Math.max(1, parseInt(String(upload.value?.maxConcurrent || 2), 10)));
    const regions = computed(() => upload.value?.regions || []);
    const canStart = computed(() => rows.value.some((r) => r.status === 'pending') && !batchRunning.value);

    watch(regions, (list) => {
        if (!region.value && list?.length) {
            region.value = list[0].key;
        }
    }, { immediate: true });

    function liveDataset() {
        const config = opts.config || {};
        return {
            folderId: config.currentFolderId || '',
            userId: config.ownerUserId || '',
            workspaceId: config.workspaceId || '',
            workspaceKind: config.currentWorkspace?.kind || '',
            collectionLocked: collectionLocked.value ? '1' : '0',
        };
    }

    function statusLabel(status) {
        const m = messages.value;
        switch (status) {
            case 'pending': return m.pending || 'Pending';
            case 'waiting': return m.waiting || 'Waiting…';
            case 'uploading': return m.uploading || 'Uploading…';
            case 'completing': return m.completing || 'Finalizing…';
            case 'done': return m.done || 'Done';
            default: return m.uploading || 'Uploading…';
        }
    }

    function gateMicrostockOrAlert() {
        const ds = liveDataset();
        if (ds.workspaceKind !== 'microstock') return true;
        if (!ds.folderId) {
            monolithAlert(messages.value.requiresFolder || 'Create a folder (collection) first, then upload inside it.');
            return false;
        }
        if (ds.collectionLocked === '1') {
            monolithAlert(messages.value.collectionLocked || 'This collection is locked and no longer accepts uploads.');
            return false;
        }
        return true;
    }

    function countActive() {
        return rows.value.filter((r) => ['waiting', 'uploading', 'completing'].includes(r.status)).length;
    }

    function addFiles(fileList) {
        const files = Array.from(fileList || []);
        if (!files.length) return;
        if (!gateMicrostockOrAlert()) return;

        const pendingCount = rows.value.filter((r) => r.status === 'pending').length;
        if (pendingCount + countActive() + files.length > maxFiles.value) {
            monolithAlert(messages.value.tooManyFiles || 'Too many files.');
            return;
        }

        for (const file of files) {
            rows.value.push({
                id: createRowId(),
                file,
                name: file.name,
                sizeLabel: formatUploadSize(file.size),
                status: 'pending',
                percent: 0,
                speed: '',
                error: '',
                dismissing: false,
            });
        }
    }

    function removePending(id) {
        rows.value = rows.value.filter((r) => !(r.id === id && r.status === 'pending'));
    }

    function openFilePicker() {
        fileInputEl.value?.click();
    }

    function onFileInputChange(e) {
        addFiles(e.target?.files);
        if (e.target) e.target.value = '';
    }

    function onOsDragOver(e) {
        if (e.dataTransfer?.types?.includes?.('Files')) {
            e.preventDefault();
            isOsDragging.value = true;
        }
    }

    function onOsDragLeave(e) {
        if (e.target === e.currentTarget) {
            isOsDragging.value = false;
        }
    }

    function onOsDrop(e) {
        e.preventDefault();
        isOsDragging.value = false;
        if (e.dataTransfer?.files?.length) {
            addFiles(e.dataTransfer.files);
        }
    }

    function onDropzoneClick(e) {
        if (e.target.closest('button, select, input, .fh-upload-queue, .fh-upload-row')) return;
        openFilePicker();
    }

    function onDropzoneKeydown(e) {
        if (e.target.closest('button, select, input')) return;
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openFilePicker();
        }
    }

    function scheduleDismiss(rowId) {
        const delayMs = DONE_ROW_DISMISS_MS;
        const fadeMs = DONE_ROW_FADE_MS;
        const waitBeforeFade = Math.max(0, delayMs - fadeMs);
        setTimeout(() => {
            const row = rows.value.find((r) => r.id === rowId);
            if (row) row.dismissing = true;
            setTimeout(() => {
                rows.value = rows.value.filter((r) => r.id !== rowId);
            }, fadeMs);
        }, waitBeforeFade);
    }

    async function startBatch() {
        const pending = rows.value.filter((r) => r.status === 'pending');
        if (!pending.length || batchRunning.value) return;

        const batchContext = snapshotBatchContext(liveDataset());
        batchRunning.value = true;
        let anySucceeded = false;

        pending.forEach((row, index) => {
            if (index >= maxConcurrent.value) {
                row.status = 'waiting';
            }
        });

        try {
            await runWithConcurrency(pending, maxConcurrent.value, async (row) => {
                row.status = 'uploading';
                row.error = '';
                try {
                    const result = await runDirectUploadFile({
                        file: row.file,
                        config: {
                            initUrl: upload.value.initUrl,
                            completeUrl: upload.value.completeUrl,
                            abortUrl: upload.value.abortUrl,
                            signPartsUrl: upload.value.signPartsUrl,
                            maxConcurrentParts: upload.value.maxConcurrentParts || 3,
                            messages: messages.value,
                        },
                        batchContext,
                        region: region.value || undefined,
                        csrfToken: csrfToken(),
                        onProgress(percent, bytesPerSecond) {
                            row.percent = percent;
                            if (bytesPerSecond != null && bytesPerSecond > 0 && percent < 100) {
                                row.speed = formatTransferRate(bytesPerSecond);
                            } else if (percent >= 100) {
                                row.speed = '';
                            }
                        },
                        onStatus(status) {
                            row.status = status;
                            if (status === 'completing') {
                                row.percent = 100;
                                row.speed = '';
                            }
                        },
                    });
                    row.status = 'done';
                    row.percent = 100;
                    row.speed = '';
                    anySucceeded = true;
                    if (result.file && typeof upsertFile === 'function') {
                        upsertFile(result.file);
                    }
                    scheduleDismiss(row.id);
                } catch (error) {
                    console.error('Direct upload failed', error);
                    row.status = 'error';
                    row.error = messages.value.uploadFailed || 'Upload failed.';
                    row.speed = '';
                }
            });
        } finally {
            batchRunning.value = false;
            if (anySucceeded && typeof softRefresh === 'function') {
                softRefresh();
            }
        }
    }

    return {
        rows,
        batchRunning,
        isOsDragging,
        region,
        fileInputEl,
        regions,
        canStart,
        statusLabel,
        addFiles,
        removePending,
        openFilePicker,
        onFileInputChange,
        onOsDragOver,
        onOsDragLeave,
        onOsDrop,
        onDropzoneClick,
        onDropzoneKeydown,
        startBatch,
        formatTransferRate,
    };
}

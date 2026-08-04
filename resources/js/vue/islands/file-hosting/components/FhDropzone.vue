<script setup>
import { useFhUpload } from '../composables/useFhUpload.js';

const props = defineProps({
    config: { type: Object, required: true },
    upload: { type: Object, default: null },
    softRefresh: { type: Function, required: true },
    upsertFile: { type: Function, required: true },
    collectionLocked: { type: Boolean, default: false },
});

const {
    rows,
    batchRunning,
    isOsDragging,
    region,
    fileInputEl,
    regions,
    canStart,
    statusLabel,
    removePending,
    openFilePicker,
    onFileInputChange,
    onOsDragOver,
    onOsDragLeave,
    onOsDrop,
    onDropzoneClick,
    onDropzoneKeydown,
    startBatch,
} = useFhUpload({
    upload: () => props.upload,
    config: props.config,
    softRefresh: props.softRefresh,
    upsertFile: props.upsertFile,
    collectionLocked: () => props.collectionLocked,
});
</script>

<template>
    <div
        v-if="upload"
        class="fh-dropzone"
        data-fh-dropzone
        data-fh-vue-upload
        :class="{ 'is-dragging': isOsDragging }"
        role="button"
        tabindex="0"
        :aria-label="upload.uploadHere || 'Upload'"
        @dragover="onOsDragOver"
        @dragleave="onOsDragLeave"
        @drop="onOsDrop"
        @click="onDropzoneClick"
        @keydown="onDropzoneKeydown"
    >
        <div class="fh-dropzone__icon">
            <i
                class="bi bi-cloud-arrow-up"
                aria-hidden="true"
            />
        </div>
        <div class="fh-dropzone__text">{{ upload.uploadHere }}</div>
        <div class="fh-dropzone__hint">{{ upload.dragHint }}</div>
        <div class="fh-dropzone__actions">
            <input
                ref="fileInputEl"
                type="file"
                class="d-none"
                multiple
                @change="onFileInputChange"
            >
            <button
                type="button"
                class="btn btn-outline-primary btn-sm"
                @click.stop="openFilePicker"
            >
                <i
                    class="bi bi-folder2-open me-1"
                    aria-hidden="true"
                />{{ upload.browse }}
            </button>
            <select
                v-if="regions.length > 1"
                v-model="region"
                class="form-select form-select-sm fh-dropzone__region"
                :aria-label="upload.regionLabel"
                @click.stop
            >
                <option
                    v-for="r in regions"
                    :key="r.key"
                    :value="r.key"
                >
                    {{ r.label }}
                </option>
            </select>
            <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="!canStart"
                @click.stop="startBatch"
            >
                <i class="bi bi-play-fill me-1" />{{ upload.start }}
            </button>
        </div>
        <div
            class="fh-upload-queue"
            @click.stop
        >
            <div
                v-for="row in rows"
                :key="row.id"
                class="mb-2 fh-upload-row"
                :class="{ 'is-dismissing': row.dismissing }"
            >
                <div class="d-flex justify-content-between align-items-center small mb-1 fh-upload-row__header">
                    <span
                        class="fh-upload-row__name text-truncate me-2"
                        :title="row.name"
                    >
                        {{ row.name }}
                        <span class="fh-upload-row__size text-muted">({{ row.sizeLabel }})</span>
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span
                            class="fh-upload-row__status"
                            :class="{ 'text-danger': row.status === 'error' }"
                        >{{ row.error || statusLabel(row.status) }}</span>
                        <button
                            v-if="row.status === 'pending'"
                            type="button"
                            class="btn btn-outline-secondary btn-sm py-0 px-1"
                            :title="upload.messages?.remove || 'Remove'"
                            @click="removePending(row.id)"
                        >&times;</button>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center small mb-1">
                    <div
                        class="progress flex-grow-1 me-2 fh-upload-row__progress"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-valuenow="row.percent"
                    >
                        <div
                            class="progress-bar fh-upload-row__progress-bar"
                            :class="{
                                'bg-success': row.status === 'done',
                                'bg-danger': row.status === 'error',
                            }"
                            :style="{ width: row.percent + '%' }"
                        >{{ row.percent }}%</div>
                    </div>
                    <span class="text-muted text-nowrap">{{ row.speed }}</span>
                </div>
            </div>
            <div
                v-if="batchRunning && !rows.length"
                class="small text-muted"
            />
        </div>
    </div>
</template>

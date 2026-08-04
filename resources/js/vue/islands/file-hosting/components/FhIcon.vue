<script setup>
defineProps({
    mime: { type: String, default: null },
    isFolder: { type: Boolean, default: false },
});

function iconClass(mime, isFolder) {
    if (isFolder) return 'bi-folder-fill fh-file-icon is-folder';
    const m = String(mime || '');
    if (m.startsWith('image/')) return 'bi-file-earmark-image fh-file-icon';
    if (m.startsWith('video/')) return 'bi-file-earmark-play fh-file-icon';
    if (m.includes('pdf')) return 'bi-file-earmark-pdf fh-file-icon';
    if (m.includes('zip') || m.includes('compressed')) return 'bi-file-earmark-zip fh-file-icon';
    return 'bi-file-earmark fh-file-icon';
}
</script>

<template>
    <i
        class="bi"
        :class="iconClass(mime, isFolder)"
        aria-hidden="true"
    />
</template>

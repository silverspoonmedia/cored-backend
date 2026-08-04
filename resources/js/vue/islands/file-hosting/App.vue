<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatCollectionProgress, resolveMicrostockBadge } from '../../../file-hosting/microstock-ui.js';
import { createMicrostockPanel } from '../../../file-hosting/microstock-panel.js';
import { createMicrostockUsagePanel } from '../../../file-hosting/microstock-usage.js';
import { initFileHostingRealtime } from '../../../file-hosting/realtime.js';
import { applyBrowseClick, listingKeys } from '../../../file-hosting/selection.js';
import { csrfToken } from './composables/useFhApi.js';
import { useFhDragMove } from './composables/useFhDragMove.js';
import { useFhMutations } from './composables/useFhMutations.js';
import { useFhShell } from './composables/useFhShell.js';
import FhDropzone from './components/FhDropzone.vue';
import FhIcon from './components/FhIcon.vue';

const props = defineProps({ bootstrap: { type: Object, required: true } });
const shell = useFhShell(props.bootstrap);
const mutations = useFhMutations(shell);
const drag = useFhDragMove({
    selected: shell.selected,
    clearSelection: shell.clearSelection,
    toggleSelect: shell.toggleSelect,
    moveItems: (keys, target) => mutations.moveItems(keys, target),
    config: shell.config,
});
const {
    config, panel, loading, isBusy, viewMode, setViewMode, browse, trash, showFile, quota,
    workspaces, currentWorkspace, isMicrostock, selected, selectionAnchor, contextMenu, clearSelection,
    toggleSelect, selectAll, setSelection, softRefresh, softRefetchBrowse, softRefetchTrash, softRefetchQuota,
    softRefetchMembers, softRefetchActivity, upsertFile, startRouter, navigate, browseHref,
    trashHref, fileShowHref, setWorkspaceChangeHandler, destroyRouter,
} = shell;

const ui = computed(() => props.bootstrap.ui || {});
const msgs = computed(() => config.messages || {});
const act = computed(() => msgs.value.actions || {});
const labels = computed(() => config.showLabels || {});
const perms = computed(() => config.permissions || {});
const visLink = computed(() => config.visLink || 'anyone_with_link');
const canCreateFolder = computed(() => ui.value.canCreateFolder === true);
const canCreateFile = computed(() => ui.value.canCreateFile === true && !!props.bootstrap.upload);
const canEdit = (entity) => entity === 'folder' ? !!perms.value.editFolder : !!perms.value.editFile;
const canDelete = (entity) => entity === 'folder' ? !!perms.value.deleteFolder : !!perms.value.deleteFile;

const rootEl = ref(null);
const searchQ = ref('');
const searchResults = ref([]);
const searchOpen = ref(false);
const modal = ref(null);
let searchTimer = null;
let realtimeHandle = null;
let microstockPanel = null;
let usagePanel = null;

const folders = computed(() => browse.value.folders || []);
const files = computed(() => browse.value.files || []);
const crumbs = computed(() => browse.value.breadcrumb || []);
const isEmpty = computed(() => !folders.value.length && !files.value.length);
const selectedCount = computed(() => selected.value.size);
const allKeys = computed(() => listingKeys(folders.value, files.value));
const collectionLocked = computed(() => !!browse.value.folder?.collection_locked);
const rootLabel = computed(() => {
    if (config.workspaceId) return currentWorkspace.value?.name || ui.value.workspaceTeam || msgs.value.rootLabel || 'Workspace';
    return ui.value.personalLabel || config.personalLabel || msgs.value.rootLabel || 'My Files';
});
const parentMeta = computed(() => {
    if (!config.currentFolderId) return null;
    const c = crumbs.value;
    const id = c.length >= 2 ? (c[c.length - 2]?.id || null) : null;
    return { id, href: browseHref({ folder_id: id }) };
});
const quotaPct = computed(() => {
    const q = quota.value;
    if (!q) return 0;
    const lim = Number(q.quota_bytes || 0);
    return lim > 0 ? Math.min(100, (Number(q.used_bytes || 0) / lim) * 100) : 0;
});
const collectionProgressText = computed(() => {
    const f = browse.value.folder;
    if (!f || !isMicrostock.value) return '';
    return formatCollectionProgress(f.collection_processed, f.collection_total, f.collection_failed, config.collectionLabels || {});
});

function go(e, href) { e?.preventDefault?.(); if (href) navigate(href); }
function openBlade(id, kind = 'Modal') {
    const el = document.getElementById(id);
    if (el && window.bootstrap?.[kind]) window.bootstrap[kind].getOrCreateInstance(el).show();
}
function msBadge(file) { return resolveMicrostockBadge(file?.microstock, config.microstockPanel || {}); }
function visLabel(v) {
    return msgs.value.visibility?.[v] || (v === visLink.value ? (labels.value.anyone_with_link || 'Link') : (labels.value.private || 'Private'));
}
function idsByEntity() {
    const out = { file: [], folder: [] };
    for (const key of selected.value) {
        const [t, id] = String(key).split(':');
        if (out[t] && id) out[t].push(id);
    }
    return out;
}
function openItem(entity, item) {
    navigate(entity === 'folder' ? browseHref({ folder_id: item.id }) : fileShowHref(item.id));
}
/** Drive: name/icon/row click selects; dblclick opens. */
function onBrowseSelect(e, entity, item) {
    if (e.target.closest('input, button, .fh-table__action-btn, .fh-action-group, a.fh-table__action-btn')) {
        return;
    }
    e.preventDefault();
    const key = `${entity}:${item.id}`;
    const { next, anchorKey } = applyBrowseClick({
        key,
        event: e,
        selected: selected.value,
        anchorKey: selectionAnchor.value,
        orderedKeys: allKeys.value,
    });
    setSelection(next, anchorKey);
}
function onBrowseOpen(e, entity, item) {
    e.preventDefault();
    openItem(entity, item);
}
function openModal(type, payload = {}) {
    modal.value = { type, name: '', visibility: 'private', target: '', ...payload };
    contextMenu.value = null;
}
async function submitModal() {
    const m = modal.value;
    if (!m) return;
    let ok = false;
    if (m.type === 'folder') ok = await mutations.createFolder(m.name);
    else if (m.type === 'rename') ok = await mutations.renameEntity(m.entity, m.id, m.name);
    else if (m.type === 'share') ok = await mutations.setVisibility(m.entity, m.id, m.visibility);
    else if (m.type === 'move') ok = await mutations.moveItems(m.keys || [`${m.entity}:${m.id}`], m.target || null);
    if (ok) modal.value = null;
}
function onContext(e, entity, item) {
    e.preventDefault();
    const key = `${entity}:${item.id}`;
    if (!selected.value.has(key)) { clearSelection(); toggleSelect(key, true); }
    contextMenu.value = { x: e.clientX, y: e.clientY, entity, id: item.id, name: item.name, item };
}
function ctxAction(action) {
    const cm = contextMenu.value;
    if (!cm) return;
    const item = cm.item || { id: cm.id, name: cm.name };
    contextMenu.value = null;
    if (action === 'open') openItem(cm.entity, item);
    else if (action === 'rename') openModal('rename', { entity: cm.entity, id: item.id, name: item.name || '' });
    else if (action === 'move') openModal('move', { entity: cm.entity, id: item.id, keys: [`${cm.entity}:${item.id}`] });
    else if (action === 'share') openModal('share', { entity: cm.entity, id: item.id, visibility: item.visibility || 'private', shareUrl: item.share_url || '' });
    else if (action === 'delete') mutations.deleteEntity(cm.entity, cm.id);
}
function downloadHref(file) {
    return shell.joinFhPath(config.fileBase || shell.joinFhPath(shell.fhBase.value, 'files'), file.id, 'download');
}
function bindRealtime() {
    realtimeHandle?.disconnect?.();
    realtimeHandle = null;
    if (!config.realtime?.enabled) return;
    try {
        realtimeHandle = initFileHostingRealtime({
            root: rootEl.value, config, csrf: csrfToken(),
            onListingRefresh: softRefetchBrowse, onQuotaRefresh: softRefetchQuota,
            onTrashRefresh: softRefetchTrash, onMembersRefresh: softRefetchMembers,
            onActivityRefresh: softRefetchActivity, onListingPatch: () => false,
            onCollectionProgress: () => softRefetchBrowse(),
        });
    } catch (err) { console.warn('FH realtime init failed', err); }
}

watch(searchQ, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        const q = searchQ.value.trim();
        if (!q) { searchResults.value = []; searchOpen.value = false; return; }
        searchResults.value = await mutations.search(q);
        searchOpen.value = true;
    }, 250);
});

onMounted(() => {
    const el = rootEl.value;
    startRouter(el);
    setWorkspaceChangeHandler(() => bindRealtime());
    bindRealtime();
    const token = csrfToken();
    try {
        if (document.getElementById('fhMicrostockPanel')) {
            microstockPanel = createMicrostockPanel(config, token, { onSaved: () => softRefetchBrowse() });
        }
        if (config.usagePanel && document.getElementById('fhMicrostockUsagePanel')) {
            usagePanel = createMicrostockUsagePanel(config.usagePanel, token);
        }
    } catch (err) {
        console.warn('FH microstock panel init failed', err);
    }
    const closeCtx = () => { contextMenu.value = null; };
    const onEsc = (e) => {
        if (e.key === 'Escape') { clearSelection(); contextMenu.value = null; searchOpen.value = false; }
    };
    document.addEventListener('click', closeCtx);
    document.addEventListener('keydown', onEsc);
    el.__fhCleanup = () => {
        document.removeEventListener('click', closeCtx);
        document.removeEventListener('keydown', onEsc);
    };
});

onBeforeUnmount(() => {
    rootEl.value?.__fhCleanup?.();
    realtimeHandle?.disconnect?.();
    destroyRouter();
    clearTimeout(searchTimer);
});
</script>

<template>
    <div
        ref="rootEl"
        class="fh-manager"
        data-fh-vue-root
        data-file-manager
        data-fh-spa
        :class="{ 'is-fh-loading': loading, 'is-fh-busy': isBusy }"
    >
        <div v-if="isBusy" class="fh-busy-overlay" data-fh-busy-overlay>
            <div class="fh-busy-overlay__inner" role="status"><div class="spinner-border spinner-border-sm" /></div>
        </div>

        <div class="fh-toolbar">
            <div class="fh-toolbar__search">
                <i class="bi bi-search fh-toolbar__search-icon" />
                <input v-model="searchQ" type="text" class="form-control form-control-sm fh-toolbar__search-input"
                       :placeholder="ui.searchPlaceholder || 'Search…'" autocomplete="off"
                       @focus="searchOpen = searchResults.length > 0">
                <div class="fh-toolbar__search-results" :class="{ 'is-open': searchOpen }">
                    <a v-for="r in searchResults" :key="r.id" class="fh-search-result" :href="fileShowHref(r.id)"
                       @click="go($event, fileShowHref(r.id)); searchOpen = false">
                        <i class="bi bi-file-earmark fh-search-result__icon" />
                        <div class="fh-search-result__name">{{ r.name }}</div>
                        <span class="fh-search-result__meta">{{ r.size_human || '' }}</span>
                    </a>
                    <div v-if="!searchResults.length && searchQ.trim()" class="fh-search-result__empty">
                        {{ msgs.searchNoResults || 'No results' }}
                    </div>
                </div>
            </div>
            <div class="fh-toolbar__view-toggle btn-group btn-group-sm" role="group">
                <button type="button" class="fh-toolbar__view-btn btn btn-outline-secondary"
                        :class="{ 'is-active': viewMode === 'table' }" @click="setViewMode('table')">
                    <i class="bi bi-list" />
                </button>
                <button type="button" class="fh-toolbar__view-btn btn btn-outline-secondary"
                        :class="{ 'is-active': viewMode === 'grid' }" @click="setViewMode('grid')">
                    <i class="bi bi-grid" />
                </button>
            </div>
            <a :href="trashHref()" class="btn btn-outline-secondary btn-sm" @click="go($event, trashHref())">
                <i class="bi bi-trash3 me-1" />{{ ui.trash || 'Trash' }}
            </a>
            <a v-show="isMicrostock" :href="config.usageUrl || config.usageUrlBase || '#'"
               class="btn btn-outline-secondary btn-sm" data-fh-toolbar-balance
               @click="usagePanel?.isReady && ($event.preventDefault(), usagePanel.open())">
                <i class="bi bi-wallet2 me-1" />{{ ui.aiBalance || 'AI Balance' }}
            </a>
            <button v-if="canCreateFolder" type="button" class="btn btn-primary btn-sm" @click="openModal('folder')">
                <i class="bi bi-folder-plus me-1" />{{ ui.newFolder || 'New folder' }}
            </button>
            <template v-if="isMicrostock && config.currentFolderId">
                <button type="button" class="btn btn-outline-primary btn-sm" @click="openBlade('fhCollectionDescribeModal')">
                    <i class="bi bi-lock me-1" />{{ ui.lockDescribe || 'Lock & describe' }}
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="openBlade('fhMicrostockPanel', 'Offcanvas')">
                    <i class="bi bi-stars me-1" />{{ ui.reviewFiles || 'Review' }}
                </button>
            </template>
            <div class="dropdown fh-workspace-switcher">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi me-1" :class="config.workspaceId ? 'bi-people' : 'bi-person'" />
                    <span data-fh-workspace-label>{{ rootLabel }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow">
                    <a class="dropdown-item" :class="{ active: !config.workspaceId }"
                       :href="browseHref({ workspace_id: null })" @click="go($event, browseHref({ workspace_id: null }))">
                        <i class="bi bi-person me-2" />{{ ui.personalLabel || config.personalLabel || 'Personal' }}
                    </a>
                    <a v-for="ws in workspaces" :key="ws.id" class="dropdown-item"
                       :class="{ active: config.workspaceId === ws.id }"
                       :href="browseHref({ workspace_id: ws.id })" @click="go($event, browseHref({ workspace_id: ws.id }))">
                        <i class="bi me-2" :class="ws.kind === 'microstock' ? 'bi-stars' : 'bi-people'" />{{ ws.name }}
                    </a>
                    <div v-if="ui.planState === 'paid'" class="px-3 py-1 small text-muted">{{ ui.slotsCounter }}</div>
                    <button v-if="ui.planState === 'paid' && ui.canCreateWorkspace" type="button" class="dropdown-item" @click="openBlade('fhCreateWorkspaceModal')">
                        <i class="bi bi-plus-lg me-2" />{{ ui.createWorkspace || 'Create workspace' }}
                    </button>
                    <template v-else-if="ui.planState === 'paid'">
                        <span class="dropdown-item disabled text-muted small">
                            <i class="bi bi-lock me-2" />{{ ui.slotsFull || 'Slots full' }}
                        </span>
                        <a :href="ui.servicesUrl || '#'" class="dropdown-item small">
                            <i class="bi bi-stars me-2" />{{ ui.buySlotCta || 'Buy slot' }}
                        </a>
                    </template>
                    <div v-else-if="ui.planState === 'free'" class="px-3 py-2 small text-muted">
                        <div>{{ ui.freeCannotCreate }}</div>
                        <a :href="ui.servicesUrl || '#'">{{ ui.upgradeCta }}</a>
                    </div>
                    <button v-else type="button" class="dropdown-item" @click="openBlade('fhCreateWorkspaceModal')">
                        <i class="bi bi-plus-lg me-2" />{{ ui.createWorkspace || 'Create workspace' }}
                    </button>
                </div>
            </div>
            <button v-if="config.workspaceId" type="button" class="btn btn-outline-secondary btn-sm"
                    @click="openBlade('fhTeamOffcanvas', 'Offcanvas')">
                <i class="bi bi-gear me-1" />{{ ui.teamSettings || ui.team || 'Team' }}
            </button>
        </div>

        <nav v-show="panel === 'browse'" class="fh-breadcrumb" data-fh-breadcrumb aria-label="breadcrumb">
            <template v-if="!crumbs.length">
                <span class="fh-breadcrumb__item is-active">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1" />{{ rootLabel }}
                </span>
            </template>
            <template v-else>
                <a class="fh-breadcrumb__item"
                   :class="{ 'is-drop-target': drag.isDropTarget('root') }"
                   data-fh-drop-folder="root"
                   :href="browseHref({ folder_id: null })"
                   @click="go($event, browseHref({ folder_id: null }))"
                   @dragover="drag.onDropOver($event, 'root')"
                   @dragleave="drag.onDropLeave($event, 'root')"
                   @drop="drag.onDrop($event, 'root')">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1" />{{ rootLabel }}
                </a>
                <template v-for="(crumb, i) in crumbs" :key="crumb.id">
                    <i class="bi bi-chevron-right fh-breadcrumb__sep" />
                    <span v-if="i === crumbs.length - 1" class="fh-breadcrumb__item is-active">{{ crumb.name }}</span>
                    <a v-else
                       class="fh-breadcrumb__item"
                       :class="{ 'is-drop-target': drag.isDropTarget(crumb.id) }"
                       :data-fh-drop-folder="crumb.id"
                       :href="browseHref({ folder_id: crumb.id })"
                       @click="go($event, browseHref({ folder_id: crumb.id }))"
                       @dragover="drag.onDropOver($event, crumb.id)"
                       @dragleave="drag.onDropLeave($event, crumb.id)"
                       @drop="drag.onDrop($event, crumb.id)">{{ crumb.name }}</a>
                </template>
            </template>
        </nav>

        <div v-if="quota && panel === 'browse'" data-fh-quota>
            <div class="fh-quota">
                <div class="fh-quota__header">
                    <span class="fh-quota__label">{{ config.quotaLabels?.used || 'Used' }}</span>
                    <span class="fh-quota__value">
                        {{ quota.used_bytes_human || '0 B' }} /
                        {{ Number(quota.quota_bytes) > 0 ? (quota.quota_bytes_human || '0 B') : (config.quotaLabels?.unlimited || 'Unlimited') }}
                    </span>
                </div>
                <div class="fh-quota__track">
                    <div class="fh-quota__fill"
                         :class="{ 'is-warn': quotaPct >= 75 && quotaPct < 90, 'is-danger': quotaPct >= 90 }"
                         :style="{ width: (Number(quota.quota_bytes) > 0 ? quotaPct : 3) + '%' }" />
                </div>
                <div v-if="collectionProgressText" class="small text-muted mt-1">{{ collectionProgressText }}</div>
            </div>
        </div>

        <FhDropzone v-if="panel === 'browse' && canCreateFile && bootstrap.upload"
                    :config="config" :upload="bootstrap.upload"
                    :soft-refresh="softRefresh" :upsert-file="upsertFile"
                    :collection-locked="collectionLocked" />

        <div v-show="panel === 'browse' && selectedCount > 0" class="fh-bulk-bar">
            <span class="fh-bulk-bar__count"><strong>{{ selectedCount }}</strong> {{ ui.selected || 'selected' }}</span>
            <div class="fh-bulk-bar__actions">
                <button v-if="perms.editFile || perms.editFolder" type="button" class="btn btn-outline-primary btn-sm"
                        @click="openModal('move', { keys: [...selected] })">
                    <i class="bi bi-folder-symlink me-1" />{{ ui.move || act.move || 'Move' }}
                </button>
                <button v-if="perms.editFile" type="button" class="btn btn-outline-success btn-sm"
                        @click="mutations.bulkVisibility(idsByEntity(), visLink)">
                    <i class="bi bi-share me-1" />{{ ui.share || act.share || 'Share' }}
                </button>
                <button v-if="perms.editFile" type="button" class="btn btn-outline-secondary btn-sm"
                        @click="mutations.bulkVisibility(idsByEntity(), 'private')">
                    <i class="bi bi-shield-lock me-1" />{{ ui.unshare || msgs.bulkUnshare || 'Unshare' }}
                </button>
                <button v-if="perms.deleteFile || perms.deleteFolder" type="button" class="btn btn-outline-danger btn-sm"
                        @click="mutations.bulkDelete(idsByEntity())">
                    <i class="bi bi-trash me-1" />{{ ui.delete || act.delete || 'Delete' }}
                </button>
            </div>
        </div>

        <!-- Browse -->
        <div v-show="panel === 'browse'" data-fh-panel="browse">
            <div v-show="viewMode === 'table'" class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__select-cell">
                                <input type="checkbox" class="form-check-input"
                                       :checked="allKeys.length && allKeys.every((k) => selected.has(k))"
                                       @change="selectAll(allKeys, $event.target.checked)">
                            </th>
                            <th class="fh-table__icon-cell" />
                            <th>{{ labels.name || 'Name' }}</th>
                            <th>{{ labels.type || 'Type' }}</th>
                            <th>{{ labels.size || 'Size' }}</th>
                            <th v-if="config.showRegion">Region</th>
                            <th>{{ labels.visibility || 'Visibility' }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="parentMeta"
                            class="fh-parent-row"
                            data-fh-row
                            data-fh-entity="parent"
                            :data-fh-drop-folder="parentMeta.id || 'root'"
                            :class="{ 'is-drop-target': drag.isDropTarget(parentMeta.id || 'root') }"
                            @dragover="drag.onDropOver($event, parentMeta.id || 'root')"
                            @dragleave="drag.onDropLeave($event, parentMeta.id || 'root')"
                            @drop="drag.onDrop($event, parentMeta.id || 'root')">
                            <td class="fh-table__select-cell" />
                            <td class="fh-table__icon-cell">
                                <a :href="parentMeta.href" draggable="false" @click="go($event, parentMeta.href)">
                                    <i class="bi bi-folder2 fh-parent-row__icon" />
                                </a>
                            </td>
                            <td>
                                <a class="fh-table__name" :href="parentMeta.href" draggable="false" @click="go($event, parentMeta.href)">
                                    <span class="fh-table__name-text">...</span>
                                </a>
                            </td>
                            <td class="fh-table__type text-muted">{{ msgs.parentFolder || 'Parent' }}</td>
                            <td class="fh-table__size">—</td>
                            <td v-if="config.showRegion" class="fh-table__region">—</td>
                            <td /><td />
                        </tr>
                        <tr v-for="folder in folders" :key="'f:' + folder.id"
                            data-fh-row data-fh-entity="folder"
                            :data-fh-id="folder.id"
                            draggable="true"
                            :class="{
                                'is-dragging-source': drag.isDraggingSource('folder', folder.id),
                                'is-drop-target': drag.isDropTarget(folder.id),
                                'is-selected': selected.has('folder:' + folder.id),
                            }"
                            @click="onBrowseSelect($event, 'folder', folder)"
                            @dblclick="onBrowseOpen($event, 'folder', folder)"
                            @contextmenu="onContext($event, 'folder', folder)"
                            @dragstart="drag.onRowDragStart($event, 'folder', folder.id)"
                            @dragend="drag.onDragEnd"
                            @dragover="drag.onDropOver($event, folder.id)"
                            @dragleave="drag.onDropLeave($event, folder.id)"
                            @drop="drag.onDrop($event, folder.id)">
                            <td class="fh-table__select-cell">
                                <input type="checkbox" class="form-check-input" :checked="selected.has('folder:' + folder.id)"
                                       @click.stop
                                       @change="toggleSelect('folder:' + folder.id, $event.target.checked)">
                            </td>
                            <td class="fh-table__icon-cell">
                                <a :href="browseHref({ folder_id: folder.id })" draggable="false"
                                   @click="onBrowseSelect($event, 'folder', folder)"
                                   @dblclick="onBrowseOpen($event, 'folder', folder)"><FhIcon is-folder /></a>
                            </td>
                            <td>
                                <a class="fh-table__name" :href="browseHref({ folder_id: folder.id })" draggable="false"
                                   @click="onBrowseSelect($event, 'folder', folder)"
                                   @dblclick="onBrowseOpen($event, 'folder', folder)">
                                    <span class="fh-table__name-text">{{ folder.name }}</span>
                                </a>
                            </td>
                            <td class="fh-table__type">{{ msgs.typeFolder || 'Folder' }}</td>
                            <td class="fh-table__size">—</td>
                            <td v-if="config.showRegion" class="fh-table__region">—</td>
                            <td>
                                <span class="fh-badge" :class="folder.visibility === visLink ? 'fh-badge--link' : 'fh-badge--private'">
                                    {{ visLabel(folder.visibility) }}
                                </span>
                            </td>
                            <td class="fh-table__actions">
                                <div class="fh-action-group">
                                    <button v-if="canEdit('folder')" type="button" class="fh-table__action-btn"
                                            @click="openModal('rename', { entity: 'folder', id: folder.id, name: folder.name })"><i class="bi bi-pencil" /></button>
                                    <button v-if="canEdit('folder')" type="button" class="fh-table__action-btn"
                                            @click="openModal('move', { entity: 'folder', id: folder.id, keys: ['folder:' + folder.id] })"><i class="bi bi-folder-symlink" /></button>
                                    <button v-if="canEdit('folder')" type="button" class="fh-table__action-btn"
                                            @click="openModal('share', { entity: 'folder', id: folder.id, visibility: folder.visibility || 'private', shareUrl: folder.share_url })"><i class="bi bi-share" /></button>
                                    <button v-if="canDelete('folder')" type="button" class="fh-table__action-btn is-danger"
                                            @click="mutations.deleteEntity('folder', folder.id)"><i class="bi bi-trash" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="file in files" :key="'file:' + file.id"
                            data-fh-row data-fh-entity="file"
                            :data-fh-id="file.id"
                            draggable="true"
                            :class="{
                                'is-dragging-source': drag.isDraggingSource('file', file.id),
                                'is-selected': selected.has('file:' + file.id),
                            }"
                            @click="onBrowseSelect($event, 'file', file)"
                            @dblclick="onBrowseOpen($event, 'file', file)"
                            @contextmenu="onContext($event, 'file', file)"
                            @dragstart="drag.onRowDragStart($event, 'file', file.id)"
                            @dragend="drag.onDragEnd">
                            <td class="fh-table__select-cell">
                                <input type="checkbox" class="form-check-input" :checked="selected.has('file:' + file.id)"
                                       @click.stop
                                       @change="toggleSelect('file:' + file.id, $event.target.checked)">
                            </td>
                            <td class="fh-table__icon-cell">
                                <a :href="fileShowHref(file.id)" draggable="false"
                                   @click="onBrowseSelect($event, 'file', file)"
                                   @dblclick="onBrowseOpen($event, 'file', file)"><FhIcon :mime="file.mime" /></a>
                            </td>
                            <td>
                                <a class="fh-table__name" :href="fileShowHref(file.id)" :title="file.name" draggable="false"
                                   @click="onBrowseSelect($event, 'file', file)"
                                   @dblclick="onBrowseOpen($event, 'file', file)">
                                    <span class="fh-table__name-text">{{ file.name }}</span>
                                </a>
                                <span v-if="isMicrostock" class="badge fh-ms-badge ms-1" :class="msBadge(file).cls">{{ msBadge(file).label }}</span>
                            </td>
                            <td class="fh-table__type">{{ file.mime || msgs.typeFile || 'File' }}</td>
                            <td class="fh-table__size">{{ file.size_human || '—' }}</td>
                            <td v-if="config.showRegion" class="fh-table__region">
                                <span class="badge text-bg-secondary fh-region-badge">{{ msgs.regions?.[file.region] || file.region || '—' }}</span>
                            </td>
                            <td>
                                <span class="fh-badge" :class="file.visibility === visLink ? 'fh-badge--link' : 'fh-badge--private'">
                                    {{ visLabel(file.visibility) }}
                                </span>
                            </td>
                            <td class="fh-table__actions">
                                <div class="fh-action-group">
                                    <button v-if="isMicrostock" type="button" class="fh-table__action-btn" @click="microstockPanel?.open?.(file.id)"><i class="bi bi-stars" /></button>
                                    <a class="fh-table__action-btn" :href="fileShowHref(file.id)" draggable="false" @click.prevent="openItem('file', file)"><i class="bi bi-eye" /></a>
                                    <button v-if="canEdit('file')" type="button" class="fh-table__action-btn"
                                            @click="openModal('rename', { entity: 'file', id: file.id, name: file.name })"><i class="bi bi-pencil" /></button>
                                    <button v-if="canEdit('file')" type="button" class="fh-table__action-btn"
                                            @click="openModal('move', { entity: 'file', id: file.id, keys: ['file:' + file.id] })"><i class="bi bi-folder-symlink" /></button>
                                    <button v-if="canEdit('file')" type="button" class="fh-table__action-btn"
                                            @click="openModal('share', { entity: 'file', id: file.id, visibility: file.visibility || 'private', shareUrl: file.share_url })"><i class="bi bi-share" /></button>
                                    <button v-if="canDelete('file')" type="button" class="fh-table__action-btn is-danger"
                                            @click="mutations.deleteEntity('file', file.id)"><i class="bi bi-trash" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="isEmpty && !parentMeta">
                            <td :colspan="config.showRegion ? 8 : 7">
                                <div class="fh-empty"><i class="bi bi-folder2-open fh-empty__icon" /><div class="fh-empty__text">{{ msgs.emptyFolder || 'Empty' }}</div></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-show="viewMode === 'grid'" class="fh-grid">
                <div v-if="parentMeta"
                     class="fh-file-card is-parent"
                     data-fh-row
                     data-fh-entity="parent"
                     :data-fh-drop-folder="parentMeta.id || 'root'"
                     :class="{ 'is-drop-target': drag.isDropTarget(parentMeta.id || 'root') }"
                     @dragover="drag.onDropOver($event, parentMeta.id || 'root')"
                     @dragleave="drag.onDropLeave($event, parentMeta.id || 'root')"
                     @drop="drag.onDrop($event, parentMeta.id || 'root')">
                    <div class="fh-file-card__icon">
                        <a :href="parentMeta.href" draggable="false" @click="go($event, parentMeta.href)"><i class="bi bi-folder2 fh-parent-row__icon" /></a>
                    </div>
                    <a class="fh-file-card__name" :href="parentMeta.href" draggable="false" @click="go($event, parentMeta.href)">...</a>
                    <div class="fh-file-card__meta">{{ msgs.parentFolder || 'Parent' }}</div>
                </div>
                <div v-for="folder in folders" :key="'gf:' + folder.id"
                     class="fh-file-card"
                     data-fh-row data-fh-entity="folder"
                     :data-fh-id="folder.id"
                     draggable="true"
                     :class="{
                         'is-dragging-source': drag.isDraggingSource('folder', folder.id),
                         'is-drop-target': drag.isDropTarget(folder.id),
                         'is-selected': selected.has('folder:' + folder.id),
                     }"
                     @click="onBrowseSelect($event, 'folder', folder)"
                     @dblclick="onBrowseOpen($event, 'folder', folder)"
                     @contextmenu="onContext($event, 'folder', folder)"
                     @dragstart="drag.onRowDragStart($event, 'folder', folder.id)"
                     @dragend="drag.onDragEnd"
                     @dragover="drag.onDropOver($event, folder.id)"
                     @dragleave="drag.onDropLeave($event, folder.id)"
                     @drop="drag.onDrop($event, folder.id)">
                    <input type="checkbox" class="form-check-input fh-file-card__select" :checked="selected.has('folder:' + folder.id)"
                           @click.stop
                           @change="toggleSelect('folder:' + folder.id, $event.target.checked)">
                    <div class="fh-file-card__icon">
                        <a :href="browseHref({ folder_id: folder.id })" draggable="false"
                           @click="onBrowseSelect($event, 'folder', folder)"
                           @dblclick="onBrowseOpen($event, 'folder', folder)"><FhIcon is-folder /></a>
                    </div>
                    <a class="fh-file-card__name" :href="browseHref({ folder_id: folder.id })" draggable="false"
                       @click="onBrowseSelect($event, 'folder', folder)"
                       @dblclick="onBrowseOpen($event, 'folder', folder)">{{ folder.name }}</a>
                    <div class="fh-file-card__meta">{{ msgs.typeFolder || 'Folder' }}</div>
                </div>
                <div v-for="file in files" :key="'gfile:' + file.id"
                     class="fh-file-card"
                     data-fh-row data-fh-entity="file"
                     :data-fh-id="file.id"
                     draggable="true"
                     :class="{
                         'is-dragging-source': drag.isDraggingSource('file', file.id),
                         'is-selected': selected.has('file:' + file.id),
                     }"
                     @click="onBrowseSelect($event, 'file', file)"
                     @dblclick="onBrowseOpen($event, 'file', file)"
                     @contextmenu="onContext($event, 'file', file)"
                     @dragstart="drag.onRowDragStart($event, 'file', file.id)"
                     @dragend="drag.onDragEnd">
                    <input type="checkbox" class="form-check-input fh-file-card__select" :checked="selected.has('file:' + file.id)"
                           @click.stop
                           @change="toggleSelect('file:' + file.id, $event.target.checked)">
                    <div class="fh-file-card__badge">
                        <span v-if="isMicrostock" class="badge fh-ms-badge" :class="msBadge(file).cls">{{ msBadge(file).label }}</span>
                    </div>
                    <div class="fh-file-card__icon">
                        <a :href="fileShowHref(file.id)" draggable="false"
                           @click="onBrowseSelect($event, 'file', file)"
                           @dblclick="onBrowseOpen($event, 'file', file)"><FhIcon :mime="file.mime" /></a>
                    </div>
                    <a class="fh-file-card__name" :href="fileShowHref(file.id)" draggable="false"
                       @click="onBrowseSelect($event, 'file', file)"
                       @dblclick="onBrowseOpen($event, 'file', file)">{{ file.name }}</a>
                    <div class="fh-file-card__meta">{{ file.size_human || msgs.typeFile || 'File' }}</div>
                </div>
                <div v-if="isEmpty && !parentMeta" class="fh-empty">
                    <i class="bi bi-folder2-open fh-empty__icon" /><div class="fh-empty__text">{{ msgs.emptyFolder || 'Empty' }}</div>
                </div>
            </div>
        </div>

        <!-- Trash -->
        <div v-show="panel === 'trash'" data-fh-panel="trash">
            <div class="table-responsive">
                <table class="table fh-table">
                    <tbody data-fh-trash-body>
                        <tr v-for="folder in (trash.folders || [])" :key="'tf:' + folder.id">
                            <td class="fh-table__icon-cell"><FhIcon is-folder /></td>
                            <td><span class="fh-table__name-text">{{ folder.name }}</span></td>
                            <td class="fh-table__type">{{ msgs.typeFolder || 'Folder' }}</td>
                            <td class="fh-table__size">{{ folder.deleted_at || '' }}</td>
                            <td class="fh-table__actions">
                                <button type="button" class="fh-table__action-btn" @click="mutations.restoreEntity('folder', folder.id)"><i class="bi bi-arrow-counterclockwise" /></button>
                                <button type="button" class="fh-table__action-btn is-danger" @click="mutations.forceDeleteEntity('folder', folder.id)"><i class="bi bi-trash" /></button>
                            </td>
                        </tr>
                        <tr v-for="file in (trash.files || [])" :key="'tfile:' + file.id">
                            <td class="fh-table__icon-cell"><FhIcon :mime="file.mime" /></td>
                            <td><span class="fh-table__name-text">{{ file.name }}</span></td>
                            <td class="fh-table__type">{{ file.mime || msgs.typeFile }}</td>
                            <td class="fh-table__size">{{ file.deleted_at || '' }}</td>
                            <td class="fh-table__actions">
                                <button type="button" class="fh-table__action-btn" @click="mutations.restoreEntity('file', file.id)"><i class="bi bi-arrow-counterclockwise" /></button>
                                <button type="button" class="fh-table__action-btn is-danger" @click="mutations.forceDeleteEntity('file', file.id)"><i class="bi bi-trash" /></button>
                            </td>
                        </tr>
                        <tr v-if="!(trash.folders || []).length && !(trash.files || []).length">
                            <td colspan="5"><div class="fh-empty"><div class="fh-empty__text">{{ msgs.trashEmpty || 'Trash is empty' }}</div></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-outline-secondary btn-sm mt-2" :href="browseHref()" @click="go($event, browseHref())">
                <i class="bi bi-arrow-left me-1" />{{ labels.back || 'Back' }}
            </a>
        </div>

        <!-- Show -->
        <div v-show="panel === 'show'" data-fh-panel="show">
            <div v-if="showFile" class="row g-3">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary">
                        <div class="card-body">
                            <div class="fh-detail__hero">
                                <div class="fh-detail__hero-icon"><FhIcon :mime="showFile.mime" /></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fh-detail__hero-name">{{ showFile.name }}</div>
                                    <div class="fh-detail__hero-meta">{{ showFile.mime }} · {{ showFile.size_human }}</div>
                                </div>
                            </div>
                            <div class="fh-stats mt-3">
                                <div class="fh-stat"><div class="fh-stat__value">{{ showFile.view_count || 0 }}</div><div class="fh-stat__label">{{ labels.views || 'Views' }}</div></div>
                                <div class="fh-stat"><div class="fh-stat__value">{{ showFile.download_count || 0 }}</div><div class="fh-stat__label">{{ labels.downloads || 'Downloads' }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-outline-secondary btn-sm" :href="browseHref({ folder_id: showFile.folder_id || null })"
                           @click="go($event, browseHref({ folder_id: showFile.folder_id || null }))">
                            <i class="bi bi-arrow-left me-1" />{{ labels.back || 'Back' }}
                        </a>
                        <a class="btn btn-primary btn-sm" :href="downloadHref(showFile)"><i class="bi bi-download me-1" />{{ labels.download || 'Download' }}</a>
                        <button v-if="canEdit('file')" type="button" class="btn btn-outline-secondary btn-sm"
                                @click="openModal('rename', { entity: 'file', id: showFile.id, name: showFile.name })">{{ act.rename || 'Rename' }}</button>
                        <button v-if="canEdit('file')" type="button" class="btn btn-outline-secondary btn-sm"
                                @click="openModal('share', { entity: 'file', id: showFile.id, visibility: showFile.visibility || 'private', shareUrl: showFile.share_url })">{{ act.share || 'Share' }}</button>
                        <button v-if="canDelete('file')" type="button" class="btn btn-outline-danger btn-sm"
                                @click="mutations.deleteEntity('file', showFile.id)">{{ act.delete || 'Delete' }}</button>
                    </div>
                    <div class="small text-muted mb-1">{{ labels.visibility || 'Visibility' }}</div>
                    <span class="fh-badge" :class="showFile.visibility === visLink ? 'fh-badge--link' : 'fh-badge--private'">{{ visLabel(showFile.visibility) }}</span>
                </div>
            </div>
        </div>

        <div v-if="contextMenu" class="fh-context-menu" role="menu"
             :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }" @click.stop>
            <button type="button" class="fh-context-menu__item" @click="ctxAction('open')"><i class="bi bi-folder2-open" />{{ act.open || act.view || 'Open' }}</button>
            <button type="button" class="fh-context-menu__item" @click="ctxAction('rename')"><i class="bi bi-pencil" />{{ act.rename || 'Rename' }}</button>
            <button type="button" class="fh-context-menu__item" @click="ctxAction('move')"><i class="bi bi-folder-symlink" />{{ act.move || 'Move' }}</button>
            <button type="button" class="fh-context-menu__item" @click="ctxAction('share')"><i class="bi bi-share" />{{ act.share || 'Share' }}</button>
            <button type="button" class="fh-context-menu__item is-danger" @click="ctxAction('delete')"><i class="bi bi-trash" />{{ act.delete || 'Delete' }}</button>
        </div>

        <div v-if="modal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)" @click.self="modal = null">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <template v-if="modal.type === 'folder'">{{ ui.newFolder || 'New folder' }}</template>
                            <template v-else-if="modal.type === 'rename'">{{ act.rename || 'Rename' }}</template>
                            <template v-else-if="modal.type === 'share'">{{ act.share || 'Share' }}</template>
                            <template v-else>{{ act.move || 'Move' }}</template>
                        </h5>
                        <button type="button" class="btn-close" @click="modal = null" />
                    </div>
                    <div class="modal-body">
                        <div v-if="modal.type === 'folder' || modal.type === 'rename'">
                            <label class="form-label">{{ labels.name || 'Name' }}</label>
                            <input v-model="modal.name" type="text" class="form-control" autofocus @keyup.enter="submitModal">
                        </div>
                        <div v-else-if="modal.type === 'share'">
                            <label class="form-label">{{ labels.visibility || 'Visibility' }}</label>
                            <select v-model="modal.visibility" class="form-select">
                                <option value="private">{{ labels.private || 'Private' }}</option>
                                <option :value="visLink">{{ labels.anyone_with_link || 'Anyone with the link' }}</option>
                            </select>
                            <input v-if="modal.shareUrl && modal.visibility === visLink" :value="modal.shareUrl" type="text" class="form-control mt-2" readonly>
                        </div>
                        <div v-else>
                            <label class="form-label">{{ ui.targetFolder || 'Target folder id (empty = root)' }}</label>
                            <input v-model="modal.target" type="text" class="form-control mb-2" placeholder="folder uuid or empty">
                            <div class="list-group list-group-flush">
                                <button type="button" class="list-group-item list-group-item-action" @click="modal.target = ''">{{ msgs.rootLabel || 'Root' }}</button>
                                <button v-for="f in folders" :key="'mv:' + f.id" type="button" class="list-group-item list-group-item-action" @click="modal.target = f.id">
                                    <i class="bi bi-folder-fill me-1" />{{ f.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" @click="modal = null">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" @click="submitModal">{{ labels.save || 'Save' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

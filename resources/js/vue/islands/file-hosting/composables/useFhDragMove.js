/**
 * Drive-like drag-move onto folder / breadcrumb / parent `...`.
 * Protocol: moveItemsRequest stays in drag-move.js; gestures owned by Vue.
 */
import { ref } from 'vue';
import { FH_ITEMS_MIME, canDropOnFolder } from '../../../../file-hosting/drag-move.js';
import { toast } from './useFhApi.js';

export { canDropOnFolder };

/**
 * @param {object} opts
 * @param {import('vue').Ref<Set<string>>} opts.selected
 * @param {() => void} opts.clearSelection
 * @param {(key: string, on?: boolean) => void} opts.toggleSelect
 * @param {(keys: string[], targetFolderId: string|null) => Promise<boolean>} opts.moveItems
 * @param {object} opts.config
 */
export function useFhDragMove(opts) {
    const { selected, clearSelection, toggleSelect, moveItems } = opts;

    function cfg() {
        return typeof opts.config === 'function' ? opts.config() : opts.config;
    }

    const dragging = ref(false);
    const dropTarget = ref(null); // string key: 'root' | `folder:${id}`

    function isInteractive(el) {
        return !!el?.closest?.('input, button, a.fh-table__action-btn, [data-bs-toggle], .fh-file-card__select');
    }

    function isFhDrag(dt) {
        if (!dt?.types) return false;
        return Array.from(dt.types).includes(FH_ITEMS_MIME);
    }

    function onRowDragStart(e, entity, id) {
        if (entity === 'parent' || !id) {
            e.preventDefault();
            return;
        }
        if (isInteractive(e.target)) {
            e.preventDefault();
            return;
        }

        const key = `${entity}:${id}`;
        if (!selected.value.has(key)) {
            clearSelection();
            toggleSelect(key, true);
        }

        const keys = Array.from(selected.value);
        dragging.value = true;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData(FH_ITEMS_MIME, JSON.stringify(keys));
        e.dataTransfer.setData('text/plain', keys.join(','));
    }

    function onDragEnd() {
        dragging.value = false;
        dropTarget.value = null;
    }

    function targetKey(folderId) {
        if (folderId === null || folderId === undefined || folderId === '' || folderId === 'root') {
            return 'root';
        }
        return `folder:${folderId}`;
    }

    function onDropOver(e, folderId) {
        if (!isFhDrag(e.dataTransfer) && !dragging.value) return;
        const keys = Array.from(selected.value);
        const target = (folderId === '' || folderId === 'root' || folderId == null) ? null : folderId;
        if (!canDropOnFolder(target, keys)) {
            dropTarget.value = null;
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        dropTarget.value = targetKey(folderId);
    }

    function onDropLeave(e, folderId) {
        const key = targetKey(folderId);
        if (dropTarget.value !== key) return;
        const related = e.relatedTarget;
        if (related && e.currentTarget?.contains?.(related)) return;
        dropTarget.value = null;
    }

    async function onDrop(e, folderId) {
        if (!isFhDrag(e.dataTransfer) && !dragging.value) return;

        e.preventDefault();
        e.stopPropagation();

        let keys = [];
        try {
            const raw = e.dataTransfer.getData(FH_ITEMS_MIME) || e.dataTransfer.getData('text/plain');
            if (raw && raw.startsWith('[')) {
                keys = JSON.parse(raw);
            } else if (raw) {
                keys = raw.split(',').filter(Boolean);
            }
        } catch {
            keys = Array.from(selected.value);
        }
        if (!keys.length) {
            keys = Array.from(selected.value);
        }

        const target = (folderId === '' || folderId === 'root' || folderId == null) ? null : folderId;
        dropTarget.value = null;
        dragging.value = false;

        if (!canDropOnFolder(target, keys)) {
            toast(cfg()?.messages?.moveInvalid || cfg()?.messages?.error || 'Cannot move here', 'danger');
            return;
        }

        await moveItems(keys, target);
    }

    function isDropTarget(folderId) {
        return dropTarget.value === targetKey(folderId);
    }

    function isDraggingSource(entity, id) {
        return dragging.value && selected.value.has(`${entity}:${id}`);
    }

    return {
        dragging,
        dropTarget,
        onRowDragStart,
        onDragEnd,
        onDropOver,
        onDropLeave,
        onDrop,
        isDropTarget,
        isDraggingSource,
    };
}

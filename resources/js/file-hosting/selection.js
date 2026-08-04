/**
 * Pure Drive-like selection helpers for File Hosting browse (Vue island).
 * Keys: "file:{uuid}" | "folder:{uuid}".
 */

/**
 * @param {Array<{id: string}>} folders
 * @param {Array<{id: string}>} files
 * @returns {string[]}
 */
export function listingKeys(folders = [], files = []) {
    return [
        ...folders.map((f) => `folder:${f.id}`),
        ...files.map((f) => `file:${f.id}`),
    ];
}

/**
 * Inclusive range between two keys in visible list order.
 *
 * @param {string[]} ordered
 * @param {string|null} fromKey
 * @param {string} toKey
 * @returns {string[]}
 */
export function rangeKeys(ordered, fromKey, toKey) {
    if (!Array.isArray(ordered) || ordered.length === 0) {
        return toKey ? [toKey] : [];
    }
    const toIdx = ordered.indexOf(toKey);
    if (toIdx < 0) {
        return toKey ? [toKey] : [];
    }
    const fromIdx = fromKey ? ordered.indexOf(fromKey) : toIdx;
    if (fromIdx < 0) {
        return [toKey];
    }
    const start = Math.min(fromIdx, toIdx);
    const end = Math.max(fromIdx, toIdx);

    return ordered.slice(start, end + 1);
}

/**
 * Apply a browse row/card click per Drive contract.
 * - plain click -> select only that key
 * - ctrl/cmd -> toggle
 * - shift -> range from anchor
 *
 * @param {object} opts
 * @param {string} opts.key
 * @param {MouseEvent|{ shiftKey?: boolean, ctrlKey?: boolean, metaKey?: boolean }} opts.event
 * @param {Iterable<string>} opts.selected
 * @param {string|null} opts.anchorKey
 * @param {string[]} opts.orderedKeys
 * @returns {{ next: Set<string>, anchorKey: string|null }}
 */
export function applyBrowseClick({ key, event, selected, anchorKey, orderedKeys }) {
    const cur = selected instanceof Set ? selected : new Set(selected || []);
    const shift = !!event?.shiftKey;
    const toggle = !!(event?.ctrlKey || event?.metaKey);

    if (shift) {
        const keys = rangeKeys(orderedKeys, anchorKey || key, key);

        return { next: new Set(keys), anchorKey: anchorKey || key };
    }

    if (toggle) {
        const next = new Set(cur);
        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }

        return { next, anchorKey: key };
    }

    return { next: new Set([key]), anchorKey: key };
}
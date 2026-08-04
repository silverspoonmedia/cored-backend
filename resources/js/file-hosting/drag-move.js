/**
 * File Hosting move helpers — framework-agnostic.
 * Vue owns drag gestures (useFhDragMove); this module keeps MIME + HTTP move.
 */

export const FH_ITEMS_MIME = 'application/x-fh-items';

/**
 * @param {string|null|undefined} folderId
 * @param {string[]} keys
 */
export function canDropOnFolder(folderId, keys) {
    // Root (null) is always a valid destination for items not already at root
    // (server no-ops same-parent moves; we still allow the gesture).
    if (folderId !== null && folderId !== undefined && folderId !== '') {
        if (keys.includes('folder:' + folderId)) {
            return false;
        }
    }
    return true;
}

/**
 * Sequential web PATCH move for files/folders.
 *
 * @param {object} opts
 * @param {string[]} opts.keys
 * @param {string|null} opts.targetFolderId
 * @param {object} opts.config
 * @param {string} opts.csrfToken
 * @param {(key: string) => {type: string, id: string}|null} opts.parseKey
 */
export async function moveItemsRequest(opts) {
    const { keys, targetFolderId, config, csrfToken, parseKey } = opts;
    const owner = config.ownerUserId || '';
    const workspace = config.workspaceId || '';
    let lastMessage = '';
    let okCount = 0;

    for (const key of keys) {
        const parsed = parseKey(key);
        if (!parsed) {
            continue;
        }

        if (parsed.type === 'folder' && targetFolderId && parsed.id === targetFolderId) {
            continue;
        }

        const isFile = parsed.type === 'file';
        const base = isFile ? config.fileRenameBase : config.folderRenameBase;
        if (!base) {
            return { ok: false, message: config.messages?.error || 'Error' };
        }

        const url = base + '/' + parsed.id + '/move';
        const body = new FormData();
        body.append('_method', 'PATCH');
        body.append('_token', csrfToken);
        if (owner) {
            body.append('user_id', owner);
        }
        if (workspace) {
            body.append('workspace_id', workspace);
        }
        if (isFile) {
            body.append('folder_id', targetFolderId || '');
        } else {
            body.append('parent_id', targetFolderId || '');
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body,
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                okCount += 1;
                lastMessage = data.message || lastMessage;
            } else {
                return {
                    ok: false,
                    message: data.message || config.messages?.moveFailed || config.messages?.error || 'Move failed',
                };
            }
        } catch (err) {
            return { ok: false, message: config.messages?.networkError || 'Network error' };
        }
    }

    if (okCount === 0) {
        return { ok: false, message: config.messages?.moveInvalid || config.messages?.error || 'Nothing to move' };
    }

    return { ok: true, message: lastMessage || config.messages?.moved || config.messages?.success };
}

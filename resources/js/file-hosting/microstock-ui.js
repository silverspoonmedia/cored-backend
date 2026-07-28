/**
 * Shared Microstock UI helpers (listing badges, review affordances, progress text).
 */

/**
 * @param {{status?: string, stages?: {vision?: string, thinking?: string, result?: string}}|null} ms
 * @param {Record<string, string>} labels
 * @returns {{label: string, cls: string}}
 */
export function resolveMicrostockBadge(ms, labels) {
    const defaults = {
        noMetadata: 'No metadata',
        statusPending: 'Pending',
        statusGenerated: 'Generated',
        statusFinalized: 'Finalized',
        statusFailed: 'Analysis failed',
        stageVision: 'Vision…',
        stageThinking: 'Thinking…',
        stageQueuedThinking: 'Queued thinking',
        stageDegraded: 'Taking longer…',
    };
    const L = { ...defaults, ...labels };

    if (!ms || !ms.status) {
        return { label: L.noMetadata, cls: 'text-bg-secondary' };
    }

    const status = ms.status;
    const stages = ms.stages || {};
    const vision = stages.vision || 'pending';
    const thinking = stages.thinking || 'pending';

    if (status === 'finalized') {
        return { label: L.statusFinalized, cls: 'text-bg-success' };
    }
    if (status === 'generated') {
        return { label: L.statusGenerated, cls: 'text-bg-info' };
    }
    if (status === 'failed') {
        return { label: L.statusFailed, cls: 'text-bg-danger' };
    }

    if (status === 'pending') {
        if (ms.phase === 'degraded') {
            return { label: L.stageDegraded, cls: 'text-bg-warning' };
        }
        if (vision === 'running') {
            return { label: L.stageVision, cls: 'text-bg-warning' };
        }
        if (vision === 'ok' && thinking === 'running') {
            return { label: L.stageThinking, cls: 'text-bg-warning' };
        }
        if (vision === 'ok' && (thinking === 'pending' || thinking === 'skipped')) {
            return { label: L.stageQueuedThinking, cls: 'text-bg-warning' };
        }
        return { label: L.statusPending, cls: 'text-bg-warning' };
    }

    return { label: L.statusPending, cls: 'text-bg-warning' };
}

/**
 * @param {{status?: string, stages?: {vision?: string, thinking?: string}}|null} ms
 */
export function isMicrostockReviewActionable(ms) {
    if (!ms || !ms.status) {
        return false;
    }

    return ['generated', 'finalized', 'failed'].includes(ms.status);
}

/**
 * @param {number|null} processed
 * @param {number|null} total
 * @param {number|null} failed
 * @param {Record<string, string>} labels
 */
export function formatCollectionProgress(processed, total, failed, labels) {
    const tmpl = labels.progress || 'Analyzed :processed of :total';
    let text = tmpl
        .replace(':processed', String(processed ?? 0))
        .replace(':total', String(total ?? 0));
    const failCount = Number(failed ?? 0);
    if (failCount > 0) {
        const failTmpl = labels.progressFailed || '(:failed failed)';
        text += ' ' + failTmpl.replace(':failed', String(failCount));
    }
    return text;
}

/**
 * @param {{vision?: string, thinking?: string, result?: string}|null} stages
 * @param {Record<string, string>} labels
 */
export function stageStripItems(stages, labels) {
    const L = {
        stageVisionLabel: 'Vision',
        stageThinkingLabel: 'Thinking',
        stageResultLabel: 'Result',
        stageOk: 'ok',
        stageRunning: '…',
        stagePending: '—',
        stageFailed: 'failed',
        ...labels,
    };
    const s = stages || {};

    function icon(state) {
        if (state === 'ok') {
            return '✓';
        }
        if (state === 'running') {
            return L.stageRunning;
        }
        if (state === 'failed') {
            return '✗';
        }
        return L.stagePending;
    }

    return [
        { key: 'vision', label: L.stageVisionLabel, state: s.vision || 'pending', icon: icon(s.vision || 'pending') },
        { key: 'thinking', label: L.stageThinkingLabel, state: s.thinking || 'pending', icon: icon(s.thinking || 'pending') },
        { key: 'result', label: L.stageResultLabel, state: s.result || 'pending', icon: icon(s.result || 'pending') },
    ];
}

export function isTerminalMetadataStatus(status) {
    return status === 'generated' || status === 'finalized' || status === 'failed';
}

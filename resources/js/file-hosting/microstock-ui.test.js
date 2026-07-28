import { describe, expect, it } from 'vitest';
import {
    formatCollectionProgress,
    isMicrostockReviewActionable,
    isTerminalMetadataStatus,
    resolveMicrostockBadge,
    stageStripItems,
} from './microstock-ui.js';

const labels = {
    statusPending: 'Pending',
    statusGenerated: 'Generated',
    statusFinalized: 'Finalized',
    statusFailed: 'Failed',
    stageVision: 'Vision…',
    stageThinking: 'Thinking…',
    stageQueuedThinking: 'Queued thinking',
    progress: ':processed of :total',
    progressFailed: '(:failed failed)',
};

describe('resolveMicrostockBadge', () => {
    it('maps vision running to stage label', () => {
        const badge = resolveMicrostockBadge({
            status: 'pending',
            stages: { vision: 'running', thinking: 'pending' },
        }, labels);
        expect(badge.label).toBe('Vision…');
        expect(badge.cls).toContain('warning');
    });

    it('maps failed status', () => {
        const badge = resolveMicrostockBadge({ status: 'failed', stages: {} }, labels);
        expect(badge.label).toBe('Failed');
        expect(badge.cls).toContain('danger');
    });
});

describe('isMicrostockReviewActionable', () => {
    it('hides review while analysis is pending', () => {
        expect(isMicrostockReviewActionable({
            status: 'pending',
            stages: { vision: 'running', thinking: 'pending' },
        })).toBe(false);
    });

    it('allows review for generated and failed', () => {
        expect(isMicrostockReviewActionable({ status: 'generated' })).toBe(true);
        expect(isMicrostockReviewActionable({ status: 'failed' })).toBe(true);
    });
});

describe('formatCollectionProgress', () => {
    it('appends failed count when present', () => {
        expect(formatCollectionProgress(2, 5, 1, labels)).toBe('2 of 5 (1 failed)');
    });
});

describe('stageStripItems', () => {
    it('returns three stages with icons', () => {
        const items = stageStripItems({ vision: 'ok', thinking: 'running', result: 'pending' }, {});
        expect(items).toHaveLength(3);
        expect(items[0].icon).toBe('✓');
        expect(items[1].icon).toBe('…');
    });
});

describe('isTerminalMetadataStatus', () => {
    it('detects terminal states', () => {
        expect(isTerminalMetadataStatus('generated')).toBe(true);
        expect(isTerminalMetadataStatus('pending')).toBe(false);
    });
});

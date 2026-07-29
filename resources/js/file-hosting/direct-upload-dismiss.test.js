import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    DONE_ROW_DISMISS_MS,
    DONE_ROW_FADE_MS,
    scheduleDoneRowDismiss,
} from './direct-upload.js';

/**
 * Minimal DOM stub — vitest runs in node (no jsdom).
 *
 * @returns {{
 *   classList: { add: Function, remove: Function, contains: Function },
 *   remove: Function,
 *   parentNode: null,
 *   _classes: Set<string>,
 *   _removed: boolean,
 * }}
 */
function makeRow() {
    const classes = new Set();
    const row = {
        _classes: classes,
        _removed: false,
        classList: {
            add(name) {
                classes.add(name);
            },
            remove(name) {
                classes.delete(name);
            },
            contains(name) {
                return classes.has(name);
            },
        },
        remove() {
            row._removed = true;
        },
        parentNode: null,
    };
    return row;
}

describe('DONE_ROW_DISMISS_MS', () => {
    it('is 5 seconds', () => {
        expect(DONE_ROW_DISMISS_MS).toBe(5000);
    });

    it('fade is part of the total dismiss window', () => {
        expect(DONE_ROW_FADE_MS).toBeGreaterThan(0);
        expect(DONE_ROW_FADE_MS).toBeLessThan(DONE_ROW_DISMISS_MS);
    });
});

describe('scheduleDoneRowDismiss', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('returns null when row is missing', () => {
        expect(scheduleDoneRowDismiss(null)).toBeNull();
        expect(scheduleDoneRowDismiss(undefined)).toBeNull();
    });

    it('adds is-dismissing near the end of the delay, then removes the row', () => {
        const row = makeRow();
        const active = new Map([['r1', { state: 'done' }]]);

        scheduleDoneRowDismiss(row, {
            active,
            rowId: 'r1',
            delayMs: 5000,
            fadeMs: 300,
        });

        vi.advanceTimersByTime(4699);
        expect(row._removed).toBe(false);
        expect(row.classList.contains('is-dismissing')).toBe(false);
        expect(active.has('r1')).toBe(true);

        vi.advanceTimersByTime(1);
        expect(row.classList.contains('is-dismissing')).toBe(true);
        expect(row._removed).toBe(false);

        vi.advanceTimersByTime(299);
        expect(row._removed).toBe(false);

        vi.advanceTimersByTime(1);
        expect(row._removed).toBe(true);
        expect(active.has('r1')).toBe(false);
    });

    it('removes immediately when fadeMs is 0 after full delay', () => {
        const row = makeRow();
        const active = new Map([['r2', { state: 'done' }]]);

        scheduleDoneRowDismiss(row, {
            active,
            rowId: 'r2',
            delayMs: 1000,
            fadeMs: 0,
        });

        vi.advanceTimersByTime(999);
        expect(row._removed).toBe(false);

        vi.advanceTimersByTime(1);
        expect(row._removed).toBe(true);
        expect(active.has('r2')).toBe(false);
    });

    it('cancel prevents removal and clears dismissing class', () => {
        const row = makeRow();
        const active = new Map([['r3', { state: 'done' }]]);

        const handle = scheduleDoneRowDismiss(row, {
            active,
            rowId: 'r3',
            delayMs: 5000,
            fadeMs: 300,
        });

        vi.advanceTimersByTime(4700);
        expect(row.classList.contains('is-dismissing')).toBe(true);

        handle.cancel();
        expect(row.classList.contains('is-dismissing')).toBe(false);

        vi.advanceTimersByTime(10_000);
        expect(row._removed).toBe(false);
        expect(active.has('r3')).toBe(true);
    });

    it('calls onBeforeRemove once before DOM removal', () => {
        const row = makeRow();
        const onBeforeRemove = vi.fn();

        scheduleDoneRowDismiss(row, {
            delayMs: 100,
            fadeMs: 0,
            onBeforeRemove,
        });

        expect(onBeforeRemove).not.toHaveBeenCalled();
        vi.advanceTimersByTime(100);
        expect(onBeforeRemove).toHaveBeenCalledTimes(1);
        expect(onBeforeRemove).toHaveBeenCalledWith(row);
        expect(row._removed).toBe(true);
    });
});

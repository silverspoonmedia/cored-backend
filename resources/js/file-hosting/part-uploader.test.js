import { describe, expect, it, vi } from 'vitest';
import {
    createPartProgressTracker,
    sortPartsByNumber,
    uploadPartsParallel,
} from './part-uploader.js';

describe('createPartProgressTracker', () => {
    it('sums loaded bytes across parts', () => {
        const tracker = createPartProgressTracker();
        tracker.set(1, 100);
        tracker.set(2, 250);
        tracker.set(3, 50);
        expect(tracker.total()).toBe(400);
    });

    it('re-PUT of a part resets its slot instead of double-counting', () => {
        const tracker = createPartProgressTracker();
        tracker.set(1, 100);
        tracker.set(1, 0); // retry resets
        tracker.set(1, 40);
        expect(tracker.total()).toBe(40);
    });
});

describe('sortPartsByNumber', () => {
    it('orders ascending without mutating input', () => {
        const input = [{ part_number: 3 }, { part_number: 1 }, { part_number: 2 }];
        const sorted = sortPartsByNumber(input);
        expect(sorted.map((p) => p.part_number)).toEqual([1, 2, 3]);
        expect(input.map((p) => p.part_number)).toEqual([3, 1, 2]);
    });
});

describe('uploadPartsParallel', () => {
    it('uploads every part and returns results sorted by part_number', async () => {
        const done = [];
        const uploadOne = vi.fn(async (partNumber) => {
            // Finish out of order to prove final sort.
            await new Promise((r) => setTimeout(r, (5 - partNumber) * 5));
            done.push(partNumber);
            return { part_number: partNumber, etag: 'e' + partNumber, size: 10 };
        });

        const parts = await uploadPartsParallel({ partCount: 4, concurrency: 2, uploadOne });

        expect(uploadOne).toHaveBeenCalledTimes(4);
        expect(parts.map((p) => p.part_number)).toEqual([1, 2, 3, 4]);
    });

    it('never exceeds the concurrency limit', async () => {
        let inFlight = 0;
        let peak = 0;
        const uploadOne = async () => {
            inFlight += 1;
            peak = Math.max(peak, inFlight);
            await new Promise((r) => setTimeout(r, 15));
            inFlight -= 1;
            return { part_number: 0, etag: 'x', size: 1 };
        };

        await uploadPartsParallel({ partCount: 10, concurrency: 3, uploadOne });
        expect(peak).toBeLessThanOrEqual(3);
        expect(peak).toBeGreaterThan(1); // actually parallel
    });

    it('propagates an upload failure (aborts the batch)', async () => {
        const uploadOne = async (partNumber) => {
            if (partNumber === 2) {
                throw new Error('Storage PUT failed with status 500');
            }
            return { part_number: partNumber, etag: 'e', size: 1 };
        };

        await expect(
            uploadPartsParallel({ partCount: 4, concurrency: 2, uploadOne }),
        ).rejects.toThrow('status 500');
    });
});

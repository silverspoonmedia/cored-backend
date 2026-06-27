import { describe, expect, it } from 'vitest';
import { runWithConcurrency } from './upload-queue.js';

describe('runWithConcurrency', () => {
    it('resolves immediately for an empty list', async () => {
        await expect(runWithConcurrency([], 2, async () => {})).resolves.toBeUndefined();
    });

    it('never exceeds the concurrency limit', async () => {
        const items = [1, 2, 3, 4, 5];
        let inFlight = 0;
        let peak = 0;
        const started = [];

        await runWithConcurrency(items, 2, async (item) => {
            started.push(item);
            inFlight += 1;
            peak = Math.max(peak, inFlight);

            await new Promise((resolve) => {
                setTimeout(resolve, 10);
            });

            inFlight -= 1;
        });

        expect(started).toEqual(items);
        expect(peak).toBeLessThanOrEqual(2);
        expect(peak).toBeGreaterThan(1);
    });

    it('completes every item', async () => {
        const completed = [];

        await runWithConcurrency(['a', 'b', 'c'], 2, async (item) => {
            completed.push(item);
        });

        expect(completed).toEqual(['a', 'b', 'c']);
    });

    it('continues when one worker rejects', async () => {
        const completed = [];

        await expect(runWithConcurrency([1, 2, 3], 2, async (item) => {
            if (item === 2) {
                throw new Error('boom');
            }

            completed.push(item);
        })).rejects.toThrow('boom');

        expect(completed).toContain(1);
        expect(completed).toContain(3);
    });
});

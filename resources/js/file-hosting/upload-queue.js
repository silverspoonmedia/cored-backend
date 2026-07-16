/**
 * Run async workers over items with a maximum in-flight concurrency.
 *
 * @template T
 * @param {T[]} items
 * @param {number} limit
 * @param {(item: T, index: number) => Promise<void>} worker
 * @returns {Promise<void>}
 */
export async function runWithConcurrency(items, limit, worker) {
    if (items.length === 0) {
        return;
    }

    const concurrency = Math.max(1, limit);
    let nextIndex = 0;

    async function runWorker() {
        while (nextIndex < items.length) {
            const index = nextIndex;
            nextIndex += 1;
            await worker(items[index], index);
        }
    }

    const runners = Array.from(
        { length: Math.min(concurrency, items.length) },
        () => runWorker(),
    );

    await Promise.all(runners);
}

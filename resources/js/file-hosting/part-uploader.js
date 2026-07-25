/**
 * Pure helpers for parallel multipart upload orchestration.
 * No DOM / XHR — unit-testable via Vitest (node env).
 */
import { runWithConcurrency } from './upload-queue.js';

/**
 * Tracks aggregate uploaded bytes across parallel parts.
 * Each part owns one slot keyed by part number, so re-PUTs and out-of-order
 * completion never double-count (unlike a single running total).
 */
export function createPartProgressTracker() {
    /** @type {Map<number, number>} */
    const loadedByPart = new Map();

    return {
        /**
         * @param {number} partNumber
         * @param {number} loadedBytes
         */
        set(partNumber, loadedBytes) {
            loadedByPart.set(partNumber, loadedBytes);
        },

        /** @returns {number} total bytes across all parts */
        total() {
            let sum = 0;
            loadedByPart.forEach(function (loaded) {
                sum += loaded;
            });
            return sum;
        },
    };
}

/**
 * Upload every part with bounded concurrency, collecting {part_number, etag, size}.
 * Result is sorted ascending by part_number (multipart Complete requires order).
 *
 * @param {object} options
 * @param {number} options.partCount
 * @param {number} options.concurrency
 * @param {(partNumber: number) => Promise<{ part_number: number, etag: string, size: number }>} options.uploadOne
 * @returns {Promise<Array<{ part_number: number, etag: string, size: number }>>}
 */
export async function uploadPartsParallel({ partCount, concurrency, uploadOne }) {
    const partNumbers = [];
    for (let partNumber = 1; partNumber <= partCount; partNumber++) {
        partNumbers.push(partNumber);
    }

    const parts = [];
    await runWithConcurrency(partNumbers, concurrency, async function (partNumber) {
        const done = await uploadOne(partNumber);
        parts.push(done);
    });

    return sortPartsByNumber(parts);
}

/**
 * @param {Array<{ part_number: number }>} parts
 * @returns {Array<{ part_number: number }>} new sorted array
 */
export function sortPartsByNumber(parts) {
    return parts.slice().sort(function (a, b) {
        return a.part_number - b.part_number;
    });
}

/**
 * Pure helpers for File Hosting browser upload progress / transfer rate.
 * No DOM — unit-testable via Vitest.
 */

/**
 * @param {number} bytes
 * @param {number} elapsedMs
 * @returns {number} bytes per second
 */
export function computeTransferRate(bytes, elapsedMs) {
    if (!Number.isFinite(bytes) || !Number.isFinite(elapsedMs) || bytes <= 0 || elapsedMs <= 0) {
        return 0;
    }

    return bytes / (elapsedMs / 1000);
}

/**
 * Create a sliding-window transfer-rate sampler (instantaneous, not cumulative).
 *
 * Why not `totalBytes / totalElapsed`? A cumulative average drifts downward for the
 * rest of the upload after any single stall, exaggerating the perceived slowdown.
 * A windowed sampler reports the rate over the recent window only, so it recovers
 * as soon as throughput recovers and tolerates an isolated stall.
 *
 * @param {number} [windowMs=5000]
 * @returns {{ add: (bytesTotal: number, atMs?: number) => void, rate: (atMs?: number) => number }}
 */
export function createRateSampler(windowMs = 5000) {
    /** @type {Array<{ t: number, bytes: number }>} */
    const samples = [];

    function prune(now) {
        while (samples.length > 0 && now - samples[0].t > windowMs) {
            samples.shift();
        }
    }

    return {
        /**
         * Record the cumulative uploaded byte total at a point in time.
         * @param {number} bytesTotal
         * @param {number} [atMs]
         */
        add(bytesTotal, atMs) {
            const now = typeof atMs === 'number' ? atMs : Date.now();
            samples.push({ t: now, bytes: bytesTotal });
            prune(now);
        },

        /**
         * Bytes/sec across the recent window; 0 when not enough data.
         * @param {number} [atMs]
         * @returns {number}
         */
        rate(atMs) {
            const now = typeof atMs === 'number' ? atMs : Date.now();
            prune(now);

            if (samples.length < 2) {
                return 0;
            }

            const first = samples[0];
            const last = samples[samples.length - 1];
            const dt = (last.t - first.t) / 1000;

            if (dt <= 0) {
                return 0;
            }

            return (last.bytes - first.bytes) / dt;
        },
    };
}

/**
 * @param {number} bytesPerSecond
 * @returns {string}
 */
export function formatTransferRate(bytesPerSecond) {
    if (!Number.isFinite(bytesPerSecond) || bytesPerSecond <= 0) {
        return '0 B/s';
    }

    const units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    let value = bytesPerSecond;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    if (unitIndex === 0) {
        return Math.round(value) + ' ' + units[unitIndex];
    }

    return value.toFixed(1) + ' ' + units[unitIndex];
}

/**
 * @param {number} uploaded
 * @param {number} total
 * @returns {number} integer percent 0–100
 */
export function computeByteProgressPercent(uploaded, total) {
    if (!Number.isFinite(uploaded) || !Number.isFinite(total) || total <= 0) {
        return 0;
    }

    if (uploaded <= 0) {
        return 0;
    }

    if (uploaded >= total) {
        return 100;
    }

    return Math.round((uploaded / total) * 100);
}

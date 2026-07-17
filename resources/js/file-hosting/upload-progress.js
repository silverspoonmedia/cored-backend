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

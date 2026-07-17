import { describe, expect, it } from 'vitest';
import {
    computeByteProgressPercent,
    computeTransferRate,
    formatTransferRate,
} from './upload-progress.js';

describe('computeTransferRate', () => {
    it('returns 0 for zero bytes', () => {
        expect(computeTransferRate(0, 1000)).toBe(0);
    });

    it('returns 0 for zero or negative elapsed', () => {
        expect(computeTransferRate(1024, 0)).toBe(0);
        expect(computeTransferRate(1024, -1)).toBe(0);
    });

    it('returns bytes per second for 1 MiB in 1s', () => {
        expect(computeTransferRate(1_048_576, 1000)).toBe(1_048_576);
    });

    it('scales with elapsed time', () => {
        expect(computeTransferRate(2_000, 500)).toBe(4_000);
    });
});

describe('formatTransferRate', () => {
    it('formats zero', () => {
        expect(formatTransferRate(0)).toBe('0 B/s');
    });

    it('formats negative or non-finite as zero', () => {
        expect(formatTransferRate(-10)).toBe('0 B/s');
        expect(formatTransferRate(Number.NaN)).toBe('0 B/s');
    });

    it('formats B/s without decimals', () => {
        expect(formatTransferRate(500)).toBe('500 B/s');
    });

    it('formats ~1.5 KiB/s', () => {
        expect(formatTransferRate(1.5 * 1024)).toBe('1.5 KB/s');
    });

    it('formats ~2.5 MiB/s', () => {
        expect(formatTransferRate(2.5 * 1024 * 1024)).toBe('2.5 MB/s');
    });
});

describe('computeByteProgressPercent', () => {
    it('returns 0 for invalid total or non-positive uploaded', () => {
        expect(computeByteProgressPercent(10, 0)).toBe(0);
        expect(computeByteProgressPercent(0, 100)).toBe(0);
        expect(computeByteProgressPercent(-5, 100)).toBe(0);
    });

    it('clamps above total to 100', () => {
        expect(computeByteProgressPercent(150, 100)).toBe(100);
    });

    it('rounds mid-progress', () => {
        expect(computeByteProgressPercent(42, 100)).toBe(42);
        expect(computeByteProgressPercent(1, 3)).toBe(33);
    });
});

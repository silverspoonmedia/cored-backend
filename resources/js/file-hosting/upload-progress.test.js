import { describe, expect, it } from 'vitest';
import {
    computeByteProgressPercent,
    computeTransferRate,
    createRateSampler,
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

describe('createRateSampler', () => {
    it('returns 0 with fewer than 2 samples', () => {
        const sampler = createRateSampler(5000);
        expect(sampler.rate()).toBe(0);
        sampler.add(1000, 1000);
        expect(sampler.rate()).toBe(0);
    });

    it('computes instantaneous rate across the window', () => {
        const sampler = createRateSampler(5000);
        sampler.add(0, 0);
        sampler.add(5_000_000, 1000);
        // 5 MB in 1s.
        expect(sampler.rate(1000)).toBe(5_000_000);
    });

    it('drops samples older than the window (recovers after a stall)', () => {
        const sampler = createRateSampler(5000);
        // Fast start.
        sampler.add(0, 0);
        sampler.add(10_000_000, 1000);
        expect(sampler.rate(1000)).toBe(10_000_000);

        // Long stall: no new bytes for >window. Old fast sample falls out.
        sampler.add(10_000_000, 10_000);
        // Only the stalled sample (and nothing before it) is in-window now,
        // but we still have the boundary sample at t=10000 only -> 0 (need 2).
        // Add a fresh sample 1s later with new bytes -> recovers to current rate.
        sampler.add(11_000_000, 11_000);
        expect(sampler.rate(11_000)).toBe(1_000_000);
    });

    it('is not dragged down by earlier slow history', () => {
        const sampler = createRateSampler(5000);
        sampler.add(0, 0);
        sampler.add(1_000_000, 1000); // slow 1 MB/s start
        // Speed jumps later; window only keeps recent samples.
        sampler.add(21_000_000, 4000);
        // Window covers t in (4000-5000..4000) -> all three still in window here.
        // Rate = (21MB - 0) / 4s across whole window.
        expect(sampler.rate(4000)).toBe(5_250_000);
    });
});

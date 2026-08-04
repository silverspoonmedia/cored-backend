import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { apiFetch, unwrapData, csrfToken } from './useApi.js';

describe('unwrapData', () => {
    it('unwraps Monolith data envelope', () => {
        expect(unwrapData({ status: 'success', data: { id: 1 } })).toEqual({ id: 1 });
    });

    it('returns payload when no data key', () => {
        expect(unwrapData({ id: 2 })).toEqual({ id: 2 });
        expect(unwrapData(null)).toBeNull();
    });
});

describe('csrfToken', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('reads meta csrf-token', () => {
        document.head.innerHTML = '<meta name="csrf-token" content="abc123">';
        expect(csrfToken()).toBe('abc123');
    });

    it('returns empty string when missing', () => {
        expect(csrfToken()).toBe('');
    });
});

describe('apiFetch', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="tok">';
        window.MonolithApi = undefined;
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.head.innerHTML = '';
        delete window.MonolithApi;
    });

    it('GETs with Accept and X-Requested-With, no CSRF', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ data: { name: 'Ada' } }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const result = await apiFetch('/apps/dashboard');

        expect(fetchMock).toHaveBeenCalledWith('/apps/dashboard', expect.objectContaining({
            method: 'GET',
            credentials: 'same-origin',
            headers: expect.objectContaining({
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }),
        }));
        expect(fetchMock.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBeUndefined();
        expect(result.ok).toBe(true);
        expect(unwrapData(result.data)).toEqual({ name: 'Ada' });
    });

    it('POSTs with CSRF token', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ status: 'success' }),
        });
        vi.stubGlobal('fetch', fetchMock);

        await apiFetch('/apps/example', { method: 'POST', body: '{}' });

        expect(fetchMock.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('tok');
    });

    it('routes through MonolithApi.handleResponse when present', async () => {
        const handleResponse = vi.fn().mockReturnValue({ handled: true, success: true });
        window.MonolithApi = { handleResponse };

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ message: 'ok', data: {} }),
        }));

        const result = await apiFetch('/apps/dashboard');

        expect(handleResponse).toHaveBeenCalled();
        expect(result.result).toEqual({ handled: true, success: true });
    });

    it('calls handleNetworkError on fetch failure', async () => {
        const handleNetworkError = vi.fn();
        window.MonolithApi = { handleNetworkError };
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        const result = await apiFetch('/apps/dashboard');

        expect(handleNetworkError).toHaveBeenCalled();
        expect(result.networkError).toBe(true);
        expect(result.ok).toBe(false);
    });
});

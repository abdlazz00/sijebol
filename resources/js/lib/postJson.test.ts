import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { postJson } from './postJson';

const fetchMock = vi.fn();

beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    Object.defineProperty(document, 'cookie', {
        configurable: true,
        get: () => 'a=1; XSRF-TOKEN=abc%3D%3D; b=2',
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
    // @ts-expect-error mengembalikan perilaku cookie bawaan jsdom
    delete document.cookie;
});

const respon = (status: number, body: unknown) =>
    Promise.resolve({ status, json: () => Promise.resolve(body) });

describe('postJson', () => {
    it('mengirim JSON dengan header XSRF dari cookie (didekode)', async () => {
        fetchMock.mockReturnValue(respon(200, { status: 'ok' }));

        const hasil = await postJson<{ status: string }>('/x', { code: 'A' });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/x');
        expect(init.method).toBe('POST');
        expect(init.credentials).toBe('same-origin');
        expect(init.headers).toMatchObject({
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': 'abc==',
        });
        expect(JSON.parse(init.body)).toEqual({ code: 'A' });
        expect(hasil).toEqual({ status: 200, data: { status: 'ok' } });
    });

    it('mengembalikan status 4xx tanpa melempar', async () => {
        fetchMock.mockReturnValue(
            respon(422, { status: 'rejected', message: 'Ditolak.' }),
        );

        const hasil = await postJson('/x', {});

        expect(hasil.status).toBe(422);
        expect(hasil.data).toEqual({ status: 'rejected', message: 'Ditolak.' });
    });

    it('badan bukan JSON menjadi data null', async () => {
        fetchMock.mockReturnValue(
            Promise.resolve({
                status: 502,
                json: () => Promise.reject(new SyntaxError('bukan json')),
            }),
        );

        expect(await postJson('/x', {})).toEqual({ status: 502, data: null });
    });

    it('melempar pada galat jaringan', async () => {
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        await expect(postJson('/x', {})).rejects.toThrow('Failed to fetch');
    });
});

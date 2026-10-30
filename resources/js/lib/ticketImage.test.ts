import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { downloadDataUrl, renderTicket } from './ticketImage';

const info = {
    studentName: 'Budi Santoso',
    school: 'SMPN 1 Sungai Beduk',
    day: 'Selasa, 20 Okt 2026',
    time: '08.00–12.00',
    location: 'Aula SMPN 1',
};

describe('renderTicket', () => {
    const ctx = {
        fillRect: vi.fn(),
        fillText: vi.fn(),
        drawImage: vi.fn(),
        measureText: vi.fn(() => ({ width: 100 })),
        fillStyle: '',
        font: '',
        textAlign: '',
    };

    beforeEach(() => {
        Object.values(ctx).forEach(
            (f) => typeof f === 'function' && 'mockClear' in f && f.mockClear(),
        );
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(
            ctx as unknown as CanvasRenderingContext2D,
        );
        vi.spyOn(HTMLCanvasElement.prototype, 'toDataURL').mockReturnValue(
            'data:image/png;base64,TTT',
        );
        vi.stubGlobal(
            'Image',
            class {
                onload: (() => void) | null = null;
                onerror: (() => void) | null = null;
                set src(_: string) {
                    queueMicrotask(() => this.onload?.());
                }
            },
        );
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('menggambar QR serta nama, sekolah, hari-tanggal, jam, dan lokasi pada kartu putih', async () => {
        const url = await renderTicket('data:image/png;base64,QQQ', info);

        expect(url).toBe('data:image/png;base64,TTT');
        expect(ctx.drawImage).toHaveBeenCalledOnce();
        const teks = ctx.fillText.mock.calls.map((c) => c[0]);
        expect(teks).toEqual(
            expect.arrayContaining([
                'Budi Santoso',
                'SMPN 1 Sungai Beduk',
                'Selasa, 20 Okt 2026',
                '08.00–12.00',
                'Aula SMPN 1',
            ]),
        );
        expect(ctx.fillRect).toHaveBeenCalled();
    });

    it('gagal bila QR tidak dapat dimuat', async () => {
        vi.stubGlobal(
            'Image',
            class {
                onload: (() => void) | null = null;
                onerror: (() => void) | null = null;
                set src(_: string) {
                    queueMicrotask(() => this.onerror?.());
                }
            },
        );

        await expect(renderTicket('x', info)).rejects.toThrow();
    });
});

describe('downloadDataUrl', () => {
    it('memicu unduhan dengan nama berkas yang diberikan', () => {
        const klik = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {});

        downloadDataUrl(
            'data:image/png;base64,TTT',
            'tiket-rekaman-2026-10-20.png',
        );

        expect(klik).toHaveBeenCalledOnce();
        const a = klik.mock.contexts[0] as HTMLAnchorElement;
        expect(a.download).toBe('tiket-rekaman-2026-10-20.png');
        expect(a.href).toBe('data:image/png;base64,TTT');
        klik.mockRestore();
    });
});

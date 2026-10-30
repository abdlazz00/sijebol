import { describe, expect, it } from 'vitest';
import { fitWithin, shrinkImage } from './imageResize';

describe('fitWithin', () => {
    it('memperkecil sisi terpanjang ke batas dengan rasio tetap', () => {
        expect(fitWithin(4000, 3000, 2400)).toEqual({
            width: 2400,
            height: 1800,
        });
        expect(fitWithin(3000, 4000, 2400)).toEqual({
            width: 1800,
            height: 2400,
        });
    });

    it('tidak membesarkan gambar yang sudah kecil', () => {
        expect(fitWithin(800, 600, 2400)).toEqual({ width: 800, height: 600 });
    });
});

describe('shrinkImage', () => {
    const big = 3 * 1024 * 1024;

    it('mengembalikan PDF apa adanya walau besar', async () => {
        const pdf = new File([new Uint8Array(big)], 'kk.pdf', {
            type: 'application/pdf',
        });

        expect(await shrinkImage(pdf)).toBe(pdf);
    });

    it('mengembalikan gambar kecil apa adanya', async () => {
        const kecil = new File([new Uint8Array(1000)], 'kk.jpg', {
            type: 'image/jpeg',
        });

        expect(await shrinkImage(kecil)).toBe(kecil);
    });

    it('mengembalikan gambar besar apa adanya bila gagal didekode', async () => {
        const rusak = new File([new Uint8Array(big)], 'kk.jpg', {
            type: 'image/jpeg',
        });

        expect(await shrinkImage(rusak)).toBe(rusak);
    });
});

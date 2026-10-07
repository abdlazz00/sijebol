import { describe, expect, it } from 'vitest';
import { whatsappUrl } from './whatsapp';

describe('whatsappUrl', () => {
    it('mengubah 08… menjadi 62… dan menyertakan pesan', () => {
        const url = whatsappUrl(
            '081234567890',
            'https://x.test/aktivasi/abc',
            'Budi',
        );

        expect(url!.startsWith('https://wa.me/6281234567890?text=')).toBe(true);
        expect(decodeURIComponent(url!.split('text=')[1])).toContain(
            'https://x.test/aktivasi/abc',
        );
    });

    it('menerima format +62 dengan spasi atau strip', () => {
        expect(whatsappUrl('+62 812-3456-7890', 'l', 'B')).toContain(
            'wa.me/6281234567890',
        );
    });

    it('null untuk nomor kosong atau tidak valid', () => {
        expect(whatsappUrl(null, 'l', 'B')).toBeNull();
        expect(whatsappUrl('', 'l', 'B')).toBeNull();
        expect(whatsappUrl('abc', 'l', 'B')).toBeNull();
    });
});

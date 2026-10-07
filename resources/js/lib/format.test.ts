import { describe, expect, it } from 'vitest';
import { formatNumber, initials, maskNik } from './format';

describe('format', () => {
    it('menyamarkan NIK 16 digit', () => {
        expect(maskNik('6171010101010001')).toBe('6171********0001');
    });

    it('aman untuk NIK kosong atau pendek', () => {
        expect(maskNik(null)).toBe('—');
        expect(maskNik('')).toBe('—');
        expect(maskNik('1234')).toBe('****');
    });

    it('memformat angka Indonesia', () => {
        expect(formatNumber(1234567)).toBe('1.234.567');
    });

    it('mengambil inisial dua huruf', () => {
        expect(initials('Budi Santoso')).toBe('BS');
        expect(initials('Budi')).toBe('B');
        expect(initials('  ')).toBe('?');
    });
});

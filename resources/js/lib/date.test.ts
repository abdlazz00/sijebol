import { describe, expect, it } from 'vitest';
import { formatDate, formatDateWithDay, relativeTime } from './date';

describe('date', () => {
    it('memformat tanggal Indonesia', () => {
        expect(formatDate('2026-10-07')).toBe('7 Okt 2026');
    });

    it('memformat tanggal dengan nama hari', () => {
        expect(formatDateWithDay('2026-10-07')).toBe('Rabu, 7 Okt 2026');
        expect(formatDateWithDay(null)).toBe('—');
    });

    it('nilai kosong atau tidak valid menjadi tanda strip', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate('')).toBe('—');
        expect(formatDate('bukan-tanggal')).toBe('—');
    });

    it('waktu relatif ringkas tanpa "yang"', () => {
        const now = new Date('2026-10-07T07:30:00Z');
        expect(relativeTime('2026-10-07T05:30:00Z', now)).toBe('2 jam lalu');
        expect(relativeTime('2026-10-06T07:30:00Z', now)).toBe('1 hari lalu');
    });

    it('waktu relatif', () => {
        const now = new Date('2026-10-07T07:30:00Z');
        expect(relativeTime('2026-10-07T07:28:00Z', now)).toMatch(/2 menit/);
        expect(relativeTime('2026-10-07T07:29:58Z', now)).toBe('baru saja');
        expect(relativeTime(null, now)).toBe('—');
    });
});

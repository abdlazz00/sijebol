import { describe, expect, it } from 'vitest';
import {
    calculateAge,
    formatDate,
    formatDateTime,
    formatDateWithDay,
    formatTime,
    relativeTime,
} from './date';

describe('date', () => {
    it('memformat tanggal Indonesia', () => {
        expect(formatDate('2026-10-07')).toBe('7 Okt 2026');
    });

    it('memformat tanggal dengan nama hari', () => {
        expect(formatDateWithDay('2026-10-07')).toBe('Rabu, 7 Okt 2026');
        expect(formatDateWithDay(null)).toBe('—');
    });

    it('memformat waktu dengan titik (zona Asia/Jakarta)', () => {
        expect(formatTime('2026-10-07T07:30:00Z')).toBe('14.30');
        expect(formatDateTime('2026-10-07T07:30:00Z')).toBe('7 Okt 2026 14.30');
    });

    it('nilai kosong atau tidak valid menjadi tanda strip', () => {
        expect(formatDate(null)).toBe('—');
        expect(formatDate('')).toBe('—');
        expect(formatDate('bukan-tanggal')).toBe('—');
        expect(formatTime(undefined)).toBe('—');
    });

    it('menghitung umur tepat di hari ulang tahun', () => {
        expect(calculateAge('2009-10-07', '2026-10-06')).toBe(16);
        expect(calculateAge('2009-10-07', '2026-10-07')).toBe(17);
        expect(calculateAge('2009-10-07', '2027-10-06')).toBe(17);
    });

    it('umur null untuk input tidak valid', () => {
        expect(calculateAge('', '2026-10-07')).toBeNull();
        expect(calculateAge('abc', '2026-10-07')).toBeNull();
    });

    it('waktu relatif', () => {
        const now = new Date('2026-10-07T07:30:00Z');
        expect(relativeTime('2026-10-07T07:28:00Z', now)).toMatch(/2 menit/);
        expect(relativeTime('2026-10-07T07:29:58Z', now)).toBe('baru saja');
        expect(relativeTime(null, now)).toBe('—');
    });
});

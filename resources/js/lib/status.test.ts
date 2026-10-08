import { describe, expect, it } from 'vitest';
import { registrationStatus, userStatus } from './status';

describe('userStatus', () => {
    it('memetakan status pengguna ke label dan varian', () => {
        expect(userStatus('aktif')).toEqual({
            label: 'Aktif',
            variant: 'success',
        });
        expect(userStatus('menunggu')).toEqual({
            label: 'Menunggu aktivasi',
            variant: 'warning',
        });
        expect(userStatus('kedaluwarsa')).toEqual({
            label: 'Kedaluwarsa',
            variant: 'danger',
        });
        expect(userStatus('nonaktif')).toEqual({
            label: 'Nonaktif',
            variant: 'neutral',
        });
    });

    it('status tak dikenal jatuh ke netral dengan label apa adanya', () => {
        expect(userStatus('aneh')).toEqual({
            label: 'aneh',
            variant: 'neutral',
        });
    });
});

describe('registrationStatus', () => {
    it('memetakan status entri ke label dan varian', () => {
        expect(registrationStatus('draf')).toEqual({
            label: 'Draf',
            variant: 'neutral',
        });
        expect(registrationStatus('diajukan')).toEqual({
            label: 'Diajukan',
            variant: 'info',
        });
        expect(registrationStatus('perlu_perbaikan')).toEqual({
            label: 'Perlu perbaikan',
            variant: 'warning',
        });
        expect(registrationStatus('terverifikasi')).toEqual({
            label: 'Terverifikasi',
            variant: 'success',
        });
        expect(registrationStatus('ditolak')).toEqual({
            label: 'Ditolak',
            variant: 'danger',
        });
    });

    it('status tak dikenal jatuh ke netral', () => {
        expect(registrationStatus('aneh')).toEqual({
            label: 'aneh',
            variant: 'neutral',
        });
    });
});

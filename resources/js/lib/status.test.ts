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
    it.each([
        ['menunggu_verifikasi', 'Menunggu verifikasi', 'info'],
        ['perlu_perbaikan', 'Perlu perbaikan', 'warning'],
        ['terdaftar', 'Terdaftar', 'success'],
        ['sudah_rekaman', 'Sudah rekaman', 'success'],
        ['tidak_hadir', 'Tidak hadir', 'danger'],
        ['belum_rekaman', 'Belum rekaman', 'warning'],
        ['ditolak', 'Ditolak', 'danger'],
        ['dibatalkan', 'Dibatalkan', 'neutral'],
        ['kedaluwarsa', 'Kedaluwarsa', 'neutral'],
    ])('memetakan %s ke label dan varian', (status, label, variant) => {
        expect(registrationStatus(status)).toEqual({ label, variant });
    });

    it('status lama yang sudah dihapus jatuh ke netral dengan label apa adanya', () => {
        expect(registrationStatus('diajukan')).toEqual({
            label: 'diajukan',
            variant: 'neutral',
        });
    });

    it('status tak dikenal jatuh ke netral', () => {
        expect(registrationStatus('aneh')).toEqual({
            label: 'aneh',
            variant: 'neutral',
        });
    });
});

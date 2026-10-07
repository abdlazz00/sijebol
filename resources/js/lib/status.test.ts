import { describe, expect, it } from 'vitest';
import { userStatus } from './status';

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

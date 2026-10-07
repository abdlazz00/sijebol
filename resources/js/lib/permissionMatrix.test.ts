import { describe, expect, it } from 'vitest';
import {
    moduleState,
    otherActions,
    setPermission,
    toggleModule,
    type CatalogModule,
} from './permissionMatrix';

const user: CatalogModule = {
    module: 'user',
    actions: [
        { value: 'user.lihat', action: 'lihat', editable: true },
        { value: 'user.buat', action: 'buat', editable: true },
        {
            value: 'user.kirim-ulang-aktivasi',
            action: 'kirim-ulang-aktivasi',
            editable: false,
        },
    ],
};

describe('permissionMatrix', () => {
    it('aksi di luar kolom standar masuk kolom Lainnya', () => {
        expect(otherActions(user).map((a) => a.action)).toEqual([
            'kirim-ulang-aktivasi',
        ]);
    });

    it('toggleModule hanya mengubah izin yang dapat diedit dan mempertahankan sisanya', () => {
        const on = toggleModule(
            ['user.kirim-ulang-aktivasi', 'siswa.lihat'],
            user,
            true,
        );
        expect([...on].sort()).toEqual([
            'siswa.lihat',
            'user.buat',
            'user.kirim-ulang-aktivasi',
            'user.lihat',
        ]);

        const off = toggleModule(on, user, false);
        expect([...off].sort()).toEqual([
            'siswa.lihat',
            'user.kirim-ulang-aktivasi',
        ]);
    });

    it('moduleState dihitung dari izin yang dapat diedit saja', () => {
        expect(moduleState([], user)).toBe('none');
        expect(moduleState(['user.lihat'], user)).toBe('some');
        expect(moduleState(['user.lihat', 'user.buat'], user)).toBe('all');
    });

    it('setPermission menambah tanpa duplikat dan mencabut', () => {
        expect(setPermission(['a'], 'b', true)).toEqual(['a', 'b']);
        expect(setPermission(['a', 'b'], 'b', true)).toEqual(['a', 'b']);
        expect(setPermission(['a', 'b'], 'a', false)).toEqual(['b']);
    });
});

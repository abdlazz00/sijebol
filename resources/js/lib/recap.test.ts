import { describe, expect, it } from 'vitest';
import { recapTable } from './recap';

const recap = {
    columns: [
        { key: 'diajukan', label: 'Diajukan' },
        { key: 'ditolak', label: 'Ditolak' },
    ],
    rows: [
        {
            school: 'SMPN A',
            counts: { diajukan: 2, ditolak: 0 },
            total: 2,
        },
    ],
    totals: { counts: { diajukan: 2, ditolak: 0 }, total: 2 },
};

describe('recapTable', () => {
    it('menyusun kolom Sekolah, status, dan Total', () => {
        const { columns } = recapTable(recap);

        expect(columns.map((c) => c.key)).toEqual([
            'school',
            'diajukan',
            'ditolak',
            'total',
        ]);
        expect(columns.map((c) => c.label)).toEqual([
            'Sekolah',
            'Diajukan',
            'Ditolak',
            'Total',
        ]);
    });

    it('meratakan baris dan menambah baris total', () => {
        const { rows } = recapTable(recap);

        expect(rows).toEqual([
            {
                school: 'SMPN A',
                diajukan: 2,
                ditolak: 0,
                total: 2,
                isTotal: false,
            },
            {
                school: 'Total',
                diajukan: 2,
                ditolak: 0,
                total: 2,
                isTotal: true,
            },
        ]);
    });

    it('tanpa sekolah tidak menambah baris total', () => {
        const empty = {
            ...recap,
            rows: [],
            totals: { counts: { diajukan: 0, ditolak: 0 }, total: 0 },
        };

        expect(recapTable(empty).rows).toEqual([]);
    });
});

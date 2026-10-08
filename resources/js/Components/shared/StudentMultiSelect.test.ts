import type { CandidateRow } from '@/types/registration';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StudentMultiSelect from './StudentMultiSelect.vue';

const base = { age: 16, missing_docs: false, selected: false, reasons: [] };
const candidates: CandidateRow[] = [
    { ...base, id: 1, name: 'Ani', nisn: '0000000001', eligible: true },
    { ...base, id: 2, name: 'Budi', nisn: '0000000002', eligible: true },
    {
        ...base,
        id: 3,
        name: 'Citra',
        nisn: '0000000003',
        eligible: false,
        missing_docs: true,
        reasons: ['Kartu Keluarga belum diunggah.'],
    },
];

function mountSelect(modelValue: number[] = []) {
    return mount(StudentMultiSelect, {
        props: { candidates, modelValue, 'onUpdate:modelValue': () => {} },
        global: {
            stubs: { Link: { template: '<a><slot /></a>' } },
            mocks: { route: (n: string, p: unknown) => `/${n}/${p}` },
        },
    });
}

describe('StudentMultiSelect', () => {
    it('menampilkan alasan sebagai teks untuk siswa belum eligible', () => {
        const text = mountSelect().text();
        expect(text).toContain('Kartu Keluarga belum diunggah.');
        expect(text).toContain('Lengkapi dokumen');
    });

    it('"Pilih semua yang eligible" hanya memilih yang eligible', async () => {
        const wrapper = mountSelect();
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[1, 2]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountSelect([1, 2]).find('[role="status"]').text()).toContain(
            '2 dipilih',
        );
    });

    it('pencarian menyaring menurut nama atau NISN', async () => {
        const wrapper = mountSelect();
        await wrapper
            .find('input[type="text"], input:not([type])')
            .setValue('budi');
        expect(wrapper.text()).toContain('Budi');
        expect(wrapper.text()).not.toContain('Ani');
    });
});

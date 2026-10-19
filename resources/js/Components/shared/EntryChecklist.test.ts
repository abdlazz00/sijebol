import type { ChecklistItem } from '@/types/schedule';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import EntryChecklist from './EntryChecklist.vue';

const items: ChecklistItem[] = [
    { id: 1, name: 'Ani', nisn: '0000000001' },
    { id: 2, name: 'Budi', nisn: '0000000002' },
    { id: 3, name: 'Citra', nisn: '0000000003' },
];

function mountList(
    modelValue: number[] = [],
    extra: Record<string, unknown> = {},
) {
    return mount(EntryChecklist, {
        props: {
            items,
            modelValue,
            'onUpdate:modelValue': () => {},
            ...extra,
        },
    });
}

describe('EntryChecklist', () => {
    it('menampilkan nama dan NISN', () => {
        const text = mountList().text();
        expect(text).toContain('Ani');
        expect(text).toContain('0000000002');
    });

    it('"Pilih semua" memilih semua item', async () => {
        const wrapper = mountList();
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[1, 2, 3]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountList([1, 2]).find('[role="status"]').text()).toContain(
            '2 dipilih',
        );
    });

    it('pencarian menyaring menurut nama atau NISN', async () => {
        const wrapper = mountList();
        await wrapper.find('input').setValue('budi');
        expect(wrapper.text()).toContain('Budi');
        expect(wrapper.text()).not.toContain('Ani');
    });

    it('menampilkan teks kosong bila tidak ada item', () => {
        const wrapper = mountList([], {
            items: [],
            emptyText: 'Tidak ada siswa.',
        });
        expect(wrapper.text()).toContain('Tidak ada siswa.');
    });
});

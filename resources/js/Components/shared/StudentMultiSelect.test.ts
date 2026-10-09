import type { Paginator } from '@/types/access';
import type { CandidateRow } from '@/types/registration';
import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StudentMultiSelect from './StudentMultiSelect.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    Link: { template: '<a><slot /></a>' },
}));

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

function page(
    rows: CandidateRow[],
    over: Partial<Paginator<CandidateRow>> = {},
): Paginator<CandidateRow> {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        from: 1,
        to: rows.length,
        total: rows.length,
        per_page: 20,
        links: [],
        ...over,
    };
}

function mountSelect(
    modelValue: number[] = [],
    props: Record<string, unknown> = {},
) {
    return mount(StudentMultiSelect, {
        props: {
            candidates: page(candidates),
            modelValue,
            reloadUrl: '/pendaftaran/buat',
            'onUpdate:modelValue': () => {},
            ...props,
        },
        global: { mocks: { route: (n: string, p: unknown) => `/${n}/${p}` } },
    });
}

describe('StudentMultiSelect', () => {
    beforeEach(() => vi.clearAllMocks());
    afterEach(() => vi.useRealTimers());

    it('menampilkan alasan sebagai teks untuk siswa belum eligible', () => {
        const text = mountSelect().text();
        expect(text).toContain('Kartu Keluarga belum diunggah.');
        expect(text).toContain('Lengkapi dokumen');
    });

    it('"Pilih semua yang eligible di halaman ini" menambah yang eligible ke pilihan yang ada', async () => {
        const wrapper = mountSelect([9]);
        const button = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Pilih semua'))!;
        await button.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[9, 1, 2]]);
    });

    it('penghitung mengikuti jumlah terpilih', () => {
        expect(mountSelect([1, 2]).find('[role="status"]').text()).toContain(
            '2 dipilih',
        );
    });

    it('siswa terpilih dari halaman lain tetap tampil dan dapat dihapus', async () => {
        const zed: CandidateRow = {
            ...base,
            id: 9,
            name: 'Zed',
            nisn: '0000000009',
            eligible: true,
        };
        const wrapper = mountSelect([9], { selectedRows: [zed] });

        expect(wrapper.text()).toContain('Terpilih (1)');
        expect(wrapper.text()).toContain('Zed');

        await wrapper
            .find('button[aria-label="Hapus Zed dari pilihan"]')
            .trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([[]]);
    });

    it('pencarian memuat ulang kandidat dari server (debounce), hanya prop candidates', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect([], { reloadQuery: { school_id: 7 } });

        await wrapper.find('input').setValue('budi');
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledWith(
            '/pendaftaran/buat',
            { school_id: 7, q: 'budi' },
            expect.objectContaining({
                only: ['candidates'],
                preserveState: true,
            }),
        );
    });

    it('tombol halaman memuat ulang halaman berikutnya dan menonaktifkan di ujung', async () => {
        const wrapper = mountSelect([], {
            candidates: page(candidates, {
                current_page: 1,
                last_page: 2,
                total: 25,
            }),
        });
        const next = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Berikutnya')!;
        const prev = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Sebelumnya')!;

        expect(prev.attributes('disabled')).toBeDefined();
        await next.trigger('click');
        expect(router.get).toHaveBeenCalledWith(
            '/pendaftaran/buat',
            { page: 2 },
            expect.anything(),
        );
    });

    it('pesan kosong membedakan belum ada siswa dari hasil pencarian kosong', async () => {
        const wrapper = mountSelect([], { candidates: page([]) });
        expect(wrapper.text()).toContain('Belum ada siswa di sekolah ini.');

        await wrapper.find('input').setValue('xyz');
        expect(wrapper.text()).toContain(
            'Tidak ada siswa yang sesuai pencarian.',
        );
    });
});

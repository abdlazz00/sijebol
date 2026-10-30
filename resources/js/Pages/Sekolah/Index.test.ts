import Index from '@/Pages/Sekolah/Index.vue';
import type { Paginator, SchoolRow } from '@/types/access';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { deleteFn, permissions } = vi.hoisted(() => ({
    deleteFn: vi.fn(),
    permissions: [] as string[],
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: deleteFn },
    usePage: () => ({ props: { auth: { permissions } } }),
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

vi.mock('@/Components/shared/ConfirmDialog.vue', () => ({
    default: {
        props: ['open', 'title', 'description', 'confirmDisabled'],
        emits: ['update:open', 'confirm'],
        template: `
            <div v-if="open" data-testid="confirm">
                <p>{{ title }}</p>
                <p>{{ description }}</p>
                <slot />
                <button
                    data-testid="ok"
                    :disabled="confirmDisabled"
                    @click="$emit('confirm')"
                >
                    Hapus
                </button>
            </div>
        `,
    },
}));

const dummySchools: Paginator<SchoolRow> = {
    data: [
        {
            id: 1,
            name: 'SMAN 1 Sungai Beduk',
            npsn: '10000001',
            level: 'SMA',
            address: 'Jl. Utama',
            users_count: 2,
            students_count: 0,
            schedules_count: 0,
        },
        {
            id: 2,
            name: 'SMKN 2 Sungai Beduk',
            npsn: '10000002',
            level: 'SMK',
            address: null,
            users_count: 0,
            students_count: 0,
            schedules_count: 0,
        },
    ],
    links: [],
    current_page: 1,
    from: 1,
    last_page: 1,
    per_page: 10,
    to: 2,
    total: 2,
};

beforeEach(() => {
    const route = (name: string, id?: number) => `/${name}/${id}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    deleteFn.mockReset();
    permissions.length = 0;
});

describe('Sekolah/Index', () => {
    it('tanpa izin hapus, tombol hapus tidak muncul', () => {
        permissions.push('sekolah.lihat', 'sekolah.ubah');
        const wrapper = mount(Index, {
            props: { schools: dummySchools, filters: {} },
        });

        expect(wrapper.text()).toContain('Ubah');
        expect(wrapper.text()).not.toContain('Hapus');
    });

    it('dengan izin sekolah.hapus, tombol hapus muncul dan membuka konfirmasi', async () => {
        permissions.push('sekolah.lihat', 'sekolah.hapus');
        const wrapper = mount(Index, {
            props: { schools: dummySchools, filters: {} },
        });

        const deleteButtons = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('Hapus'));
        expect(deleteButtons.length).toBe(2);

        // Klik hapus pada sekolah kosong (id: 2)
        await deleteButtons[1].trigger('click');

        const dialog = wrapper.find('[data-testid="confirm"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.text()).toContain('Hapus sekolah SMKN 2 Sungai Beduk?');
        expect(dialog.text()).toContain(
            'Tidak ada data siswa, operator, atau sesi yang terhubung',
        );

        // Konfirmasi hapus memanggil router.delete
        await dialog.find('[data-testid="ok"]').trigger('click');
        expect(deleteFn).toHaveBeenCalledWith(
            '/schools.destroy/2',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('sekolah dengan data terkait menonaktifkan tombol konfirmasi hapus', async () => {
        permissions.push('sekolah.lihat', 'sekolah.hapus');
        const wrapper = mount(Index, {
            props: { schools: dummySchools, filters: {} },
        });

        const deleteButtons = wrapper
            .findAll('button')
            .filter((b) => b.text().includes('Hapus'));

        // Klik hapus pada sekolah dengan operator (id: 1, users_count: 2)
        await deleteButtons[0].trigger('click');

        const dialog = wrapper.find('[data-testid="confirm"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.text()).toContain(
            'Sekolah masih memiliki 2 operator dan tidak dapat dihapus.',
        );

        const okButton = dialog.find('[data-testid="ok"]');
        expect(okButton.attributes('disabled')).toBeDefined();

        await okButton.trigger('click');
        expect(deleteFn).not.toHaveBeenCalled();
    });
});

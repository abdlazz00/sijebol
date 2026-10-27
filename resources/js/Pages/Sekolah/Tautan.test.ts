import Tautan from '@/Pages/Sekolah/Tautan.vue';
import type { SchoolLink } from '@/types/access';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { post, permissions } = vi.hoisted(() => ({
    post: vi.fn(),
    permissions: [] as string[],
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    usePage: () => ({ props: { auth: { permissions } } }),
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/Components/shared/ConfirmDialog.vue', () => ({
    default: {
        props: ['open', 'title', 'description'],
        emits: ['update:open', 'confirm'],
        template:
            '<div v-if="open" data-testid="confirm"><p>{{ title }}</p><p>{{ description }}</p><button data-testid="ok" @click="$emit(\'confirm\')">ok</button></div>',
    },
}));

const sekolah: SchoolLink = {
    id: 4,
    name: 'SMAN 1 Sungai Beduk',
    npsn: '10000001',
    url: 'http://localhost/daftar/abc123',
    students_count: 12,
};

beforeEach(() => {
    const route = (name: string, id?: number) => `/${name}/${id}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    post.mockReset();
    permissions.length = 0;
});

describe('Sekolah/Tautan', () => {
    it('menampilkan link, jumlah siswa yang sudah mengisi data, dan tombol WhatsApp', () => {
        const w = mount(Tautan, { props: { schools: [sekolah] } });

        expect(w.text()).toContain('SMAN 1 Sungai Beduk');
        expect(w.text()).toContain('12 siswa sudah mengisi data');
        expect((w.find('textarea').element as HTMLTextAreaElement).value).toBe(
            sekolah.url,
        );

        const wa = w.find('a[href^="https://wa.me/?text="]');
        expect(wa.exists()).toBe(true);
        expect(decodeURIComponent(wa.attributes('href')!)).toContain(
            sekolah.url,
        );
    });

    it('tombol reset hanya untuk pemegang izin reset, dialog menyebut nama dan dampaknya', async () => {
        const tanpa = mount(Tautan, { props: { schools: [sekolah] } });
        expect(tanpa.text()).not.toContain('Reset link');

        permissions.push('sekolah.reset-tautan');
        const w = mount(Tautan, { props: { schools: [sekolah] } });
        const tombol = w
            .findAll('button')
            .find((b) => b.text().includes('Reset link'))!;
        await tombol.trigger('click');

        const dialog = w.find('[data-testid="confirm"]');
        expect(dialog.text()).toContain('Reset link SMAN 1 Sungai Beduk?');
        expect(dialog.text()).toContain('tidak berlaku lagi');

        await w.find('[data-testid="ok"]').trigger('click');

        expect(post).toHaveBeenCalledWith(
            '/registration-links.reset/4',
            {},
            { preserveScroll: true },
        );
    });

    it('tanpa sekolah menampilkan empty state', () => {
        const w = mount(Tautan, { props: { schools: [] } });

        expect(w.text()).toContain('Belum ada sekolah');
    });
});

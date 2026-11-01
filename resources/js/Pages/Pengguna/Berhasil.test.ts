import Berhasil from '@/Pages/Pengguna/Berhasil.vue';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
}));
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));

const user = {
    id: 7,
    name: 'Budi',
    email: 'budi@x.test',
    phone: '081234567890',
    role: 'operator-sekolah',
    school: 'SMAN 1 Sungai Beduk',
};
const link = 'http://localhost/activation/abc';

beforeEach(() => {
    const route = (name: string) => `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

describe('Pengguna/Berhasil', () => {
    it.each(['dibuat', 'ulang'] as const)(
        'menyebut email aktivasi terkirim, mode %s',
        (mode) => {
            const w = mount(Berhasil, { props: { user, link, mode } });

            expect(w.text()).toContain(
                'Email aktivasi sedang dikirim ke budi@x.test',
            );
        },
    );

    it('tetap menyediakan link dan tombol WhatsApp sebagai cadangan', () => {
        const w = mount(Berhasil, { props: { user, link, mode: 'dibuat' } });

        expect(w.text()).toContain('cadangan bila email belum sampai');
        expect(w.text()).toContain('Kirim via WhatsApp');
        expect(w.find('a[href^="https://wa.me/"]').exists()).toBe(true);
    });
});

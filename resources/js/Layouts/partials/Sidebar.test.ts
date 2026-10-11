import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Sidebar from './Sidebar.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
    return {
        ...actual,
        Link: { template: '<a><slot /></a>' },
        usePage: () => ({
            props: {
                auth: {
                    user: {
                        name: 'Admin Test',
                        email: 'admin@example.com',
                        roles: ['admin-kecamatan'],
                    },
                },
                menu: [
                    {
                        group: 'Utama',
                        items: [
                            {
                                label: 'Beranda',
                                href: '/dashboard',
                                icon: 'home',
                            },
                        ],
                    },
                    {
                        group: 'Pelayanan',
                        items: [
                            {
                                label: 'Siswa',
                                href: '/siswa',
                                icon: 'graduation-cap',
                            },
                        ],
                    },
                ],
                permissions: [],
                flash: {},
            },
            url: '/',
        }),
    };
});

describe('Sidebar Navigation Component', () => {
    it('memiliki kontainer navigasi dengan kelas seamless scrollbar dan ritme vertikal space-y-4', () => {
        const wrapper = mount(Sidebar, {
            props: { collapsed: false },
        });

        // Kontainer menu vertikal
        const navContainer = wrapper.find('.overflow-y-auto');
        expect(navContainer.exists()).toBe(true);

        // Memastikan kelas seamless scrollbar (menyembunyikan native scrollbar)
        expect(navContainer.classes()).toContain('[scrollbar-width:none]');
        expect(navContainer.classes()).toContain('[-ms-overflow-style:none]');
        expect(navContainer.classes()).toContain(
            '[&::-webkit-scrollbar]:hidden',
        );

        // Memastikan ritme spasi vertikal lebih ringkas
        expect(navContainer.classes()).toContain('space-y-4');
        expect(navContainer.classes()).toContain('pt-4');
    });

    it('merender grup menu Utama dan Pelayanan', () => {
        const wrapper = mount(Sidebar, {
            props: { collapsed: false },
        });

        expect(wrapper.text()).toContain('Utama');
        expect(wrapper.text()).toContain('Pelayanan');
        expect(wrapper.text()).toContain('Beranda');
        expect(wrapper.text()).toContain('Siswa');
    });
});

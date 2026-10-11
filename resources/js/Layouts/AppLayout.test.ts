import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppLayout from './AppLayout.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
    return {
        ...actual,
        Head: { template: '<title><slot /></title>' },
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
                permissions: [],
                flash: {},
            },
            url: '/',
        }),
        usePoll: vi.fn(),
    };
});

describe('AppLayout Shell Layout', () => {
    it('memiliki kontainer main dengan atribut aksesibilitas dan kelas fluid 1600px', () => {
        const wrapper = mount(AppLayout, {
            props: { title: 'Test Page' },
            slots: { default: '<div id="test-content">Konten Uji</div>' },
        });

        const main = wrapper.find('main#konten');
        expect(main.exists()).toBe(true);
        expect(main.attributes('tabindex')).toBe('-1');

        // Memastikan tidak ada mx-auto yang membuat layout melayang di tengah
        expect(main.classes()).not.toContain('mx-auto');
        // Memastikan batas lebar menggunakan 1600px dan fluid rata-kiri
        expect(main.classes()).toContain('max-w-[1600px]');
        expect(main.classes()).toContain('w-full');
    });

    it('memiliki header Topbar yang berposisi sticky di bagian atas dengan z-index aman', () => {
        const wrapper = mount(AppLayout, {
            props: { title: 'Test Sticky Header' },
        });

        const header = wrapper.find('header');
        expect(header.exists()).toBe(true);
        expect(header.classes()).toContain('sticky');
        expect(header.classes()).toContain('top-0');
        expect(header.classes()).toContain('z-20');
    });
});

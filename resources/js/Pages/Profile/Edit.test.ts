import Edit from '@/Pages/Profile/Edit.vue';
import type { ProfileIdentity } from '@/types/profile';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (data: Record<string, string>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                patch: vi.fn(),
                put: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            }),
    };
});
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
vi.mock('@/Components/profile/LogoutOtherDevicesDialog.vue', () => ({
    default: {
        props: ['open'],
        template:
            '<div data-testid="devices-dialog" :data-open="String(open)" />',
    },
}));

beforeEach(() => {
    const route = (name: string) => `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

const identity: ProfileIdentity = {
    school: null,
    student: null,
    access: null,
    created_at: '2026-10-07T03:00:00+07:00',
    last_login_at: null,
    can_open_student_data: false,
};

function mountPage() {
    return mount(Edit, {
        props: {
            profile: {
                name: 'Sari Wulandari',
                email: 'sari@example.com',
                phone: null,
                role: 'admin-kecamatan',
            },
            identity,
        },
    });
}

describe('Profile/Edit', () => {
    it('memuat kartu identitas dan kartu Keamanan', () => {
        const wrapper = mountPage();

        expect(wrapper.find('section h2').text()).toBe('Sari Wulandari');
        expect(wrapper.text()).toContain('Keamanan');
        expect(wrapper.text()).toContain('Perbarui password');
        expect(wrapper.text()).toContain('Keluar dari perangkat lain');
    });

    it('tombol Keluar dari perangkat lain membuka dialog', async () => {
        const wrapper = mountPage();
        const dialog = () => wrapper.get('[data-testid="devices-dialog"]');

        expect(dialog().attributes('data-open')).toBe('false');

        const trigger = wrapper
            .findAll('button')
            .find((b) => b.text() === 'Keluar dari perangkat lain');
        await trigger!.trigger('click');

        expect(dialog().attributes('data-open')).toBe('true');
    });
});

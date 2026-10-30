import LogoutOtherDevicesDialog from '@/Components/profile/LogoutOtherDevicesDialog.vue';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { state } = vi.hoisted(() => ({
    state: { form: null as Record<string, any> | null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (data: Record<string, string>) => {
            state.form = reactive({
                ...data,
                errors: {} as Record<string, string>,
                processing: false,
                delete: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            });

            return state.form;
        },
    };
});
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
vi.mock('@/Components/ui/dialog', async () => {
    const part = { template: '<div><slot /></div>' };

    return {
        Dialog: {
            props: ['open'],
            template: '<div v-if="open"><slot /></div>',
        },
        DialogContent: {
            name: 'DialogContentStub',
            emits: ['closeAutoFocus'],
            template: '<div><slot /></div>',
        },
        DialogHeader: part,
        DialogTitle: part,
        DialogDescription: part,
        DialogFooter: part,
    };
});

beforeEach(() => {
    const route = (name: string) => `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

function mountDialog() {
    return mount(LogoutOtherDevicesDialog, { props: { open: true } });
}

describe('LogoutOtherDevicesDialog', () => {
    it('menjelaskan dampak aksi', () => {
        const text = mountDialog().text();

        expect(text).toContain('Keluar dari perangkat lain?');
        expect(text).toContain(
            'Semua perangkat lain akan keluar dari akun ini. Perangkat ini tetap masuk.',
        );
    });

    it('password kosong ditolak di klien tanpa memanggil server', async () => {
        const wrapper = mountDialog();

        await wrapper.find('form').trigger('submit');

        expect(state.form!.delete).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Password saat ini wajib diisi.');
    });

    it('mengirim DELETE ke rute sesi dan menutup dialog saat sukses', async () => {
        const wrapper = mountDialog();
        state.form!.delete.mockImplementation(
            (_url: string, opts: { onSuccess: () => void }) => opts.onSuccess(),
        );

        await wrapper.find('input').setValue('rahasia123');
        await wrapper.find('form').trigger('submit');

        expect(state.form!.delete).toHaveBeenCalledWith(
            '/profile.sessions.destroy',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });

    it('menampilkan galat password dari server di bawah field', async () => {
        const wrapper = mountDialog();

        state.form!.errors = { current_password: 'Password tidak cocok.' };
        await nextTick();

        expect(wrapper.text()).toContain('Password tidak cocok.');
    });

    it('mengembalikan fokus ke elemen pemicu saat dialog ditutup', async () => {
        // Dialog dibuka lewat kode (bukan DialogTrigger), jadi reka-ui tak tahu ke mana fokus kembali.
        const opener = document.createElement('button');
        document.body.append(opener);
        opener.focus();
        const wrapper = mount(LogoutOtherDevicesDialog, {
            props: { open: false },
        });
        await wrapper.setProps({ open: true });
        const event = { preventDefault: vi.fn() };

        wrapper
            .findComponent({ name: 'DialogContentStub' })
            .vm.$emit('closeAutoFocus', event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(document.activeElement).toBe(opener);
        opener.remove();
    });

    it('menutup dialog mengosongkan password dan galat', async () => {
        const wrapper = mountDialog();

        await wrapper.setProps({ open: false });

        expect(state.form!.reset).toHaveBeenCalled();
        expect(state.form!.clearErrors).toHaveBeenCalled();
    });
});

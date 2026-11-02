import Daftar from '@/Pages/Daftar/Index.vue';
import { config, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { post, resetWidget } = vi.hoisted(() => ({
    post: vi.fn(),
    resetWidget: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => ({ url: '/daftar/abc123' }),
        useForm: (data: Record<string, string>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post,
                clearErrors: vi.fn(),
            }),
    };
});
vi.mock('@/Layouts/AuthLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}));
vi.mock('@/lib/focusFirstInvalid', () => ({ focusFirstInvalid: vi.fn() }));
// Widget asli memuat skrip Cloudflare: diganti tombol yang memberi token saat diklik.
vi.mock('@/Components/shared/TurnstileWidget.vue', () => ({
    default: {
        emits: ['update:token', 'load-error'],
        setup: (_: unknown, { expose }: { expose: (e: object) => void }) =>
            expose({ reset: resetWidget }),
        template:
            '<div><button type="button" data-test="widget" @click="$emit(\'update:token\', \'token-uji\')" /><button type="button" data-test="widget-gagal" @click="$emit(\'load-error\')" /></div>',
    },
}));

const props = { valid: true, school: 'SMAN 1', sent: false, ttl_days: 3 };

beforeEach(() => {
    vi.useFakeTimers();
    const route = (name: string, p?: string) => `/${name}/${p ?? ''}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
    sessionStorage.clear();
    post.mockReset();
    resetWidget.mockReset();
});
afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('Daftar', () => {
    it('token tidak berlaku: pesan tampil dan form tidak dirender', () => {
        const w = mount(Daftar, { props: { ...props, valid: false } });

        expect(w.text()).toContain('Link pendaftaran tidak berlaku');
        expect(w.find('form').exists()).toBe(false);
    });

    it('terkirim: menampilkan alamat, kirim ulang menunggu 60 detik, lalu aktif', async () => {
        sessionStorage.setItem(
            'sijebol.daftar',
            JSON.stringify({ name: 'Budi', email: 'budi@example.com' }),
        );
        const w = mount(Daftar, { props: { ...props, sent: true } });
        await flushPromises();

        expect(w.text()).toContain('Periksa email Anda');
        expect(w.text()).toContain('budi@example.com');
        expect(w.text()).toContain('Link berlaku 3 hari');
        expect(w.find('form').exists()).toBe(false);

        const resend = () =>
            w
                .findAll('button')
                .find((b) => b.text().startsWith('Kirim ulang'))!;
        expect(resend().attributes('disabled')).toBeDefined();
        expect(resend().text()).toContain('60 dtk');

        vi.advanceTimersByTime(60_000);
        await flushPromises();

        expect(resend().attributes('disabled')).toBeUndefined();
    });

    it('"Salah email? Ubah" mengembalikan form dengan email terisi', async () => {
        sessionStorage.setItem(
            'sijebol.daftar',
            JSON.stringify({ name: 'Budi', email: 'budi@gmial.com' }),
        );
        const w = mount(Daftar, { props: { ...props, sent: true } });

        await w
            .findAll('button')
            .find((b) => b.text().includes('Salah email'))!
            .trigger('click');

        expect(w.find('form').exists()).toBe(true);
        expect(
            (w.find('input[type="email"]').element as HTMLInputElement).value,
        ).toBe('budi@gmial.com');
    });

    it('email tidak valid menampilkan pesan dan tidak mengirim', async () => {
        const w = mount(Daftar, { props });

        await w.find('input[autocomplete="name"]').setValue('Budi');
        await w.find('input[type="email"]').setValue('bukan-email');
        await w.find('form').trigger('submit');

        expect(w.text()).toContain('Format email tidak valid.');
        expect(post).not.toHaveBeenCalled();
    });

    it('data valid dikirim ke rute daftar.store dengan token dari URL', async () => {
        const w = mount(Daftar, { props });

        await w.find('input[autocomplete="name"]').setValue('Budi');
        await w.find('input[type="email"]').setValue('budi@example.com');
        await w.find('form').trigger('submit');

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/register.store/abc123');
    });

    describe('verifikasi keamanan (Turnstile)', () => {
        const withKey = { ...props, turnstile_site_key: 'kunci-situs' };
        const submitButton = (w: ReturnType<typeof mount>) =>
            w.findAll('button').find((b) => b.text() === 'Buat akun')!;

        it('tanpa site key: tidak ada widget dan tombol langsung aktif', () => {
            const w = mount(Daftar, { props });

            expect(w.find('[data-test="widget"]').exists()).toBe(false);
            expect(submitButton(w).attributes('disabled')).toBeUndefined();
            expect(w.text()).not.toContain('Memeriksa keamanan');
        });

        it('tombol kirim nonaktif sampai widget memberi token', async () => {
            const w = mount(Daftar, { props: withKey });

            expect(submitButton(w).attributes('disabled')).toBeDefined();
            expect(w.text()).toContain('Memeriksa keamanan');

            await w.find('[data-test="widget"]').trigger('click');

            expect(submitButton(w).attributes('disabled')).toBeUndefined();
            expect(w.text()).not.toContain('Memeriksa keamanan');
        });

        it('token ikut terkirim dan widget di-reset setelah pengiriman selesai', async () => {
            const w = mount(Daftar, { props: withKey });
            await w.find('input[autocomplete="name"]').setValue('Budi');
            await w.find('input[type="email"]').setValue('budi@example.com');
            await w.find('[data-test="widget"]').trigger('click');

            await w.find('form').trigger('submit');

            expect(post).toHaveBeenCalledTimes(1);
            expect(resetWidget).not.toHaveBeenCalled();
            post.mock.calls[0][1].onFinish();
            expect(resetWidget).toHaveBeenCalledTimes(1);
        });

        it('widget gagal dimuat: pesan penjelasan tampil dan hilang begitu token tiba', async () => {
            const w = mount(Daftar, { props: withKey });

            await w.find('[data-test="widget-gagal"]').trigger('click');
            expect(w.text()).toContain('Verifikasi keamanan tidak termuat');
            expect(submitButton(w).attributes('disabled')).toBeDefined();

            await w.find('[data-test="widget"]').trigger('click');
            expect(w.text()).not.toContain('Verifikasi keamanan tidak termuat');
        });

        it('kirim ulang di keadaan "Periksa email" juga menunggu token', async () => {
            sessionStorage.setItem(
                'sijebol.daftar',
                JSON.stringify({ name: 'Budi', email: 'budi@example.com' }),
            );
            const w = mount(Daftar, { props: { ...withKey, sent: true } });
            vi.advanceTimersByTime(60_000);
            await flushPromises();

            const resend = () =>
                w
                    .findAll('button')
                    .find((b) => b.text().startsWith('Kirim ulang'))!;
            expect(resend().attributes('disabled')).toBeDefined();

            await w.find('[data-test="widget"]').trigger('click');
            expect(resend().attributes('disabled')).toBeUndefined();
        });
    });
});

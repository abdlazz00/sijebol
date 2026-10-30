import ProfileIdentityCard from '@/Components/profile/ProfileIdentityCard.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { humanize, initials } from '@/lib/format';
import type { ProfileIdentity } from '@/types/profile';
import { config, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

beforeEach(() => {
    const route = (name: string) => `/${name}`;
    vi.stubGlobal('route', route);
    config.global.mocks = { route };
});

const base: ProfileIdentity = {
    school: null,
    student: null,
    access: null,
    created_at: '2026-10-07T03:00:00+07:00',
    last_login_at: '2026-10-28T07:42:00+07:00',
    can_open_student_data: false,
};

const siswa: ProfileIdentity = {
    ...base,
    school: 'SMAN 1 Sungai Beduk',
    student: {
        nik: '3201********0042',
        nisn: '0087654321',
        birth_date: '2009-05-05',
        age: 17,
        gender: 'P',
    },
    can_open_student_data: true,
};

function mountCard(identity: ProfileIdentity, role = 'siswa') {
    return mount(ProfileIdentityCard, {
        props: { name: 'Aulia Rahmawati', role, identity },
    });
}

describe('ProfileIdentityCard', () => {
    it('siswa: menampilkan data siswa dengan NIK dimasker, umur, dan tautan Data saya', () => {
        const text = mountCard(siswa).text();

        expect(text).toContain('3201********0042');
        expect(text).toContain('0087654321');
        expect(text).toContain(`${formatDate('2009-05-05')} · 17 tahun`);
        expect(text).toContain('Perempuan');
        expect(text).toContain('Data siswa tidak diubah di sini');
        expect(mountCard(siswa).find('a[href="/me.show"]').text()).toContain(
            'Lihat dan lengkapi data saya',
        );
    });

    it('siswa tanpa baris Student: baris sekolah tampil dan tautan tetap ada', () => {
        const wrapper = mountCard({ ...siswa, student: null });

        expect(wrapper.text()).toContain('Sekolah');
        expect(wrapper.text()).toContain('SMAN 1 Sungai Beduk');
        expect(wrapper.find('a[href="/me.show"]').exists()).toBe(true);
    });

    it('tautan Data saya berada di luar blok rincian yang dilipat', () => {
        const wrapper = mountCard(siswa);
        const controlled = wrapper.find('button[aria-controls]');
        const detail = wrapper.find(
            `#${controlled.attributes('aria-controls')}`,
        );

        expect(detail.exists()).toBe(true);
        expect(detail.find('a').exists()).toBe(false);
    });

    it('operator: sekolah, akses, dan akun dibuat; tanpa tautan Data saya', () => {
        const wrapper = mountCard(
            {
                ...base,
                school: 'SMAN 1 Sungai Beduk',
                access: 'Memantau siswa dan sesi',
            },
            'operator-sekolah',
        );

        expect(wrapper.text()).toContain('Akses');
        expect(wrapper.text()).toContain('Memantau siswa dan sesi');
        expect(wrapper.text()).toContain('Akun dibuat');
        expect(wrapper.find('a[href="/me.show"]').exists()).toBe(false);
    });

    it('baris Akses hilang bila role tanpa deskripsi', () => {
        const wrapper = mountCard(
            { ...base, school: 'SMAN 1 Sungai Beduk' },
            'operator-sekolah',
        );

        expect(wrapper.text()).not.toContain('Akses');
    });

    it('admin: baris Wilayah se-kecamatan', () => {
        const text = mountCard(base, 'admin-kecamatan').text();

        expect(text).toContain('Wilayah');
        expect(text).toContain('Seluruh sekolah di Kec. Sungai Beduk');
    });

    it('login terakhir diformat, dan "—" bila belum pernah', () => {
        expect(mountCard(base).text()).toContain(
            formatDateTime('2026-10-28T07:42:00+07:00'),
        );
        expect(mountCard({ ...base, last_login_at: null }).text()).toMatch(
            /Login terakhir\s*—/,
        );
    });

    it('login terakhir ada di header ringkas, di luar blok Data diri yang dilipat', () => {
        const wrapper = mountCard(siswa);
        const header = wrapper.get('section > div:first-child');
        const detail = wrapper.get(
            `#${wrapper.get('button[aria-controls]').attributes('aria-controls')}`,
        );

        expect(header.text()).toContain('Login terakhir');
        expect(header.text()).toContain(
            formatDateTime('2026-10-28T07:42:00+07:00'),
        );
        expect(detail.text()).toContain('Login terakhir');
    });

    it('nama jadi judul h2, dengan inisial dan label role', () => {
        const wrapper = mountCard(siswa, 'siswa');

        expect(wrapper.find('h2').text()).toBe('Aulia Rahmawati');
        expect(wrapper.text()).toContain(initials('Aulia Rahmawati'));
        expect(wrapper.text()).toContain(humanize('siswa'));
        expect(wrapper.find('section').attributes('aria-labelledby')).toBe(
            wrapper.find('h2').attributes('id'),
        );
    });

    it('tombol Data diri membuka dan menutup rincian', async () => {
        const wrapper = mountCard(siswa);
        const toggle = wrapper.find('button[aria-controls]');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatusBadge from './StatusBadge.vue';

describe('StatusBadge', () => {
    it('menampilkan label teks (tidak hanya warna)', () => {
        const wrapper = mount(StatusBadge, { props: { status: 'menunggu' } });
        expect(wrapper.text()).toContain('Menunggu aktivasi');
        expect(wrapper.classes().join(' ')).toContain('bg-warning-soft');
    });

    it('status tak dikenal tetap tampil netral', () => {
        const wrapper = mount(StatusBadge, { props: { status: 'aneh' } });
        expect(wrapper.text()).toContain('aneh');
        expect(wrapper.classes().join(' ')).toContain('bg-neutral-soft');
    });
});

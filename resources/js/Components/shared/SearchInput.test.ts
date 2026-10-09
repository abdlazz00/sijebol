import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SearchInput from './SearchInput.vue';

describe('SearchInput', () => {
    it('meneruskan aria-label ke input, bukan ke pembungkus', () => {
        const wrapper = mount(SearchInput, {
            attrs: { 'aria-label': 'Cari siswa' },
        });

        expect(wrapper.find('input').attributes('aria-label')).toBe(
            'Cari siswa',
        );
        expect(wrapper.find('div').attributes('aria-label')).toBeUndefined();
    });

    it('tetap meneruskan id dan placeholder ke input', () => {
        const wrapper = mount(SearchInput, {
            props: { id: 'cari', placeholder: 'Cari…' },
        });

        expect(wrapper.find('input').attributes('id')).toBe('cari');
        expect(wrapper.find('input').attributes('placeholder')).toBe('Cari…');
    });
});

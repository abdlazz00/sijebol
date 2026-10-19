import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import KtpLangsungBadge from './KtpLangsungBadge.vue';

describe('KtpLangsungBadge', () => {
    it('menampilkan teks (bukan hanya warna) dan makna untuk pembaca layar', () => {
        const w = mount(KtpLangsungBadge);

        expect(w.text()).toContain('KTP-el langsung');
        expect(w.find('.sr-only').text()).toContain(
            'Berumur lebih dari 17 tahun; KTP-el dapat langsung terbit.',
        );
        expect(w.attributes('title')).toContain('KTP-el dapat langsung terbit');
        expect(w.find('svg').attributes('aria-hidden')).toBe('true');
    });
});

import { describe, expect, it, vi, type Mock } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ usePage: vi.fn() }));

import { usePage } from '@inertiajs/vue3';
import { useFlash } from './useFlash';

describe('useFlash', () => {
    it('mengembalikan flash dari props', () => {
        (usePage as Mock).mockReturnValue({
            props: { flash: { success: 'Tersimpan', error: null } },
        });
        expect(useFlash().flash.value).toEqual({
            success: 'Tersimpan',
            error: null,
        });
    });

    it('aman bila flash tidak ada', () => {
        (usePage as Mock).mockReturnValue({ props: {} });
        expect(useFlash().flash.value).toEqual({});
    });
});

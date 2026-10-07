import { afterEach, describe, expect, it, vi } from 'vitest';
import { copyText } from './clipboard';

afterEach(() => vi.unstubAllGlobals());

describe('copyText', () => {
    it('memakai navigator.clipboard bila tersedia', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        expect(await copyText('halo')).toBe(true);
        expect(writeText).toHaveBeenCalledWith('halo');
    });

    it('jatuh ke execCommand saat clipboard API tidak ada', async () => {
        vi.stubGlobal('navigator', {});
        document.execCommand = vi.fn().mockReturnValue(true);

        expect(await copyText('halo')).toBe(true);
        expect(document.execCommand).toHaveBeenCalledWith('copy');
    });

    it('false bila semuanya gagal', async () => {
        vi.stubGlobal('navigator', {
            clipboard: { writeText: vi.fn().mockRejectedValue(new Error('x')) },
        });
        document.execCommand = vi.fn().mockReturnValue(false);

        expect(await copyText('halo')).toBe(false);
    });
});

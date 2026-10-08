import { describe, expect, it } from 'vitest';
import { formatBytes, MAX_BYTES, validateFile } from './files';

describe('validateFile', () => {
    it('menerima PDF, JPG, dan PNG di bawah batas', () => {
        for (const type of ['application/pdf', 'image/jpeg', 'image/png']) {
            expect(validateFile({ type, size: 1000 })).toBeNull();
        }
    });

    it('menolak tipe lain dengan pesan tipe', () => {
        expect(validateFile({ type: 'text/plain', size: 10 })).toBe(
            'Berkas harus PDF, JPG, atau PNG.',
        );
    });

    it('menolak ukuran di atas 5 MB tetapi menerima tepat 5 MB', () => {
        expect(
            validateFile({ type: 'application/pdf', size: MAX_BYTES + 1 }),
        ).toBe('Ukuran berkas maksimal 5 MB.');
        expect(
            validateFile({ type: 'application/pdf', size: MAX_BYTES }),
        ).toBeNull();
    });
});

describe('formatBytes', () => {
    it('memakai satuan B, KB, dan MB dengan koma desimal', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(1536)).toBe('1,5 KB');
        expect(formatBytes(5 * 1024 * 1024)).toBe('5,0 MB');
    });
});

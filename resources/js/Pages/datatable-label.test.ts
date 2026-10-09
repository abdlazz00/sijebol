import { describe, expect, it } from 'vitest';

const pages = import.meta.glob('./**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
}) as Record<string, string>;

describe('label DataTable', () => {
    it('setiap halaman yang memakai DataTable memberi label', () => {
        const missing = Object.entries(pages)
            .filter(([, src]) => src.includes('<DataTable'))
            .filter(([, src]) =>
                [...src.matchAll(/<DataTable\b[^>]*>/gs)].some(
                    (m) => !/\slabel=/.test(m[0]),
                ),
            )
            .map(([path]) => path);

        expect(missing).toEqual([]);
    });
});

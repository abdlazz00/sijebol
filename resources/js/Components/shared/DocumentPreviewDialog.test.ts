import type { StudentDocumentRow } from '@/types/student';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import DocumentPreviewDialog from './DocumentPreviewDialog.vue';

describe('DocumentPreviewDialog', () => {
    const mockDocs: StudentDocumentRow[] = [
        {
            type: 'kk',
            name: 'kk-0012345678.pdf',
            size: 102400,
            mime: 'application/pdf',
            url: '/siswa/1/dokumen/kk',
            preview_url: '/siswa/1/dokumen/kk/preview',
        },
        {
            type: 'akta_lahir',
            name: 'akta-0012345678.jpg',
            size: 204800,
            mime: 'image/jpeg',
            url: '/siswa/1/dokumen/akta_lahir',
            preview_url: '/siswa/1/dokumen/akta_lahir/preview',
        },
    ];

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('merender judul dan daftar tab dokumen', async () => {
        mount(DocumentPreviewDialog, {
            props: {
                open: true,
                title: 'Ahmad Dahlan',
                documents: mockDocs,
                initialType: 'kk',
            },
            attachTo: document.body,
        });
        await flushPromises();

        expect(document.body.textContent).toContain('Ahmad Dahlan');
        expect(document.body.textContent).toContain('Kartu Keluarga');
        expect(document.body.textContent).toContain('Akta Kelahiran');
    });

    it('menampilkan iframe saat berkas aktif adalah PDF', async () => {
        mount(DocumentPreviewDialog, {
            props: {
                open: true,
                title: 'Ahmad Dahlan',
                documents: mockDocs,
                initialType: 'kk',
            },
            attachTo: document.body,
        });
        await flushPromises();

        const iframe = document.body.querySelector('iframe');
        expect(iframe).not.toBeNull();
        expect(iframe?.getAttribute('src')).toBe('/siswa/1/dokumen/kk/preview');
    });

    it('berpindah ke gambar saat tab Akta Kelahiran diklik', async () => {
        mount(DocumentPreviewDialog, {
            props: {
                open: true,
                title: 'Ahmad Dahlan',
                documents: mockDocs,
                initialType: 'kk',
            },
            attachTo: document.body,
        });
        await flushPromises();

        const tabs = document.body.querySelectorAll('button[role="tab"]');
        expect(tabs.length).toBe(2);

        // Klik tab Akta Kelahiran
        (tabs[1] as HTMLButtonElement).click();
        await flushPromises();

        const img = document.body.querySelector('img');
        expect(img).not.toBeNull();
        expect(img?.getAttribute('src')).toBe('/siswa/1/dokumen/akta_lahir/preview');
        expect(document.body.textContent).toContain('100%');
    });

    it('menyediakan tautan unduh dan buka tab baru', async () => {
        mount(DocumentPreviewDialog, {
            props: {
                open: true,
                title: 'Ahmad Dahlan',
                documents: mockDocs,
                initialType: 'kk',
            },
            attachTo: document.body,
        });
        await flushPromises();

        const links = Array.from(document.body.querySelectorAll('a'));
        const openLink = links.find((l) => l.textContent?.includes('Buka Tab Baru'));
        const downloadLink = links.find((l) => l.textContent?.includes('Unduh'));

        expect(openLink?.getAttribute('href')).toBe('/siswa/1/dokumen/kk/preview');
        expect(openLink?.getAttribute('target')).toBe('_blank');
        expect(downloadLink?.getAttribute('href')).toBe('/siswa/1/dokumen/kk');
    });
});

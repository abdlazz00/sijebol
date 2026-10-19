const ACCEPT = ['application/pdf', 'image/jpeg', 'image/png'];
export const MAX_BYTES = 5 * 1024 * 1024;

const decimal = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

/** Pesan galat sama dengan server; null bila berkas boleh diunggah. */
export function validateFile(file: {
    type: string;
    size: number;
}): string | null {
    if (!ACCEPT.includes(file.type)) return 'Berkas harus PDF, JPG, atau PNG.';
    if (file.size > MAX_BYTES) {
        const advice =
            file.type === 'application/pdf'
                ? 'Pilih PDF yang lebih kecil.'
                : 'Ambil ulang dengan resolusi lebih rendah, atau pilih PDF.';

        return `Berkas ${formatBytes(file.size)} terlalu besar (maksimal 5 MB). ${advice}`;
    }

    return null;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${decimal.format(bytes / 1024)} KB`;

    return `${decimal.format(bytes / (1024 * 1024))} MB`;
}

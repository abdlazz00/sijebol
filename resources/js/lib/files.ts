export const ACCEPT = ['application/pdf', 'image/jpeg', 'image/png'];
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
    if (file.size > MAX_BYTES) return 'Ukuran berkas maksimal 5 MB.';

    return null;
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${decimal.format(bytes / 1024)} KB`;

    return `${decimal.format(bytes / (1024 * 1024))} MB`;
}

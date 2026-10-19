const SHRINKABLE = ['image/jpeg', 'image/png'];
const SKIP_BELOW_BYTES = 1024 * 1024;

/** Ukuran baru dengan sisi terpanjang paling besar `maxSide`, rasio tetap, tidak pernah membesarkan. */
export function fitWithin(width: number, height: number, maxSide: number) {
    const scale = Math.min(1, maxSide / Math.max(width, height));

    return {
        width: Math.round(width * scale),
        height: Math.round(height * scale),
    };
}

/**
 * Foto kamera HP sering 3-12 MB; dikecilkan jadi JPEG sebelum diunggah. PDF, berkas kecil, atau gambar yang
 * gagal didekode dikembalikan apa adanya (server tetap memvalidasi).
 */
export async function shrinkImage(
    file: File,
    maxSide = 2400,
    quality = 0.85,
): Promise<File> {
    if (!SHRINKABLE.includes(file.type) || file.size <= SKIP_BELOW_BYTES) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const { width, height } = fitWithin(
            bitmap.width,
            bitmap.height,
            maxSide,
        );
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d')?.drawImage(bitmap, 0, 0, width, height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', quality),
        );
        if (!blob || blob.size >= file.size) return file;

        return new File([blob], `${file.name.replace(/\.\w+$/, '')}.jpg`, {
            type: 'image/jpeg',
        });
    } catch {
        return file;
    }
}

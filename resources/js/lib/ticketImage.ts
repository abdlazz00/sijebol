/** Kartu tiket PNG yang tetap berguna dari galeri HP tanpa sinyal: QR + identitas siswa + sesi. */
export type TicketImageInfo = {
    studentName: string;
    school: string;
    day: string;
    time: string;
    location: string;
};

const WIDTH = 900;
const HEIGHT = 1280;
const FONT = 'system-ui, -apple-system, "Segoe UI", sans-serif';

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('QR gagal dimuat'));
        img.src = src;
    });
}

/** Mengecilkan huruf sampai teks muat; nama atau lokasi yang panjang tidak terpotong. */
function drawFitted(
    ctx: CanvasRenderingContext2D,
    text: string,
    y: number,
    startSize: number,
    weight: number,
) {
    let size = startSize;
    ctx.font = `${weight} ${size}px ${FONT}`;
    while (size > 20 && ctx.measureText(text).width > WIDTH - 100) {
        size -= 2;
        ctx.font = `${weight} ${size}px ${FONT}`;
    }
    ctx.fillText(text, WIDTH / 2, y);
}

export async function renderTicket(
    qrDataUrl: string,
    info: TicketImageInfo,
): Promise<string> {
    const qr = await loadImage(qrDataUrl);
    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas tidak tersedia');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    ctx.fillStyle = '#111827';
    ctx.textAlign = 'center';
    drawFitted(ctx, 'Tiket Rekaman KTP-el', 90, 44, 700);
    ctx.drawImage(qr, 130, 140, 640, 640);
    drawFitted(ctx, info.studentName, 880, 52, 700);
    drawFitted(ctx, info.school, 940, 34, 500);
    drawFitted(ctx, info.day, 1030, 40, 600);
    drawFitted(ctx, info.time, 1090, 40, 600);
    drawFitted(ctx, info.location, 1150, 34, 500);

    ctx.fillStyle = '#4b5563';
    drawFitted(ctx, 'SIJEBOL · Kecamatan Sungai Beduk', 1230, 26, 400);

    return canvas.toDataURL('image/png');
}

export function downloadDataUrl(url: string, filename: string): void {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.append(a);
    a.click();
    a.remove();
}

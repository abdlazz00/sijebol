const TZ = 'Asia/Jakarta';
const EMPTY = '—';

type DateInput = string | Date | null | undefined;

const dateFmt = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: TZ,
});
const dayFmt = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    timeZone: TZ,
});
const timeFmt = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: TZ,
});
const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'always' });

function toDate(value: DateInput): Date | null {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function formatDate(value: DateInput): string {
    const d = toDate(value);
    return d ? dateFmt.format(d) : EMPTY;
}

export function formatTime(value: DateInput): string {
    const d = toDate(value);
    return d ? timeFmt.format(d) : EMPTY;
}

/** "7 Okt 2026 · 14.30". */
export function formatDateTime(value: DateInput): string {
    const d = toDate(value);
    return d ? `${dateFmt.format(d)} · ${timeFmt.format(d)}` : EMPTY;
}

/** "Rabu, 7 Okt 2026". */
export function formatDateWithDay(value: DateInput): string {
    const d = toDate(value);
    return d ? dayFmt.format(d) : EMPTY;
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000],
    ['month', 2_592_000],
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

export function relativeTime(value: DateInput, now: Date = new Date()): string {
    const d = toDate(value);
    if (!d) return EMPTY;
    const diff = Math.round((d.getTime() - now.getTime()) / 1000);
    for (const [unit, seconds] of UNITS) {
        if (Math.abs(diff) >= seconds) {
            return rtf
                .format(Math.round(diff / seconds), unit)
                .replace(' yang lalu', ' lalu');
        }
    }
    return 'baru saja';
}

const DAY_MS = 86_400_000;

/** Kurang dari 24 jam: "2 jam lalu"; lebih lama: "6 Okt 2026 · 08.00". */
export function formatWhen(value: DateInput, now: Date = new Date()): string {
    const d = toDate(value);
    if (!d) return EMPTY;

    return Math.abs(now.getTime() - d.getTime()) < DAY_MS
        ? relativeTime(d, now)
        : formatDateTime(d);
}

/** "08:00" -> "08.00" (jam dari server berbentuk HH:MM, bukan tanggal). */
export function formatClock(value: string | null | undefined): string {
    return value ? value.replace(':', '.') : EMPTY;
}

/** "08.00–12.00". */
export function formatTimeRange(
    start: string | null | undefined,
    end: string | null | undefined,
): string {
    return `${formatClock(start)}–${formatClock(end)}`;
}

const monthFmt = new Intl.DateTimeFormat('id-ID', {
    month: 'long',
    year: 'numeric',
    timeZone: TZ,
});

/** "Oktober 2026" dari tanggal Y-m-d. */
export function formatMonth(value: DateInput): string {
    const d = toDate(value);
    return d ? monthFmt.format(d) : EMPTY;
}

const TZ = 'Asia/Jakarta';
const EMPTY = '—';

type DateInput = string | Date | null | undefined;

const dateFmt = new Intl.DateTimeFormat('id-ID', {
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
const ymdFmt = new Intl.DateTimeFormat('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: TZ,
});
const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' });

function toDate(value: DateInput): Date | null {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
}

function ymd(value: string | Date): [number, number, number] | null {
    if (typeof value === 'string') {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);
        return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
    }
    const parts = Object.fromEntries(
        ymdFmt.formatToParts(value).map((p) => [p.type, p.value]),
    );
    return [Number(parts.year), Number(parts.month), Number(parts.day)];
}

export function formatDate(value: DateInput): string {
    const d = toDate(value);
    return d ? dateFmt.format(d) : EMPTY;
}

export function formatTime(value: DateInput): string {
    const d = toDate(value);
    return d ? timeFmt.format(d) : EMPTY;
}

export function formatDateTime(value: DateInput): string {
    const d = toDate(value);
    return d ? `${dateFmt.format(d)} ${timeFmt.format(d)}` : EMPTY;
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
            return rtf.format(Math.round(diff / seconds), unit);
        }
    }
    return 'baru saja';
}

/** Hanya untuk tampilan; kelayakan umur ditentukan server. */
export function calculateAge(
    birthDate: string,
    on: string | Date = new Date(),
): number | null {
    const b = ymd(birthDate);
    const o = ymd(on);
    if (!b || !o) return null;
    let age = o[0] - b[0];
    if (o[1] < b[1] || (o[1] === b[1] && o[2] < b[2])) age--;
    return age;
}

const numberFmt = new Intl.NumberFormat('id-ID');

export function maskNik(nik?: string | null): string {
    if (!nik) return '—';
    if (nik.length <= 8) return '*'.repeat(nik.length);
    return nik.slice(0, 4) + '*'.repeat(nik.length - 8) + nik.slice(-4);
}

export function formatNumber(value: number): string {
    return numberFmt.format(value);
}

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    return parts
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join('');
}

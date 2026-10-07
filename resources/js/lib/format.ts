const numberFmt = new Intl.NumberFormat('id-ID');

export function formatNumber(value: number): string {
    return numberFmt.format(value);
}

/** "admin-kecamatan" → "Admin kecamatan". */
export function humanize(text?: string | null): string {
    if (!text) return '—';
    const spaced = text.replace(/[-_]+/g, ' ').trim();

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

export function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    return parts
        .slice(0, 2)
        .map((p) => p[0].toUpperCase())
        .join('');
}

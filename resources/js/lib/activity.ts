import type { ActivityChange } from '@/types/notification';

/** Nilai perubahan untuk tampilan; kosong menjadi tanda strip. */
export function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

/** "name: A → B; phone: — → 0812" (maks `limit` perubahan). */
export function summarizeChanges(changes: ActivityChange[], limit = 2): string {
    return (
        changes
            .slice(0, limit)
            .map(
                (c) =>
                    `${c.field}: ${formatValue(c.old)} → ${formatValue(c.new)}`,
            )
            .join('; ') || '—'
    );
}

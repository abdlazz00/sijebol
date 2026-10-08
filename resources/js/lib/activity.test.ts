import { describe, expect, it } from 'vitest';
import { formatValue, summarizeChanges } from './activity';

describe('activity', () => {
    it('memformat nilai kosong, objek, dan angka', () => {
        expect(formatValue(null)).toBe('—');
        expect(formatValue(undefined)).toBe('—');
        expect(formatValue('')).toBe('—');
        expect(formatValue({ a: 1 })).toBe('{"a":1}');
        expect(formatValue(5)).toBe('5');
        expect(formatValue(false)).toBe('false');
    });

    it('meringkas sampai dua perubahan pertama', () => {
        const changes = [
            { field: 'name', old: 'A', new: 'B' },
            { field: 'phone', old: null, new: '0812' },
            { field: 'x', old: 1, new: 2 },
        ];

        expect(summarizeChanges(changes)).toBe('name: A → B; phone: — → 0812');
        expect(summarizeChanges(changes, 1)).toBe('name: A → B');
    });

    it('tanpa perubahan menghasilkan tanda strip', () => {
        expect(summarizeChanges([])).toBe('—');
    });
});

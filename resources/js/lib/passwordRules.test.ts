import { describe, expect, it } from 'vitest';
import { passwordRules, passwordScore } from './passwordRules';

describe('passwordRules', () => {
    it('kosong tidak memenuhi aturan apa pun', () => {
        expect(passwordRules('').every((r) => !r.met)).toBe(true);
        expect(passwordScore('')).toBe(0);
    });

    it('menilai tiap aturan terpisah', () => {
        expect(passwordRules('abcdefgh').map((r) => r.met)).toEqual([
            true,
            true,
            false,
        ]);
        expect(passwordRules('12345678').map((r) => r.met)).toEqual([
            true,
            false,
            true,
        ]);
        expect(passwordRules('a1').map((r) => r.met)).toEqual([
            false,
            true,
            true,
        ]);
    });

    it('skor penuh untuk password kuat', () => {
        expect(passwordScore('abcd1234')).toBe(3);
    });
});

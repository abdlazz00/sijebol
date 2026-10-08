import { Circle, Home, ScrollText, Users } from '@lucide/vue';
import { describe, expect, it } from 'vitest';
import { menuIcon } from './menuIcons';

describe('menuIcon', () => {
    it('memetakan kunci ikon dari server', () => {
        expect(menuIcon('home')).toBe(Home);
        expect(menuIcon('users')).toBe(Users);
        expect(menuIcon('scroll-text')).toBe(ScrollText);
    });

    it('kunci tak dikenal jatuh ke ikon default', () => {
        expect(menuIcon('tidak-ada')).toBe(Circle);
    });
});

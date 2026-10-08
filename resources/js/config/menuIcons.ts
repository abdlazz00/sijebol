import {
    Circle,
    Home,
    School,
    ScrollText,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';

const icons: Record<string, Component> = {
    home: Home,
    users: Users,
    'shield-check': ShieldCheck,
    school: School,
    'scroll-text': ScrollText,
};

/** Kunci ikon datang dari server (`MenuService`); kunci tak dikenal tidak boleh merusak sidebar. */
export function menuIcon(key: string): Component {
    return icons[key] ?? Circle;
}

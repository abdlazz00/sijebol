import {
    BadgeCheck,
    CalendarDays,
    Circle,
    ClipboardList,
    FileSpreadsheet,
    GraduationCap,
    Home,
    Link2,
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
    'graduation-cap': GraduationCap,
    'clipboard-list': ClipboardList,
    'badge-check': BadgeCheck,
    'calendar-days': CalendarDays,
    'file-spreadsheet': FileSpreadsheet,
    link: Link2,
};

/** Kunci ikon datang dari server (`MenuService`); kunci tak dikenal tidak boleh merusak sidebar. */
export function menuIcon(key: string): Component {
    return icons[key] ?? Circle;
}

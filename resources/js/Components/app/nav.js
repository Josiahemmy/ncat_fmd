import {
    LayoutDashboard,
    Plane,
    Wrench,
    ScrollText,
    PackagePlus,
    PackageMinus,
    BookOpenCheck,
    Boxes,
    BarChart3,
    ShieldCheck,
} from 'lucide-react';

/**
 * Sidebar navigation — order per design spec §7. `routeName` maps to a named
 * Ziggy route; `group` drives the visual sections in the sidebar.
 */
export const navItems = [
    { label: 'Dashboard', icon: LayoutDashboard, routeName: 'dashboard', group: 'overview' },

    { label: 'Aircraft Types', icon: Plane, routeName: 'aircraft-types', group: 'operations' },
    { label: 'Work Orders', icon: Wrench, routeName: 'work-orders', group: 'operations' },
    { label: 'Requisitions', icon: ScrollText, routeName: 'requisitions', group: 'operations' },
    { label: 'Receiving', icon: PackagePlus, routeName: 'receiving', group: 'operations' },
    { label: 'Issuing', icon: PackageMinus, routeName: 'issuing', group: 'operations' },
    { label: 'Tally Cards', icon: BookOpenCheck, routeName: 'tally-cards', group: 'operations' },

    { label: 'Parts', icon: Boxes, routeName: 'parts', group: 'catalogue' },
    { label: 'Reports', icon: BarChart3, routeName: 'reports', group: 'catalogue' },

    { label: 'Administration', icon: ShieldCheck, routeName: 'administration', group: 'system' },
];

export const navGroups = [
    { key: 'overview', label: null },
    { key: 'operations', label: 'Operations' },
    { key: 'catalogue', label: 'Catalogue' },
    { key: 'system', label: 'System' },
];

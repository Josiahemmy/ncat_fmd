import {
    LayoutDashboard,
    Plane,
    Warehouse,
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
 * Sidebar navigation — order per design spec §7 (v1.1). `permission` (single)
 * or `permissionsAny` (array) controls visibility; items with neither are
 * always shown. The Sidebar filters against the user's shared permissions.
 */
export const navItems = [
    { label: 'Dashboard', icon: LayoutDashboard, routeName: 'dashboard', group: 'overview' },

    { label: 'Aircraft Types', icon: Plane, routeName: 'aircraft-types', group: 'operations', permission: 'aircraft.view' },
    { label: 'Stores', icon: Warehouse, routeName: 'stores.index', group: 'operations', permission: 'stores.view' },
    { label: 'Work Orders', icon: Wrench, routeName: 'work-orders', group: 'operations', permission: 'work_orders.view' },
    { label: 'Requisitions', icon: ScrollText, routeName: 'requisitions', group: 'operations', permission: 'requisitions.view' },
    { label: 'Receiving', icon: PackagePlus, routeName: 'receiving', group: 'operations', permission: 'receiving.view' },
    { label: 'Issuing', icon: PackageMinus, routeName: 'issuing', group: 'operations', permission: 'issues.view' },
    { label: 'Tally Cards', icon: BookOpenCheck, routeName: 'tally-cards', group: 'operations', permission: 'tally.view' },

    { label: 'Parts', icon: Boxes, routeName: 'parts.index', group: 'catalogue', permission: 'parts.view' },
    { label: 'Reports', icon: BarChart3, routeName: 'reports', group: 'catalogue', permission: 'reports.view' },

    {
        label: 'Administration',
        icon: ShieldCheck,
        routeName: 'admin.dashboard',
        group: 'system',
        permissionsAny: [
            'users.view', 'roles.view', 'aircraft.view', 'stores.view',
            'ata.view', 'counters.view', 'audit.view',
        ],
    },
];

export const navGroups = [
    { key: 'overview', label: null },
    { key: 'operations', label: 'Operations' },
    { key: 'catalogue', label: 'Catalogue' },
    { key: 'system', label: 'System' },
];

import { Link } from '@inertiajs/react';
import {
    Activity,
    GitBranch,
    Hash,
    LayoutGrid,
    ListOrdered,
    Plane,
    ShieldCheck,
    Users,
    Warehouse,
} from 'lucide-react';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';

const SECTIONS = [
    { label: 'Overview', icon: LayoutGrid, route: 'admin.dashboard' },
    { label: 'Users', icon: Users, route: 'admin.users.index', permission: 'users.view' },
    { label: 'Roles', icon: ShieldCheck, route: 'admin.roles.index', permission: 'roles.view' },
    { label: 'Fleet', icon: Plane, route: 'admin.fleet.index', permission: 'aircraft.view' },
    { label: 'Stores', icon: Warehouse, route: 'admin.stores.index', permission: 'stores.view' },
    { label: 'ATA Chapters', icon: Hash, route: 'admin.ata.index', permission: 'ata.view' },
    { label: 'Counters', icon: ListOrdered, route: 'admin.counters.index', permission: 'counters.view' },
    { label: 'Approval Workflow', icon: GitBranch, route: 'admin.approvals.index', permission: 'approvals.manage' },
    { label: 'Activity Log', icon: Activity, route: 'admin.activity.index', permission: 'audit.view' },
];

export function AdminNav() {
    const { can } = usePermissions();
    const visible = SECTIONS.filter((s) => !s.permission || can(s.permission));

    const isActive = (name) => {
        try {
            return route().current(name);
        } catch {
            return false;
        }
    };

    return (
        <div className="mb-6 overflow-x-auto border-b border-border">
            <nav className="flex min-w-max gap-1 pb-px">
                {visible.map((s) => {
                    const active = isActive(s.route);
                    const Icon = s.icon;
                    return (
                        <Link
                            key={s.route}
                            href={route(s.route)}
                            className={cn(
                                'flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm font-medium transition-colors',
                                active
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                            {s.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

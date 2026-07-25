import { Head, Link } from '@inertiajs/react';
import {
    Activity, GitBranch, Hash, ListOrdered, Plane, ShieldCheck, Users, Warehouse,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { usePermissions } from '@/lib/permissions';

export default function AdminDashboard({ counts }) {
    const { can } = usePermissions();

    const cards = [
        { label: 'Users', value: `${counts.activeUsers}/${counts.users}`, hint: 'active / total', icon: Users, route: 'admin.users.index', permission: 'users.view' },
        { label: 'Roles', value: counts.roles, icon: ShieldCheck, route: 'admin.roles.index', permission: 'roles.view' },
        { label: 'Aircraft', value: counts.aircraft, hint: `${counts.types} types`, icon: Plane, route: 'admin.fleet.index', permission: 'aircraft.view' },
        { label: 'Stores', value: counts.stores, icon: Warehouse, route: 'admin.stores.index', permission: 'stores.view' },
        { label: 'ATA Chapters', value: counts.ataChapters, icon: Hash, route: 'admin.ata.index', permission: 'ata.view' },
        { label: 'Document Counters', value: counts.counters, icon: ListOrdered, route: 'admin.counters.index', permission: 'counters.view' },
        { label: 'Approval Workflow', value: counts.approvalLevels, hint: 'requisition levels', icon: GitBranch, route: 'admin.approvals.index', permission: 'approvals.manage' },
        { label: 'Activity Log', value: '—', hint: 'audit trail', icon: Activity, route: 'admin.activity.index', permission: 'audit.view' },
    ].filter((c) => can(c.permission ?? ''));

    return (
        <AppLayout>
            <Head title="Administration" />
            <PageHeader
                eyebrow="System"
                title="Administration"
                description="Manage users, roles, the fleet, stores, reference data and the audit trail."
                icon={ShieldCheck}
            />
            <AdminNav />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {cards.map((c) => {
                    const Icon = c.icon;
                    return (
                        <Link key={c.label} href={route(c.route)}>
                            <Card variant="glass" className="group h-full p-5 transition-shadow hover:shadow-glass-lg">
                                <div className="flex items-start justify-between">
                                    <p className="text-sm font-medium text-muted-foreground">{c.label}</p>
                                    <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-white">
                                        <Icon className="size-[18px]" />
                                    </span>
                                </div>
                                <p className="mt-3 font-display text-2xl font-bold text-ncat-navy">{c.value}</p>
                                {c.hint && <p className="mt-0.5 text-xs text-muted-foreground">{c.hint}</p>}
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}

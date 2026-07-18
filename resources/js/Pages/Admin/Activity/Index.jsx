import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Activity as ActivityIcon, Filter } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';

const EVENT_TONE = {
    created: 'success', updated: 'info', deleted: 'error',
    login: 'neutral', logout: 'neutral', password_reset: 'warning', password_changed: 'warning',
};

export default function ActivityIndex({ activities, filters, logNames, events }) {
    const [local, setLocal] = useState(filters);

    const apply = (next = local) => {
        router.get(route('admin.activity.index'), next, { preserveState: true, replace: true });
    };
    const reset = () => {
        const cleared = { log_name: '', event: '', search: '', from: '', to: '' };
        setLocal(cleared);
        apply(cleared);
    };
    const set = (k, v) => setLocal((s) => ({ ...s, [k]: v }));

    return (
        <AppLayout>
            <Head title="Activity Log · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Activity Log"
                description="Government-grade audit trail of every create, update, delete, and sign-in."
                icon={ActivityIcon}
            />
            <AdminNav />

            <Card className="mb-4 p-4">
                <form onSubmit={(e) => { e.preventDefault(); apply(); }} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <Input placeholder="Search description…" value={local.search} onChange={(e) => set('search', e.target.value)} className="lg:col-span-2" />
                    <Select value={local.log_name} onChange={(e) => set('log_name', e.target.value)}>
                        <option value="">All areas</option>
                        {logNames.map((n) => <option key={n} value={n}>{n}</option>)}
                    </Select>
                    <Select value={local.event} onChange={(e) => set('event', e.target.value)}>
                        <option value="">All events</option>
                        {events.map((n) => <option key={n} value={n}>{n}</option>)}
                    </Select>
                    <Input type="date" value={local.from} onChange={(e) => set('from', e.target.value)} />
                    <Input type="date" value={local.to} onChange={(e) => set('to', e.target.value)} />
                    <div className="flex gap-2 sm:col-span-2 lg:col-span-6">
                        <Button type="submit"><Filter className="size-4" /> Apply</Button>
                        <Button type="button" variant="outline" onClick={reset}>Reset</Button>
                    </div>
                </form>
            </Card>

            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>When</TableHead>
                            <TableHead>User</TableHead>
                            <TableHead>Area</TableHead>
                            <TableHead>Event</TableHead>
                            <TableHead>Description</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {activities.data.map((a) => (
                            <TableRow key={a.id}>
                                <TableCell className="whitespace-nowrap text-sm text-muted-foreground" title={a.created_at}>
                                    {a.created_for_humans}
                                </TableCell>
                                <TableCell className="font-medium text-ncat-navy">{a.causer}</TableCell>
                                <TableCell><Badge variant="neutral">{a.log_name}</Badge></TableCell>
                                <TableCell>
                                    {a.event && <Badge variant={EVENT_TONE[a.event] ?? 'neutral'}>{a.event}</Badge>}
                                </TableCell>
                                <TableCell className="text-sm">{a.description}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!activities.data.length && <p className="p-8 text-center text-sm text-muted-foreground">No activity matches your filters.</p>}
            </Card>

            {activities.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {activities.links.map((link, i) => (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

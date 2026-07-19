import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Inbox, Plus, ScrollText, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { RequisitionStatusBadge } from '@/Components/documents/RequisitionBadges';
import { usePermissions } from '@/lib/permissions';

export default function RequisitionsIndex({ requisitions, aircraft, filters }) {
    const { can } = usePermissions();
    const canCreate = can('requisitions.create');
    const [f, setF] = useState(filters);

    const apply = (next = f) => router.get(route('requisitions.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Requisitions" />
            <PageHeader
                eyebrow="Operations"
                title="Requisitions"
                description="Aircraft spare parts requisitions — one voucher per unit, from draft through approval to issue."
                icon={ScrollText}
                actions={canCreate && (
                    <Button onClick={() => router.visit(route('requisitions.create'))}>
                        <Plus className="size-4" /> New requisition
                    </Button>
                )}
            />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search no. / description / part no…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="issued">Issued</option>
                        <option value="closed">Closed</option>
                    </Select>
                    <Select value={f.aircraft} onChange={(e) => set('aircraft', e.target.value)}>
                        <option value="">All aircraft</option>
                        {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                    </Select>
                    <div className="flex items-center lg:col-span-2">
                        <Button type="button" variant={f.status === 'submitted' ? 'default' : 'outline'} className="w-full"
                            onClick={() => set('status', f.status === 'submitted' ? '' : 'submitted')}>
                            <Inbox className="size-4" /> Approval queue
                        </Button>
                    </div>
                </div>
            </Card>

            <Card className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No.</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Aircraft</TableHead>
                            <TableHead>Part</TableHead>
                            <TableHead>WO</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {requisitions.map((r) => (
                            <TableRow key={r.id}>
                                <TableCell className="whitespace-nowrap">
                                    <Link href={route('requisitions.show', r.id)} className="font-mono text-sm font-medium text-ncat-navy hover:text-primary">
                                        {r.requisition_no}
                                    </Link>
                                </TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{r.date ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-sm">{r.aircraft_reg ?? '—'}</TableCell>
                                <TableCell className="max-w-xs truncate text-sm">{r.part_no ?? r.full_description}</TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-xs">{r.wo_ref ?? '—'}</TableCell>
                                <TableCell><RequisitionStatusBadge status={r.status} /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!requisitions.length && (
                    <div className="p-10 text-center">
                        <ScrollText className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No requisitions match these filters.</p>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

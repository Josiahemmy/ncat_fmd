import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Search, Ship } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';

/** The state a shipment is in, as one badge rather than four columns. */
function ShipmentState({ shipment }) {
    if (shipment.is_closed) return <Badge variant="neutral">Closed</Badge>;
    if (shipment.has_arrived) return <Badge variant="success">Arrived</Badge>;
    if (shipment.is_overdue) {
        return (
            <Badge variant="destructive">
                Overdue by {shipment.days_overdue}d
            </Badge>
        );
    }
    return <Badge variant="info">In transit</Badge>;
}

export default function ShipmentsIndex({ shipments, filters, vendors, statuses, can }) {
    const [f, setF] = useState(filters);

    const apply = (next) => router.get(route('shipments.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    const overdueCount = shipments.filter((s) => s.is_overdue).length;

    return (
        <AppLayout>
            <Head title="Shipping" />
            <PageHeader
                eyebrow="Operations"
                title="Shipping"
                description="Consignments on their way to NCAT, tracked event by event."
                icon={Ship}
                actions={can.manage && (
                    <Button onClick={() => router.visit(route('shipments.create'))}>
                        <Plus className="size-4" /> New shipment
                    </Button>
                )}
            />

            {overdueCount > 0 && f.state !== 'overdue' && (
                <button
                    type="button"
                    onClick={() => set('state', 'overdue')}
                    className="mb-4 flex w-full items-center gap-2.5 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-left text-sm text-ncat-navy transition-colors hover:bg-destructive/10"
                >
                    <AlertTriangle className="size-4 shrink-0 text-destructive" />
                    <span>
                        <strong className="font-semibold">{overdueCount}</strong>{' '}
                        {overdueCount === 1 ? 'shipment is' : 'shipments are'} past the expected arrival date.
                        Show only those.
                    </span>
                </button>
            )}

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search reference / AWB / carrier…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.vendor} onChange={(e) => set('vendor', e.target.value)}>
                        <option value="">Any vendor</option>
                        {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                    </Select>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                    </Select>
                    <Select value={f.state} onChange={(e) => set('state', e.target.value)}>
                        <option value="">Any state</option>
                        <option value="in_transit">In transit</option>
                        <option value="overdue">Overdue</option>
                        <option value="arrived">Arrived</option>
                        <option value="closed">Closed</option>
                    </Select>
                    <Select value={f.source} onChange={(e) => set('source', e.target.value)}>
                        <option value="">Any source</option>
                        <option value="purchase_order">Against a purchase order</option>
                        <option value="repair_order">Against a repair order</option>
                        <option value="standalone">Standalone</option>
                    </Select>
                </div>
            </Card>

            {shipments.length ? (
                <Card className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reference</TableHead>
                                <TableHead>Vendor</TableHead>
                                <TableHead>Source order</TableHead>
                                <TableHead>Latest status</TableHead>
                                <TableHead>Expected</TableHead>
                                <TableHead>State</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {shipments.map((s) => (
                                <TableRow key={s.id} className={s.is_overdue ? 'bg-destructive/[0.03]' : undefined}>
                                    <TableCell className="whitespace-nowrap font-mono text-sm">
                                        <Link href={route('shipments.show', s.id)} className="font-medium text-ncat-navy hover:text-primary">
                                            {s.reference}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-sm">{s.vendor ?? '—'}</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{s.source_label ?? 'Standalone'}</TableCell>
                                    <TableCell className="text-sm">
                                        {s.current_status ?? <span className="text-muted-foreground">No events yet</span>}
                                        {s.days_since_last_event !== null && (
                                            <span className="ml-2 text-xs tabular-nums text-muted-foreground">
                                                {s.days_since_last_event}d ago
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-sm tabular-nums">
                                        {s.expected_arrival_date ?? '—'}
                                    </TableCell>
                                    <TableCell><ShipmentState shipment={s} /></TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            ) : (
                <EmptyState
                    icon={Ship}
                    title="No shipments match these filters"
                    description="Raise a shipment when a vendor tells you goods are on the way, then record each step as you hear about it."
                    action={can.manage && (
                        <Button onClick={() => router.visit(route('shipments.create'))}>
                            <Plus className="size-4" /> New shipment
                        </Button>
                    )}
                />
            )}
        </AppLayout>
    );
}

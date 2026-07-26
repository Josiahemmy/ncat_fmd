import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { OrderStatus, OrdersTabs } from '@/Components/documents/orders';

export default function PurchaseOrdersIndex({ orders, filters, can }) {
    const [f, setF] = useState(filters);

    const apply = (next) => router.get(route('purchase-orders.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Purchase Orders · Orders" />
            <PageHeader
                eyebrow="Operations"
                title="Orders"
                description="Purchase orders to suppliers and repair orders to repair organisations."
                icon={FileText}
                actions={can.create && (
                    <Button onClick={() => router.visit(route('purchase-orders.create'))}>
                        <Plus className="size-4" /> New purchase order
                    </Button>
                )}
            />

            <OrdersTabs active="purchase" />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search reference / vendor…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        <option value="draft">Draft</option>
                        <option value="issued">Issued</option>
                        <option value="partially_received">Partially received</option>
                        <option value="received">Received</option>
                        <option value="closed">Closed</option>
                        <option value="cancelled">Cancelled</option>
                    </Select>
                </div>
            </Card>

            <Card className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Reference</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Vendor</TableHead>
                            <TableHead>Priority</TableHead>
                            <TableHead className="text-right">Lines</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {orders.map((o) => (
                            <TableRow key={o.id}>
                                <TableCell className="whitespace-nowrap font-mono text-sm">
                                    <Link href={route('purchase-orders.show', o.id)} className="font-medium text-ncat-navy hover:text-primary">
                                        {o.po_number ?? 'Draft'}
                                    </Link>
                                </TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{o.order_date}</TableCell>
                                <TableCell className="text-sm">{o.vendor ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{o.priority ?? '—'}</TableCell>
                                <TableCell className="text-right text-sm tabular-nums">{o.line_count}</TableCell>
                                <TableCell><OrderStatus status={o.status} /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!orders.length && (
                    <div className="p-10 text-center">
                        <FileText className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No purchase orders match these filters.</p>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

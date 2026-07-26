import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileText, Pencil, Send } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { OrderStatus, OrdersTabs } from '@/Components/documents/orders';

const num = (v) => (v === null || v === undefined ? '—' : String(Number(v)));

export default function PurchaseOrderShow({ order, can }) {
    const [cancelling, setCancelling] = useState(false);
    const cancel = useForm({ cancel_reason: '' });

    const post = (name) => router.post(route(name, order.id), {}, { preserveScroll: true });

    const submitCancel = (e) => {
        e.preventDefault();
        cancel.post(route('purchase-orders.cancel', order.id), {
            preserveScroll: true,
            onSuccess: () => setCancelling(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`${order.po_number ?? 'Draft purchase order'} · Orders`} />
            <PageHeader
                eyebrow="Purchase Order"
                title={order.po_number ?? 'Draft purchase order'}
                description={order.vendor?.name}
                icon={FileText}
                actions={(
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={route('purchase-orders.pdf', order.id)} target="_blank" rel="noreferrer">
                                <Download className="size-4" /> PDF
                            </a>
                        </Button>
                        {order.is_draft && can.edit && (
                            <>
                                <Button variant="outline" onClick={() => router.visit(route('purchase-orders.edit', order.id))}>
                                    <Pencil className="size-4" /> Edit
                                </Button>
                                <Button onClick={() => post('purchase-orders.issue')}>
                                    <Send className="size-4" /> Issue order
                                </Button>
                            </>
                        )}
                        {['received', 'partially_received', 'issued'].includes(order.status) && can.close && (
                            <Button variant="outline" onClick={() => post('purchase-orders.close')}>Close</Button>
                        )}
                        {!['closed', 'cancelled'].includes(order.status) && can.close && (
                            <Button variant="outline" onClick={() => setCancelling((v) => !v)}>Cancel order</Button>
                        )}
                    </div>
                )}
            />

            <OrdersTabs active="purchase" />

            {cancelling && (
                <Card className="mb-6 p-5">
                    <form onSubmit={submitCancel} className="space-y-3">
                        <label htmlFor="cancel_reason" className="text-sm font-medium">
                            Why is this order being cancelled?
                        </label>
                        <textarea
                            id="cancel_reason" rows={2} value={cancel.data.cancel_reason}
                            onChange={(e) => cancel.setData('cancel_reason', e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                        />
                        {cancel.errors.cancel_reason && (
                            <p className="text-sm text-destructive">{cancel.errors.cancel_reason}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            The reason is kept on the record. To correct an issued order, cancel it and raise a
                            new one so both stay in the trail.
                        </p>
                        <Button type="submit" disabled={cancel.processing}>Confirm cancellation</Button>
                    </form>
                </Card>
            )}

            {order.status === 'cancelled' && order.cancel_reason && (
                <p className="mb-6 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm">
                    <strong>Cancelled.</strong> {order.cancel_reason}
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                <Card className="space-y-4 p-5 text-sm">
                    <div><OrderStatus status={order.status} /></div>
                    <div>
                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Order date</span>
                        {order.order_date}
                    </div>
                    <div>
                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Vendor</span>
                        {order.vendor ? (
                            <Link href={route('vendors.show', order.vendor.id)} className="text-primary hover:underline">
                                {order.vendor.name}
                            </Link>
                        ) : '—'}
                        {order.vendor?.address_lines?.map((l, i) => (
                            <span key={i} className="block text-xs text-muted-foreground">{l}</span>
                        ))}
                    </div>
                    {order.aircraft_type_label && (
                        <div>
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Aircraft type</span>
                            {order.aircraft_type_label}
                        </div>
                    )}
                    {order.priority_label && (
                        <div>
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Priority</span>
                            <Badge variant="warning">{order.priority_label}</Badge>
                        </div>
                    )}
                    {order.issued_at && (
                        <div>
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Issued</span>
                            {order.issued_at}
                            {order.issued_by && <span className="block text-xs text-muted-foreground">by {order.issued_by}</span>}
                        </div>
                    )}
                    {order.remarks && (
                        <div className="border-t border-border pt-4">
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Remarks</span>
                            {order.remarks}
                        </div>
                    )}
                </Card>

                <div className="space-y-6">
                    <Card className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">S/No.</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Part number</TableHead>
                                    <TableHead className="text-right">Ordered</TableHead>
                                    <TableHead className="text-right">Received</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Time line</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {order.lines.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell className="text-sm">{l.line_no}</TableCell>
                                        <TableCell className="text-sm">{l.description ?? '—'}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{l.part_number ?? '—'}</TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">{num(l.qty_to_order)}</TableCell>
                                        <TableCell className="text-right text-sm tabular-nums">
                                            {num(l.qty_received)}
                                            {l.outstanding > 0 && l.qty_received > 0 && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {num(l.outstanding)} outstanding
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">{l.line_status ?? '—'}</TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">{l.timeline_label ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 font-display text-base font-semibold text-ncat-navy">Receiving</h2>
                        {order.srvs.length ? (
                            <ul className="space-y-2 text-sm">
                                {order.srvs.map((s) => (
                                    <li key={s.id} className="flex items-center gap-3">
                                        <Link href={route('receiving.show', s.id)} className="font-mono text-primary hover:underline">
                                            {s.srv_number}
                                        </Link>
                                        <span className="text-muted-foreground">{s.srv_date}</span>
                                        <Badge variant={s.status === 'posted' ? 'success' : 'neutral'}>
                                            {s.status === 'posted' ? 'Posted' : 'Draft'}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {order.is_receivable
                                    ? 'Nothing received yet. Raise an SRV and pick this order to book goods in against it.'
                                    : 'No store receipt vouchers reference this order.'}
                            </p>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

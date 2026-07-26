import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, PackageCheck, Pencil, Send, Truck, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { Select } from '@/Components/ui/Select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { OrderStatus, OrdersTabs } from '@/Components/documents/orders';

const SERIAL_LABELS = {
    in_store: 'In store',
    installed: 'Installed',
    at_repair: 'At repair',
    removed_unserviceable: 'Removed unserviceable',
    scrapped: 'Scrapped',
};

export default function RepairOrderShow({ order, can }) {
    const [cancelling, setCancelling] = useState(false);
    const [returning, setReturning] = useState(false);
    const cancel = useForm({ cancel_reason: '' });

    // Serviceable is the common outcome, so it is the default and the storekeeper
    // only has to change the lines that came back scrap.
    const returnForm = useForm({
        dispositions: order.lines.map((l) => ({ line_id: l.id, disposition: 'serviceable', note: '' })),
    });

    const post = (name) => router.post(route(name, order.id), {}, { preserveScroll: true });

    const patchDisposition = (lineId, changes) => returnForm.setData(
        'dispositions',
        returnForm.data.dispositions.map((d) => (d.line_id === lineId ? { ...d, ...changes } : d)),
    );

    const submitReturn = (e) => {
        e.preventDefault();
        returnForm.post(route('repair-orders.returned', order.id), {
            preserveScroll: true,
            onSuccess: () => setReturning(false),
        });
    };

    const submitCancel = (e) => {
        e.preventDefault();
        cancel.post(route('repair-orders.cancel', order.id), {
            preserveScroll: true,
            onSuccess: () => setCancelling(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`${order.ro_number ?? 'Draft repair order'} · Orders`} />
            <PageHeader
                eyebrow="Repair Order"
                title={order.ro_number ?? 'Draft repair order'}
                description={order.vendor?.name}
                icon={Wrench}
                actions={(
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={route('repair-orders.pdf', order.id)} target="_blank" rel="noreferrer">
                                <Download className="size-4" /> PDF
                            </a>
                        </Button>
                        {order.is_draft && can.edit && (
                            <>
                                <Button variant="outline" onClick={() => router.visit(route('repair-orders.edit', order.id))}>
                                    <Pencil className="size-4" /> Edit
                                </Button>
                                <Button onClick={() => post('repair-orders.issue')}>
                                    <Send className="size-4" /> Issue order
                                </Button>
                            </>
                        )}
                        {order.status === 'issued' && can.edit && (
                            <Button variant="outline" onClick={() => post('repair-orders.at-vendor')}>
                                <Truck className="size-4" /> Mark at vendor
                            </Button>
                        )}
                        {order.is_awaiting_return && can.edit && (
                            <Button onClick={() => setReturning((v) => !v)}>
                                <PackageCheck className="size-4" /> Book units back
                            </Button>
                        )}
                        {order.status === 'returned' && can.close && (
                            <Button variant="outline" onClick={() => post('repair-orders.close')}>Close</Button>
                        )}
                        {!['closed', 'cancelled'].includes(order.status) && can.close && (
                            <Button variant="outline" onClick={() => setCancelling((v) => !v)}>Cancel order</Button>
                        )}
                    </div>
                )}
            />

            <OrdersTabs active="repair" />

            {returning && (
                <Card className="mb-6 p-5">
                    <h2 className="mb-1 font-display text-base font-semibold text-ncat-navy">Book the units back in</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        Every line needs a decision. A serviceable unit is received into Quarantine and has to be
                        certified before it can be issued again. A scrapped unit is written off and cannot be reused.
                    </p>

                    <form onSubmit={submitReturn} className="space-y-4">
                        {returnForm.errors.dispositions && (
                            <p className="text-sm text-destructive">{returnForm.errors.dispositions}</p>
                        )}

                        {order.lines.map((l, i) => {
                            const d = returnForm.data.dispositions.find((x) => x.line_id === l.id);

                            return (
                                <div key={l.id} className="grid gap-3 rounded-lg border border-border p-4 sm:grid-cols-[1fr_auto]">
                                    <div>
                                        <span className="font-display text-sm font-bold text-ncat-navy">
                                            Line {l.line_no}: {l.description ?? l.part_number}
                                        </span>
                                        {l.serial_no && (
                                            <span className="block font-mono text-xs text-muted-foreground">
                                                {l.serial_no}
                                            </span>
                                        )}
                                        <input
                                            type="text" value={d?.note ?? ''} placeholder="Note (optional)"
                                            onChange={(e) => patchDisposition(l.id, { note: e.target.value })}
                                            className="mt-2 w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                            aria-label={`Note for line ${l.line_no}`}
                                        />
                                    </div>
                                    <Select
                                        value={d?.disposition ?? 'serviceable'}
                                        aria-label={`Disposition for line ${l.line_no}`}
                                        onChange={(e) => patchDisposition(l.id, { disposition: e.target.value })}
                                        className="sm:w-48"
                                    >
                                        <option value="serviceable">Serviceable</option>
                                        <option value="scrapped">Scrapped</option>
                                    </Select>
                                </div>
                            );
                        })}

                        <Button type="submit" disabled={returnForm.processing}>Confirm return</Button>
                    </form>
                </Card>
            )}

            {cancelling && (
                <Card className="mb-6 p-5">
                    <form onSubmit={submitCancel} className="space-y-3">
                        <label htmlFor="ro_cancel_reason" className="text-sm font-medium">
                            Why is this order being cancelled?
                        </label>
                        <textarea
                            id="ro_cancel_reason" rows={2} value={cancel.data.cancel_reason}
                            onChange={(e) => cancel.setData('cancel_reason', e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                        />
                        {cancel.errors.cancel_reason && (
                            <p className="text-sm text-destructive">{cancel.errors.cancel_reason}</p>
                        )}
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
                        <span className="block text-xs uppercase tracking-wide text-muted-foreground">Repair organisation</span>
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
                    {order.returned_at && (
                        <div>
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Returned</span>
                            {order.returned_at}
                        </div>
                    )}
                    {order.remarks && (
                        <div className="border-t border-border pt-4">
                            <span className="block text-xs uppercase tracking-wide text-muted-foreground">Remarks</span>
                            {order.remarks}
                        </div>
                    )}
                </Card>

                <Card className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-14">S/N.</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Part number</TableHead>
                                <TableHead>Serial no.</TableHead>
                                <TableHead className="text-right">Qty</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Outcome</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {order.lines.map((l) => (
                                <TableRow key={l.id}>
                                    <TableCell className="text-sm">{l.line_no}</TableCell>
                                    <TableCell className="text-sm">{l.description ?? '—'}</TableCell>
                                    <TableCell className="whitespace-nowrap font-mono text-sm">{l.part_number ?? '—'}</TableCell>
                                    <TableCell className="whitespace-nowrap font-mono text-sm">
                                        {l.serial_no ?? '—'}
                                        {l.serial_status && (
                                            <span className="block font-sans text-xs text-muted-foreground">
                                                {SERIAL_LABELS[l.serial_status] ?? l.serial_status}
                                            </span>
                                        )}
                                        {l.requisition && (
                                            <Link
                                                href={route('requisitions.show', l.requisition.id)}
                                                className="block font-sans text-xs text-primary hover:underline"
                                            >
                                                From requisition {l.requisition.requisition_no}
                                            </Link>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right text-sm tabular-nums">{Number(l.qty)}</TableCell>
                                    <TableCell className="text-sm">{l.action ?? '—'}</TableCell>
                                    <TableCell>
                                        {l.disposition ? (
                                            <>
                                                <Badge variant={l.disposition === 'serviceable' ? 'success' : 'error'}>
                                                    {l.disposition === 'serviceable' ? 'Serviceable' : 'Scrapped'}
                                                </Badge>
                                                {l.disposition_note && (
                                                    <span className="block text-xs text-muted-foreground">{l.disposition_note}</span>
                                                )}
                                            </>
                                        ) : <span className="text-sm text-muted-foreground">—</span>}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </AppLayout>
    );
}

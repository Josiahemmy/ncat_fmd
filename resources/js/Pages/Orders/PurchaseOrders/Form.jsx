import { Head, useForm } from '@inertiajs/react';
import { FileText, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { OrdersTabs, PriorityPicker } from '@/Components/documents/orders';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const blankLine = {
    id: null, description: '', part_id: '', part_number: '',
    qty_to_order: '', line_status: 'NEW', timeline_month: '', timeline_year: '',
};

/**
 * Create and edit share this form: a purchase order is only editable while it
 * is a draft, so there is no meaningful difference between the two screens
 * beyond the verb and the target route.
 */
export default function PurchaseOrderForm({ order, vendors, parts, aircraftTypes, nextSerial, mode }) {
    const editing = mode === 'edit';

    const form = useForm({
        order_date: order?.order_date ?? new Date().toISOString().slice(0, 10),
        vendor_id: order?.vendor_id ?? '',
        aircraft_type_label: order?.aircraft_type_label ?? '',
        priority: order?.priority ?? null,
        remarks: order?.remarks ?? '',
        lines: order?.lines?.length
            ? order.lines.map((l) => ({
                id: l.id, description: l.description ?? '', part_id: l.part_id ?? '',
                part_number: l.part_number ?? '', qty_to_order: l.qty_to_order,
                line_status: l.line_status ?? '', timeline_month: l.timeline_month ?? '',
                timeline_year: l.timeline_year ?? '',
            }))
            : [{ ...blankLine }],
    });

    const rows = form.data.lines;
    const write = (next) => form.setData('lines', next);
    const patch = (i, changes) => write(rows.map((r, idx) => (idx === i ? { ...r, ...changes } : r)));

    // Picking a catalogue part fills the printed part number; typing over it
    // afterwards is allowed, because orders often name a part the catalogue
    // does not carry yet.
    const pickPart = (i, partId) => {
        const part = parts.find((p) => String(p.id) === String(partId));
        patch(i, {
            part_id: partId || '',
            part_number: part ? part.part_number : rows[i].part_number,
            description: part && !rows[i].description ? part.description : rows[i].description,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        editing
            ? form.put(route('purchase-orders.update', order.id))
            : form.post(route('purchase-orders.store'));
    };

    return (
        <AppLayout>
            <Head title={editing ? 'Edit purchase order' : 'New purchase order'} />
            <PageHeader
                eyebrow="Orders"
                title={editing ? `Edit ${order.po_number ?? 'draft purchase order'}` : 'New purchase order'}
                description={
                    editing
                        ? 'Drafts stay editable. Once issued, the order is frozen and corrections go through cancel and re-raise.'
                        : `Saved as a draft first. It takes the next reference in the series (serial ${nextSerial}) when you issue it.`
                }
                icon={FileText}
            />

            <OrdersTabs active="purchase" />

            <form onSubmit={submit} className="space-y-6">
                <Card className="p-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="order_date">Order date</Label>
                            <Input id="order_date" type="date" value={form.data.order_date}
                                onChange={(e) => form.setData('order_date', e.target.value)} />
                            <p className="text-xs text-muted-foreground">
                                The day and month of the reference come from this date.
                            </p>
                            {form.errors.order_date && <p className="text-sm text-destructive">{form.errors.order_date}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="vendor_id">Vendor</Label>
                            <Select id="vendor_id" value={form.data.vendor_id}
                                onChange={(e) => form.setData('vendor_id', e.target.value)}>
                                <option value="">Select a vendor…</option>
                                {vendors.map((v) => (
                                    <option key={v.id} value={v.id}>{v.name}{v.country ? ` · ${v.country}` : ''}</option>
                                ))}
                            </Select>
                            {form.errors.vendor_id && <p className="text-sm text-destructive">{form.errors.vendor_id}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="aircraft_type_label">Aircraft type</Label>
                            <Input id="aircraft_type_label" list="ac-types" value={form.data.aircraft_type_label}
                                placeholder="e.g. DIAMOND DA-40NG/DA-42NG"
                                onChange={(e) => form.setData('aircraft_type_label', e.target.value)} />
                            <datalist id="ac-types">
                                {aircraftTypes.map((t) => <option key={t} value={t} />)}
                            </datalist>
                            <p className="text-xs text-muted-foreground">Optional. Prints above the table.</p>
                        </div>
                    </div>

                    <div className="mt-5 border-t border-border pt-5">
                        <Label className="mb-3 block">Priority</Label>
                        <PriorityPicker value={form.data.priority} onChange={(v) => form.setData('priority', v)} />
                    </div>
                </Card>

                <Card className="p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-display text-base font-semibold text-ncat-navy">Lines</h2>
                        <Button type="button" variant="outline" size="sm"
                            onClick={() => write([...rows, { ...blankLine }])}>
                            <Plus className="size-4" /> Add line
                        </Button>
                    </div>

                    {typeof form.errors.lines === 'string' && (
                        <p className="mb-3 text-sm text-destructive">{form.errors.lines}</p>
                    )}

                    <div className="space-y-4">
                        {rows.map((line, i) => (
                            <div key={line.id ?? `new-${i}`} className="rounded-lg border border-border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="font-display text-sm font-bold text-ncat-navy">Line {i + 1}</span>
                                    <Button type="button" variant="ghost" size="icon"
                                        aria-label={`Remove line ${i + 1}`}
                                        disabled={rows.length === 1}
                                        onClick={() => write(rows.filter((_, idx) => idx !== i))}>
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="space-y-1.5 lg:col-span-2">
                                        <Label htmlFor={`desc-${i}`}>Description</Label>
                                        <Input id={`desc-${i}`} value={line.description}
                                            onChange={(e) => patch(i, { description: e.target.value })} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`part-${i}`}>Part from catalogue</Label>
                                        <Select id={`part-${i}`} value={line.part_id}
                                            onChange={(e) => pickPart(i, e.target.value)}>
                                            <option value="">Not in the catalogue</option>
                                            {parts.map((p) => (
                                                <option key={p.id} value={p.id}>{p.part_number}</option>
                                            ))}
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`pn-${i}`}>Part number (printed)</Label>
                                        <Input id={`pn-${i}`} value={line.part_number}
                                            onChange={(e) => patch(i, { part_number: e.target.value })} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`qty-${i}`}>Qty to order</Label>
                                        <Input id={`qty-${i}`} type="number" min="0" step="any" value={line.qty_to_order}
                                            onChange={(e) => patch(i, { qty_to_order: e.target.value })} />
                                        {form.errors[`lines.${i}.qty_to_order`] && (
                                            <p className="text-sm text-destructive">{form.errors[`lines.${i}.qty_to_order`]}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`st-${i}`}>Status</Label>
                                        <Input id={`st-${i}`} list="line-statuses" value={line.line_status}
                                            onChange={(e) => patch(i, { line_status: e.target.value })} />
                                        <datalist id="line-statuses">
                                            <option value="NEW" />
                                            <option value="OVERHAULED" />
                                            <option value="SERVICEABLE" />
                                        </datalist>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`tm-${i}`}>Timeline month</Label>
                                        <Select id={`tm-${i}`} value={line.timeline_month}
                                            onChange={(e) => patch(i, { timeline_month: e.target.value })}>
                                            <option value="">—</option>
                                            {MONTHS.map((m, idx) => (
                                                <option key={m} value={idx + 1}>{m}</option>
                                            ))}
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ty-${i}`}>Timeline year</Label>
                                        <Input id={`ty-${i}`} type="number" min="2000" max="2100" value={line.timeline_year}
                                            onChange={(e) => patch(i, { timeline_year: e.target.value })} />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card className="p-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="remarks">Internal remarks</Label>
                        <textarea id="remarks" rows={3} value={form.data.remarks}
                            onChange={(e) => form.setData('remarks', e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                        <p className="text-xs text-muted-foreground">Not printed on the order.</p>
                    </div>
                </Card>

                <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Save draft'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { OrdersTabs, PriorityPicker } from '@/Components/documents/orders';

const blankLine = {
    id: null, description: '', part_id: '', part_number: '',
    part_serial_id: '', serial_no: '', qty: 1, action: 'OVERHAUL',
};

export default function RepairOrderForm({ order, vendors, parts, aircraftTypes, serials, nextSerial, mode }) {
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
                part_number: l.part_number ?? '', part_serial_id: l.part_serial_id ?? '',
                serial_no: l.serial_no ?? '', qty: l.qty, action: l.action ?? '',
            }))
            : [{ ...blankLine }],
    });

    const rows = form.data.lines;
    const write = (next) => form.setData('lines', next);
    const patch = (i, changes) => write(rows.map((r, idx) => (idx === i ? { ...r, ...changes } : r)));

    /**
     * Picking a tracked serial fills the part, part number, and printed serial
     * from the record, and pins the quantity to one: a tracked serial is a
     * single physical unit. Clearing it drops back to free text, which the
     * paper allows for units that were never in the catalogue.
     */
    const pickSerial = (i, serialId) => {
        const serial = serials.find((s) => String(s.id) === String(serialId));

        if (!serial) {
            patch(i, { part_serial_id: '', serial_no: '' });

            return;
        }

        patch(i, {
            part_serial_id: serialId,
            serial_no: serial.serial_number,
            part_id: serial.part_id ?? '',
            part_number: serial.part_number ?? rows[i].part_number,
            description: rows[i].description || serial.description || '',
            qty: 1,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        editing
            ? form.put(route('repair-orders.update', order.id))
            : form.post(route('repair-orders.store'));
    };

    return (
        <AppLayout>
            <Head title={editing ? 'Edit repair order' : 'New repair order'} />
            <PageHeader
                eyebrow="Orders"
                title={editing ? `Edit ${order.ro_number ?? 'draft repair order'}` : 'New repair order'}
                description={
                    editing
                        ? 'Drafts stay editable. Issuing sends the listed serials to repair.'
                        : `Saved as a draft first. It takes the next reference in the series (serial ${nextSerial}) when you issue it, and its serials move to at-repair at the same moment.`
                }
                icon={Wrench}
            />

            <OrdersTabs active="repair" />

            <form onSubmit={submit} className="space-y-6">
                <Card className="p-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="order_date">Order date</Label>
                            <Input id="order_date" type="date" value={form.data.order_date}
                                onChange={(e) => form.setData('order_date', e.target.value)} />
                            <p className="text-xs text-muted-foreground">The month of the reference comes from this date.</p>
                            {form.errors.order_date && <p className="text-sm text-destructive">{form.errors.order_date}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="vendor_id">Repair organisation</Label>
                            <Select id="vendor_id" value={form.data.vendor_id}
                                onChange={(e) => form.setData('vendor_id', e.target.value)}>
                                <option value="">Select a repair organisation…</option>
                                {vendors.map((v) => (
                                    <option key={v.id} value={v.id}>{v.name}{v.country ? ` · ${v.country}` : ''}</option>
                                ))}
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Only vendors typed as a repair organisation are listed.
                            </p>
                            {form.errors.vendor_id && <p className="text-sm text-destructive">{form.errors.vendor_id}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="aircraft_type_label">Aircraft type</Label>
                            <Input id="aircraft_type_label" list="ro-ac-types" value={form.data.aircraft_type_label}
                                placeholder="e.g. DIAMOND DA40G"
                                onChange={(e) => form.setData('aircraft_type_label', e.target.value)} />
                            <datalist id="ro-ac-types">
                                {aircraftTypes.map((t) => <option key={t} value={t} />)}
                            </datalist>
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
                                    <div className="flex items-center gap-2">
                                        {line.part_serial_id && <Badge variant="info">Tracked serial</Badge>}
                                        <Button type="button" variant="ghost" size="icon"
                                            aria-label={`Remove line ${i + 1}`}
                                            disabled={rows.length === 1}
                                            onClick={() => write(rows.filter((_, idx) => idx !== i))}>
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="space-y-1.5 lg:col-span-2">
                                        <Label htmlFor={`ro-desc-${i}`}>Description</Label>
                                        <Input id={`ro-desc-${i}`} value={line.description}
                                            onChange={(e) => patch(i, { description: e.target.value })} />
                                    </div>

                                    <div className="space-y-1.5 lg:col-span-2">
                                        <Label htmlFor={`ro-serial-${i}`}>Serial from stock</Label>
                                        <Select id={`ro-serial-${i}`} value={line.part_serial_id}
                                            onChange={(e) => pickSerial(i, e.target.value)}>
                                            <option value="">Not tracked (type the serial below)</option>
                                            {serials.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.serial_number} · {s.part_number} · {s.status.replace(/_/g, ' ')}
                                                </option>
                                            ))}
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            Units removed as unserviceable or already at repair. Picking one moves it to
                                            at-repair when the order is issued and back-links its requisition.
                                        </p>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ro-part-${i}`}>Part from catalogue</Label>
                                        <Select id={`ro-part-${i}`} value={line.part_id}
                                            disabled={Boolean(line.part_serial_id)}
                                            onChange={(e) => {
                                                const part = parts.find((p) => String(p.id) === String(e.target.value));
                                                patch(i, {
                                                    part_id: e.target.value || '',
                                                    part_number: part ? part.part_number : line.part_number,
                                                });
                                            }}>
                                            <option value="">Not in the catalogue</option>
                                            {parts.map((p) => <option key={p.id} value={p.id}>{p.part_number}</option>)}
                                        </Select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ro-pn-${i}`}>Part number (printed)</Label>
                                        <Input id={`ro-pn-${i}`} value={line.part_number}
                                            onChange={(e) => patch(i, { part_number: e.target.value })} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ro-sn-${i}`}>Serial no. (printed)</Label>
                                        <Input id={`ro-sn-${i}`} value={line.serial_no}
                                            readOnly={Boolean(line.part_serial_id)}
                                            className={line.part_serial_id ? 'bg-muted text-muted-foreground' : undefined}
                                            onChange={(e) => patch(i, { serial_no: e.target.value })} />
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ro-qty-${i}`}>Qty</Label>
                                        <Input id={`ro-qty-${i}`} type="number" min="0" step="any" value={line.qty}
                                            readOnly={Boolean(line.part_serial_id)}
                                            className={line.part_serial_id ? 'bg-muted text-muted-foreground' : undefined}
                                            onChange={(e) => patch(i, { qty: e.target.value })} />
                                        {form.errors[`lines.${i}.qty`] && (
                                            <p className="text-sm text-destructive">{form.errors[`lines.${i}.qty`]}</p>
                                        )}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor={`ro-act-${i}`}>Action</Label>
                                        <Input id={`ro-act-${i}`} list="ro-actions" value={line.action}
                                            onChange={(e) => patch(i, { action: e.target.value })} />
                                        <datalist id="ro-actions">
                                            <option value="OVERHAUL" />
                                            <option value="REPAIR" />
                                            <option value="TEST" />
                                            <option value="INSPECT" />
                                        </datalist>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <Card className="p-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="ro-remarks">Internal remarks</Label>
                        <textarea id="ro-remarks" rows={3} value={form.data.remarks}
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

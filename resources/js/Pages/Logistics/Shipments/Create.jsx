import { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Ship } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { DatePicker } from '@/Components/ui/DatePicker';

const FREE_TEXT = '__free_text__';

function Row({ label, htmlFor, error, hint, children }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function ShipmentCreate({ vendors, purchaseOrders, repairOrders, statuses, arrivalStatus }) {
    const [statusMode, setStatusMode] = useState(statuses[0] ?? FREE_TEXT);

    const form = useForm({
        vendor_id: '',
        source_kind: '',
        source_id: '',
        description: '',
        carrier: '',
        awb_reference: '',
        expected_arrival_date: '',
        status: statuses[0] ?? '',
        event_date: new Date().toISOString().slice(0, 10),
        note: '',
        is_arrival: false,
    });

    // Orders are filtered to the chosen vendor: a consignment from one supplier
    // is not going to be fulfilling another supplier's order.
    const sourceOptions = useMemo(() => {
        const list = form.data.source_kind === 'purchase_order' ? purchaseOrders
            : form.data.source_kind === 'repair_order' ? repairOrders : [];

        return form.data.vendor_id
            ? list.filter((o) => String(o.vendor_id) === String(form.data.vendor_id))
            : list;
    }, [form.data.source_kind, form.data.vendor_id, purchaseOrders, repairOrders]);

    const pickStatus = (value) => {
        setStatusMode(value);
        form.setData((d) => ({
            ...d,
            status: value === FREE_TEXT ? '' : value,
            is_arrival: value === arrivalStatus,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('shipments.store'));
    };

    return (
        <AppLayout>
            <Head title="New shipment · Shipping" />
            <PageHeader
                eyebrow="Shipping"
                title="New shipment"
                description="Raise this when a vendor tells you goods are on the way. The reference is assigned now."
                icon={Ship}
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                <Card className="space-y-4 p-5">
                    <h2 className="font-display text-sm font-semibold text-ncat-navy">Consignment</h2>

                    <Row label="Vendor" htmlFor="vendor" error={form.errors.vendor_id}>
                        <Select id="vendor" value={form.data.vendor_id}
                            onChange={(e) => form.setData((d) => ({ ...d, vendor_id: e.target.value, source_id: '' }))}>
                            <option value="">Select a vendor</option>
                            {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                        </Select>
                    </Row>

                    <Row label="Against an order" htmlFor="source-kind"
                        hint="Leave as standalone if these goods are not covered by a purchase or repair order.">
                        <Select id="source-kind" value={form.data.source_kind}
                            onChange={(e) => form.setData((d) => ({ ...d, source_kind: e.target.value, source_id: '' }))}>
                            <option value="">Standalone</option>
                            <option value="purchase_order">Purchase order</option>
                            <option value="repair_order">Repair order</option>
                        </Select>
                    </Row>

                    {form.data.source_kind && (
                        <Row label="Order" htmlFor="source-id" error={form.errors.source_id}>
                            <Select id="source-id" value={form.data.source_id}
                                onChange={(e) => form.setData('source_id', e.target.value)}>
                                <option value="">Select an order</option>
                                {sourceOptions.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
                            </Select>
                        </Row>
                    )}

                    <Row label="Description" htmlFor="description" error={form.errors.description}>
                        <textarea
                            id="description" rows={3} value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            placeholder="What is in the consignment"
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                        />
                    </Row>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Row label="Carrier" htmlFor="carrier" error={form.errors.carrier}>
                            <Input id="carrier" value={form.data.carrier}
                                onChange={(e) => form.setData('carrier', e.target.value)} />
                        </Row>
                        <Row label="AWB / tracking" htmlFor="awb" error={form.errors.awb_reference}>
                            <Input id="awb" value={form.data.awb_reference}
                                onChange={(e) => form.setData('awb_reference', e.target.value)} />
                        </Row>
                    </div>

                    <Row label="Expected arrival" htmlFor="expected" error={form.errors.expected_arrival_date}
                        hint="Used for the overdue alert. Leave blank if the vendor has not committed to a date.">
                        <DatePicker id="expected" value={form.data.expected_arrival_date}
                            onChange={(e) => form.setData('expected_arrival_date', e.target.value)} />
                    </Row>
                </Card>

                <Card className="space-y-4 p-5">
                    <h2 className="font-display text-sm font-semibold text-ncat-navy">Opening event</h2>
                    <p className="text-xs text-muted-foreground">
                        Where the consignment is right now. This becomes the first entry on the timeline
                        and cannot be edited later.
                    </p>

                    <Row label="Status" htmlFor="status" error={form.errors.status}>
                        <Select id="status" value={statusMode} onChange={(e) => pickStatus(e.target.value)}>
                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                            <option value={FREE_TEXT}>Something else…</option>
                        </Select>
                        {statusMode === FREE_TEXT && (
                            <Input className="mt-2" placeholder="Describe where the consignment is"
                                value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)} />
                        )}
                    </Row>

                    <Row label="Date" htmlFor="event-date" error={form.errors.event_date}>
                        <DatePicker id="event-date" value={form.data.event_date}
                            onChange={(e) => form.setData('event_date', e.target.value)} />
                    </Row>

                    <Row label="Note" htmlFor="note">
                        <textarea
                            id="note" rows={3} value={form.data.note}
                            onChange={(e) => form.setData('note', e.target.value)}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                        />
                    </Row>

                    <label className="flex cursor-pointer items-start gap-2.5">
                        <input
                            type="checkbox" checked={form.data.is_arrival}
                            onChange={(e) => form.setData('is_arrival', e.target.checked)}
                            className="mt-0.5 size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                        />
                        <span className="text-sm">
                            The consignment is already at NCAT
                            <span className="block text-xs text-muted-foreground">
                                Tick this when you are recording a shipment that has already landed.
                            </span>
                        </span>
                    </label>

                    <Button type="submit" disabled={form.processing} className="w-full">
                        Create shipment
                    </Button>
                </Card>
            </form>
        </AppLayout>
    );
}

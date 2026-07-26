import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle, CheckCircle2, Lock, PackagePlus, Plane, Ship, Truck,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { DatePicker } from '@/Components/ui/DatePicker';
import ShipmentTimeline from '@/Components/logistics/ShipmentTimeline';

const FREE_TEXT = '__free_text__';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm text-ncat-navy">{children ?? '—'}</dd>
        </div>
    );
}

/**
 * The inline add-event form. The status picker suggests the admin list and
 * offers "Something else" for anything it does not cover, because a
 * consignment can stall somewhere nobody wrote down in advance.
 */
function AddEventForm({ shipment, statuses, arrivalStatus }) {
    const [mode, setMode] = useState(statuses[0] ?? FREE_TEXT);

    const form = useForm({
        status: statuses[0] ?? '',
        event_date: new Date().toISOString().slice(0, 10),
        note: '',
        is_arrival: false,
    });

    const pick = (value) => {
        setMode(value);
        if (value !== FREE_TEXT) {
            form.setData((d) => ({
                ...d,
                status: value,
                // Pre-tick for the configured arrival label. It stays editable:
                // the flag on the event is what closes the shipment, not the text.
                is_arrival: value === arrivalStatus,
            }));
        } else {
            form.setData((d) => ({ ...d, status: '' }));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('shipments.events.store', shipment.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('note');
                form.setData((d) => ({ ...d, is_arrival: false }));
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-1.5">
                <Label htmlFor="ev-status">Status</Label>
                <Select id="ev-status" value={mode} onChange={(e) => pick(e.target.value)}>
                    {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                    <option value={FREE_TEXT}>Something else…</option>
                </Select>
                {mode === FREE_TEXT && (
                    <Input
                        className="mt-2"
                        placeholder="Describe where the consignment is"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    />
                )}
                {form.errors.status && <p className="text-sm text-destructive">{form.errors.status}</p>}
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="ev-date">Date</Label>
                <DatePicker id="ev-date" value={form.data.event_date}
                    onChange={(e) => form.setData('event_date', e.target.value)} />
                {form.errors.event_date && <p className="text-sm text-destructive">{form.errors.event_date}</p>}
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="ev-note">Note</Label>
                <textarea
                    id="ev-note" rows={3} value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    placeholder="Anything worth recording. Correct an earlier entry here rather than editing it."
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
            </div>

            <label className="flex cursor-pointer items-start gap-2.5">
                <input
                    type="checkbox" checked={form.data.is_arrival}
                    onChange={(e) => form.setData('is_arrival', e.target.checked)}
                    className="mt-0.5 size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                />
                <span className="text-sm">
                    This is the consignment arriving at NCAT
                    <span className="block text-xs text-muted-foreground">
                        Stops it counting as overdue and opens the SRV handoff.
                    </span>
                </span>
            </label>

            <Button type="submit" disabled={form.processing} className="w-full">
                Record event
            </Button>
        </form>
    );
}

export default function ShipmentShow({ shipment, statuses, arrivalStatus, can }) {
    const close = useForm({});

    return (
        <AppLayout>
            <Head title={`${shipment.reference} · Shipping`} />
            <PageHeader
                eyebrow="Shipping"
                title={shipment.reference}
                description={shipment.description || shipment.vendor}
                icon={Ship}
                actions={(
                    <div className="flex flex-wrap gap-2">
                        {can.receive && shipment.has_arrived && (
                            <Button onClick={() => router.visit(route('shipments.srv', shipment.id))}>
                                <PackagePlus className="size-4" /> Create SRV from this shipment
                            </Button>
                        )}
                        {can.manage && !shipment.is_closed && (
                            <Button
                                variant="outline"
                                disabled={close.processing}
                                onClick={() => close.post(route('shipments.close', shipment.id), { preserveScroll: true })}
                            >
                                <Lock className="size-4" /> Close shipment
                            </Button>
                        )}
                    </div>
                )}
            />

            {shipment.is_overdue && (
                <div className="mb-6 flex items-start gap-2.5 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-ncat-navy">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <span>
                        Expected {shipment.expected_arrival_date}, which was {shipment.days_overdue}{' '}
                        {shipment.days_overdue === 1 ? 'day' : 'days'} ago. Chase the vendor or the carrier,
                        then record what they tell you as an event.
                    </span>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_1fr]">
                <div className="space-y-6">
                    <Card className="p-5">
                        <dl className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                {shipment.is_closed
                                    ? <Badge variant="neutral">Closed</Badge>
                                    : shipment.has_arrived
                                        ? <Badge variant="success"><CheckCircle2 className="size-3" /> Arrived</Badge>
                                        : shipment.is_overdue
                                            ? <Badge variant="destructive">Overdue by {shipment.days_overdue}d</Badge>
                                            : <Badge variant="info"><Truck className="size-3" /> In transit</Badge>}
                            </div>

                            <Field label="Vendor">{shipment.vendor}</Field>
                            <Field label="Source order">
                                {shipment.source_label
                                    ? (shipment.source_kind === 'purchase_order'
                                        ? <Link href={route('purchase-orders.show', shipment.source_id)} className="text-primary hover:underline">{shipment.source_label}</Link>
                                        : <Link href={route('repair-orders.show', shipment.source_id)} className="text-primary hover:underline">{shipment.source_label}</Link>)
                                    : 'Standalone'}
                            </Field>
                            <Field label="Carrier">{shipment.carrier}</Field>
                            <Field label="AWB / tracking">{shipment.awb_reference}</Field>
                            <Field label="Expected arrival">{shipment.expected_arrival_date}</Field>
                            <Field label="Raised by">
                                {shipment.created_by} <span className="text-muted-foreground">on {shipment.created_at}</span>
                            </Field>
                        </dl>
                    </Card>

                    {shipment.srvs.length > 0 && (
                        <Card className="p-5">
                            <h2 className="mb-3 font-display text-sm font-semibold text-ncat-navy">
                                Receipt vouchers
                            </h2>
                            <ul className="space-y-2 text-sm">
                                {shipment.srvs.map((v) => (
                                    <li key={v.id} className="flex items-center justify-between gap-3">
                                        <Link href={route('receiving.show', v.id)} className="font-mono text-primary hover:underline">
                                            {v.srv_number}
                                        </Link>
                                        <span className="text-xs text-muted-foreground">{v.srv_date} · {v.status}</span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    {can.manage && (
                        <Card className="p-5">
                            <h2 className="mb-1 font-display text-sm font-semibold text-ncat-navy">
                                Record an event
                            </h2>
                            <p className="mb-4 text-xs text-muted-foreground">
                                Entries cannot be edited or removed once recorded. To correct one, add another
                                saying what changed.
                            </p>
                            <AddEventForm shipment={shipment} statuses={statuses} arrivalStatus={arrivalStatus} />
                        </Card>
                    )}
                </div>

                <Card className="p-6 sm:p-8">
                    <div className="mb-6 flex items-baseline justify-between gap-4">
                        <h2 className="font-display text-lg font-semibold text-ncat-navy">Timeline</h2>
                        <p className="text-xs text-muted-foreground">
                            {shipment.events.length} {shipment.events.length === 1 ? 'entry' : 'entries'}, newest first
                        </p>
                    </div>
                    <ShipmentTimeline events={shipment.events} shipment={shipment} />

                    {shipment.has_arrived && can.receive && (
                        <div className="mt-8 flex flex-col gap-3 rounded-md border border-ncat-success/30 bg-ncat-success/5 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="flex items-start gap-2.5 text-sm text-ncat-navy">
                                <Plane className="mt-0.5 size-4 shrink-0 text-ncat-success" />
                                The goods are here. Raise the receipt voucher and they go into Quarantine
                                for certification as usual.
                            </p>
                            <Button onClick={() => router.visit(route('shipments.srv', shipment.id))}>
                                <PackagePlus className="size-4" /> Create SRV
                            </Button>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

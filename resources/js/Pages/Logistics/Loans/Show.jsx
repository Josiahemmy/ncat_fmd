import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeftRight, Plane, ShieldAlert, Undo2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { DatePicker } from '@/Components/ui/DatePicker';
import { LoanStatus } from '@/Components/logistics/LoanStatus';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm text-ncat-navy">{children ?? '—'}</dd>
        </div>
    );
}

function ReturnForm({ loan }) {
    const form = useForm({
        returned_at: new Date().toISOString().slice(0, 10),
        return_condition: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('loans.return', loan.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="space-y-1.5">
                <Label htmlFor="r-date">Returned on</Label>
                <DatePicker id="r-date" value={form.data.returned_at}
                    onChange={(e) => form.setData('returned_at', e.target.value)} />
                {form.errors.returned_at && <p className="text-sm text-destructive">{form.errors.returned_at}</p>}
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="r-condition">Condition on return</Label>
                <textarea
                    id="r-condition" rows={3} value={form.data.return_condition}
                    onChange={(e) => form.setData('return_condition', e.target.value)}
                    placeholder="Serviceable, damaged, incomplete…"
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
            </div>
            <p className="text-xs text-muted-foreground">
                {loan.direction === 'out'
                    ? `The units post back into ${loan.from_store}, the store they left.`
                    : 'The borrowed stock leaves the ledger and returns to its owner.'}
            </p>
            <Button type="submit" disabled={form.processing} className="w-full">
                <Undo2 className="size-4" /> Record return
            </Button>
        </form>
    );
}

function WriteOffForm({ loan }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ write_off_reason: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('loans.write-off', loan.id), { preserveScroll: true });
    };

    if (!open) {
        return (
            <Button variant="outline" className="w-full" onClick={() => setOpen(true)}>
                <AlertTriangle className="size-4 text-destructive" /> Write off
            </Button>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <p className="text-sm text-ncat-graphite">
                Writing off posts an adjustment out of the ledger. The stock stops being NCAT's,
                and your reason is stored on the movement.
            </p>
            <div className="space-y-1.5">
                <Label htmlFor="w-reason">Reason</Label>
                <textarea
                    id="w-reason" rows={3} value={form.data.write_off_reason}
                    onChange={(e) => form.setData('write_off_reason', e.target.value)}
                    placeholder="Why this unit is not coming back"
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                />
                {form.errors.write_off_reason && (
                    <p className="text-sm text-destructive">{form.errors.write_off_reason}</p>
                )}
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>Confirm write-off</Button>
                <Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
            </div>
        </form>
    );
}

function InstallForm({ loan, aircraft }) {
    const form = useForm({ installed_aircraft_id: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('loans.install', loan.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="space-y-1.5">
                <Label htmlFor="a-id">Fitted to</Label>
                <Select id="a-id" value={form.data.installed_aircraft_id}
                    onChange={(e) => form.setData('installed_aircraft_id', e.target.value)}>
                    <option value="">Select an aircraft</option>
                    {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                </Select>
                {form.errors.installed_aircraft_id && (
                    <p className="text-sm text-destructive">{form.errors.installed_aircraft_id}</p>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                Allowed, and marked: the parts-on-aircraft view will show it as loaned property.
            </p>
            <Button type="submit" disabled={form.processing} className="w-full">
                <Plane className="size-4" /> Record as fitted
            </Button>
        </form>
    );
}

export default function LoanShow({ loan, aircraft, can }) {
    const outbound = loan.direction === 'out';
    const open = loan.status === 'on_loan';

    return (
        <AppLayout>
            <Head title={`${loan.counterparty} · Loaners`} />
            <PageHeader
                eyebrow={outbound ? 'Loaners · Outbound' : 'Loaners · Inbound'}
                title={loan.item_label}
                description={outbound
                    ? `Lent to ${loan.counterparty}`
                    : `Borrowed from ${loan.counterparty}`}
                icon={ArrowLeftRight}
            />

            {loan.is_overdue && (
                <div className="mb-6 flex items-start gap-2.5 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-ncat-navy">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0 text-destructive" />
                    <span>
                        Due back {loan.due_date}, {loan.days_overdue}{' '}
                        {loan.days_overdue === 1 ? 'day' : 'days'} ago.
                        {outbound
                            ? ' Chase the borrower, or write it off if it is not coming back.'
                            : ' The owner is waiting on this.'}
                    </span>
                </div>
            )}

            {!outbound && (
                <p className="mb-6 flex items-start gap-2.5 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-ncat-graphite">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    Not NCAT property. Excluded from stock value, stock summary and reorder alerts.
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                <Card className="p-5">
                    <dl className="grid gap-5 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <LoanStatus loan={loan} />
                        </div>
                        <Field label={outbound ? 'Borrower' : 'Lender'}>{loan.counterparty}</Field>
                        <Field label="Contact">{loan.party_contact}</Field>
                        <Field label="Item">{loan.item_label}</Field>
                        <Field label="Serial">{loan.serial}</Field>
                        <Field label="Quantity">{loan.quantity}</Field>
                        {outbound
                            ? <Field label="Issued from">{loan.from_store}</Field>
                            : <Field label="Fitted to">{loan.installed_aircraft}</Field>}
                        <Field label={outbound ? 'Loaned on' : 'Received on'}>{loan.started_at}</Field>
                        <Field label="Due back">{loan.due_date}</Field>
                        {loan.returned_at && <Field label="Returned on">{loan.returned_at}</Field>}
                        {loan.return_condition && (
                            <div className="sm:col-span-2">
                                <Field label="Condition on return">{loan.return_condition}</Field>
                            </div>
                        )}
                        {loan.write_off_reason && (
                            <div className="sm:col-span-2">
                                <Field label="Write-off reason">
                                    {loan.write_off_reason}
                                    <span className="block text-xs text-muted-foreground">
                                        Recorded by {loan.written_off_by}
                                    </span>
                                </Field>
                            </div>
                        )}
                        {loan.notes && (
                            <div className="sm:col-span-2">
                                <Field label="Notes">{loan.notes}</Field>
                            </div>
                        )}
                        <Field label="Recorded by">{loan.created_by}</Field>
                    </dl>
                </Card>

                <div className="space-y-6">
                    {open && can.manage && (
                        <Card className="p-5">
                            <h2 className="mb-4 font-display text-sm font-semibold text-ncat-navy">Record return</h2>
                            <ReturnForm loan={loan} />
                        </Card>
                    )}

                    {open && can.manage && !outbound && !loan.installed_aircraft && (
                        <Card className="p-5">
                            <h2 className="mb-4 font-display text-sm font-semibold text-ncat-navy">Fit to an aircraft</h2>
                            <InstallForm loan={loan} aircraft={aircraft} />
                        </Card>
                    )}

                    {open && outbound && can.write_off && (
                        <Card className="p-5">
                            <h2 className="mb-4 font-display text-sm font-semibold text-ncat-navy">Not coming back</h2>
                            <WriteOffForm loan={loan} />
                        </Card>
                    )}

                    {!open && (
                        <Card className="p-5">
                            <Badge variant={loan.status === 'returned' ? 'success' : 'neutral'}>
                                {loan.status === 'returned' ? 'Closed by return' : 'Closed by write-off'}
                            </Badge>
                            <p className="mt-3 text-sm text-muted-foreground">
                                This loan is closed. Any correction is a new record, not an edit to this one.
                            </p>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

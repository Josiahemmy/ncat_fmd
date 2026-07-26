import { useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeftRight, Plus, Search, ShieldAlert } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { EmptyState } from '@/Components/ui/EmptyState';
import { LoanStatus } from '@/Components/logistics/LoanStatus';
import { DatePicker } from '@/Components/ui/DatePicker';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';

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

/** Lending NCAT stock out. The store list is already limited to Bonded and Dope. */
function OutboundForm({ formProps, onDone }) {
    const form = useForm({
        vendor_id: '', party_name: '', party_contact: '',
        part_id: '', part_serial_id: '', quantity: '1', from_store_id: '',
        started_at: new Date().toISOString().slice(0, 10), due_date: '', notes: '',
    });

    const serials = useMemo(
        () => formProps.serials.filter((s) => String(s.part_id) === String(form.data.part_id)),
        [formProps.serials, form.data.part_id],
    );

    const submit = (e) => {
        e.preventDefault();
        form.post(route('loans.outbound.store'), { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <Row label="Borrower (a known vendor)" htmlFor="o-vendor" error={form.errors.vendor_id}>
                <Select id="o-vendor" value={form.data.vendor_id}
                    onChange={(e) => form.setData('vendor_id', e.target.value)}>
                    <option value="">Not a vendor</option>
                    {formProps.vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                </Select>
            </Row>
            <Row label="Or the organisation's name" htmlFor="o-party" error={form.errors.party_name}>
                <Input id="o-party" value={form.data.party_name}
                    onChange={(e) => form.setData('party_name', e.target.value)} />
            </Row>

            <Row label="Contact" htmlFor="o-contact" error={form.errors.party_contact}>
                <Input id="o-contact" value={form.data.party_contact}
                    onChange={(e) => form.setData('party_contact', e.target.value)} />
            </Row>
            <Row label="Issuing store" htmlFor="o-store" error={form.errors.from_store_id}
                hint="The return comes back here, so it is fixed at issue.">
                <Select id="o-store" value={form.data.from_store_id}
                    onChange={(e) => form.setData('from_store_id', e.target.value)}>
                    <option value="">Select a store</option>
                    {formProps.stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </Select>
            </Row>

            <Row label="Part" htmlFor="o-part" error={form.errors.part_id}>
                <Select id="o-part" value={form.data.part_id}
                    onChange={(e) => form.setData((d) => ({ ...d, part_id: e.target.value, part_serial_id: '' }))}>
                    <option value="">Select a part</option>
                    {formProps.parts.map((p) => (
                        <option key={p.id} value={p.id}>{p.part_number} - {p.description}</option>
                    ))}
                </Select>
            </Row>
            <Row label="Serial" htmlFor="o-serial" error={form.errors.part_serial_id}
                hint={serials.length ? 'Pick the exact unit leaving the store.' : 'No tracked serials in store for this part.'}>
                <Select id="o-serial" value={form.data.part_serial_id} disabled={!serials.length}
                    onChange={(e) => form.setData((d) => ({
                        ...d, part_serial_id: e.target.value, quantity: e.target.value ? '1' : d.quantity,
                    }))}>
                    <option value="">Not serialised</option>
                    {serials.map((s) => <option key={s.id} value={s.id}>{s.serial_number}</option>)}
                </Select>
            </Row>

            <Row label="Quantity" htmlFor="o-qty" error={form.errors.quantity}>
                <Input id="o-qty" type="number" step="0.01" min="0.01"
                    disabled={!!form.data.part_serial_id}
                    value={form.data.quantity}
                    onChange={(e) => form.setData('quantity', e.target.value)} />
            </Row>
            <div className="grid gap-4 sm:grid-cols-2">
                <Row label="Loaned on" htmlFor="o-start" error={form.errors.started_at}>
                    <DatePicker id="o-start" value={form.data.started_at}
                        onChange={(e) => form.setData('started_at', e.target.value)} />
                </Row>
                <Row label="Due back" htmlFor="o-due" error={form.errors.due_date}>
                    <DatePicker id="o-due" value={form.data.due_date}
                        onChange={(e) => form.setData('due_date', e.target.value)} />
                </Row>
            </div>

            <div className="sm:col-span-2">
                <Row label="Notes" htmlFor="o-notes">
                    <textarea id="o-notes" rows={2} value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                </Row>
            </div>

            <div className="sm:col-span-2">
                <Button type="submit" disabled={form.processing}>Record loan out</Button>
            </div>
        </form>
    );
}

/** Borrowing property in. The part link is optional: it may not be catalogued. */
function InboundForm({ formProps, onDone }) {
    const form = useForm({
        vendor_id: '', party_name: '', party_contact: '',
        part_id: '', item_description: '', serial_text: '', quantity: '1',
        started_at: new Date().toISOString().slice(0, 10), due_date: '', notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('loans.inbound.store'), { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <Row label="Lender (a known vendor)" htmlFor="i-vendor" error={form.errors.vendor_id}>
                <Select id="i-vendor" value={form.data.vendor_id}
                    onChange={(e) => form.setData('vendor_id', e.target.value)}>
                    <option value="">Not a vendor</option>
                    {formProps.vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                </Select>
            </Row>
            <Row label="Or the organisation's name" htmlFor="i-party" error={form.errors.party_name}>
                <Input id="i-party" value={form.data.party_name}
                    onChange={(e) => form.setData('party_name', e.target.value)} />
            </Row>

            <Row label="Contact" htmlFor="i-contact" error={form.errors.party_contact}>
                <Input id="i-contact" value={form.data.party_contact}
                    onChange={(e) => form.setData('party_contact', e.target.value)} />
            </Row>
            <Row label="Catalogued part" htmlFor="i-part" error={form.errors.part_id}
                hint="Link one if it exists. A borrowed item does not have to be in the catalogue.">
                <Select id="i-part" value={form.data.part_id}
                    onChange={(e) => form.setData('part_id', e.target.value)}>
                    <option value="">Not in the catalogue</option>
                    {formProps.parts.map((p) => (
                        <option key={p.id} value={p.id}>{p.part_number} - {p.description}</option>
                    ))}
                </Select>
            </Row>

            <Row label="Item" htmlFor="i-desc" error={form.errors.item_description}
                hint="Required when no catalogued part is linked.">
                <Input id="i-desc" value={form.data.item_description}
                    onChange={(e) => form.setData('item_description', e.target.value)} />
            </Row>
            <Row label="Serial" htmlFor="i-serial" error={form.errors.serial_text}>
                <Input id="i-serial" value={form.data.serial_text}
                    onChange={(e) => form.setData('serial_text', e.target.value)} />
            </Row>

            <Row label="Quantity" htmlFor="i-qty" error={form.errors.quantity}>
                <Input id="i-qty" type="number" step="0.01" min="0.01" value={form.data.quantity}
                    onChange={(e) => form.setData('quantity', e.target.value)} />
            </Row>
            <div className="grid gap-4 sm:grid-cols-2">
                <Row label="Received on" htmlFor="i-start" error={form.errors.started_at}>
                    <DatePicker id="i-start" value={form.data.started_at}
                        onChange={(e) => form.setData('started_at', e.target.value)} />
                </Row>
                <Row label="Due back" htmlFor="i-due" error={form.errors.due_date}>
                    <DatePicker id="i-due" value={form.data.due_date}
                        onChange={(e) => form.setData('due_date', e.target.value)} />
                </Row>
            </div>

            <div className="sm:col-span-2">
                <Row label="Notes" htmlFor="i-notes">
                    <textarea id="i-notes" rows={2} value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                </Row>
            </div>

            <div className="sm:col-span-2">
                <Button type="submit" disabled={form.processing}>Record loan in</Button>
            </div>
        </form>
    );
}

export default function LoansIndex({ loans, filters, counts, formProps, can }) {
    const [f, setF] = useState(filters);
    const [adding, setAdding] = useState(false);
    const outbound = f.direction === 'out';

    const apply = (next) => router.get(route('loans.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    const switchTo = (direction) => { setAdding(false); set('direction', direction); };

    const tabs = [
        { key: 'out', label: 'Outbound', hint: 'Lent by NCAT', count: counts.out },
        { key: 'in', label: 'Inbound', hint: 'Borrowed by NCAT', count: counts.in },
    ];

    return (
        <AppLayout>
            <Head title="Loaners" />
            <PageHeader
                eyebrow="Operations"
                title="Loaners"
                description="Stock NCAT has lent out, and property NCAT is holding for someone else."
                icon={ArrowLeftRight}
                actions={can.manage && (
                    <Button onClick={() => setAdding((v) => !v)} variant={adding ? 'outline' : 'default'}>
                        <Plus className="size-4" />
                        {adding ? 'Cancel' : outbound ? 'Lend stock out' : 'Record borrowed item'}
                    </Button>
                )}
            />

            <div className="mb-4 flex flex-wrap gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.key} type="button" onClick={() => switchTo(t.key)}
                        className={`-mb-px border-b-2 px-4 py-2.5 text-left text-sm font-medium transition-colors ${
                            f.direction === t.key
                                ? 'border-primary text-ncat-navy'
                                : 'border-transparent text-muted-foreground hover:text-ncat-navy'
                        }`}
                    >
                        {t.label}
                        <span className="ml-2 text-xs tabular-nums text-muted-foreground">{t.count}</span>
                        <span className="block text-xs font-normal text-muted-foreground">{t.hint}</span>
                    </button>
                ))}
            </div>

            {!outbound && (
                <p className="mb-4 flex items-start gap-2.5 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm text-ncat-graphite">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    Borrowed stock is tracked but is not NCAT property. It is excluded from stock value,
                    stock summary and reorder alerts, and it is marked as loaned wherever it appears.
                </p>
            )}

            {adding && can.manage && (
                <Card className="mb-4 p-5">
                    <h2 className="mb-4 font-display text-sm font-semibold text-ncat-navy">
                        {outbound ? 'Lend stock out' : 'Record a borrowed item'}
                    </h2>
                    {outbound
                        ? <OutboundForm formProps={formProps} onDone={() => setAdding(false)} />
                        : <InboundForm formProps={formProps} onDone={() => setAdding(false)} />}
                </Card>
            )}

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search party / part / serial…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        <option value="on_loan">On loan</option>
                        <option value="overdue">Overdue</option>
                        <option value="returned">Returned</option>
                        <option value="written_off">Written off</option>
                    </Select>
                </div>
            </Card>

            {loans.length ? (
                <Card className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{outbound ? 'Borrower' : 'Lender'}</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead>Serial</TableHead>
                                <TableHead className="text-right">Qty</TableHead>
                                {outbound && <TableHead>From store</TableHead>}
                                {!outbound && <TableHead>Fitted to</TableHead>}
                                <TableHead>{outbound ? 'Loaned' : 'Received'}</TableHead>
                                <TableHead>Due</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loans.map((l) => (
                                <TableRow key={l.id} className={l.is_overdue ? 'bg-destructive/[0.03]' : undefined}>
                                    <TableCell className="text-sm">
                                        <Link href={route('loans.show', l.id)} className="font-medium text-ncat-navy hover:text-primary">
                                            {l.counterparty}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-sm">{l.item_label}</TableCell>
                                    <TableCell className="font-mono text-sm">{l.serial ?? '—'}</TableCell>
                                    <TableCell className="text-right text-sm tabular-nums">{l.quantity}</TableCell>
                                    {outbound && <TableCell className="text-sm">{l.from_store ?? '—'}</TableCell>}
                                    {!outbound && <TableCell className="text-sm">{l.installed_aircraft ?? '—'}</TableCell>}
                                    <TableCell className="whitespace-nowrap text-sm tabular-nums">{l.started_at}</TableCell>
                                    <TableCell className="whitespace-nowrap text-sm tabular-nums">{l.due_date ?? '—'}</TableCell>
                                    <TableCell><LoanStatus loan={l} /></TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            ) : (
                <EmptyState
                    icon={ArrowLeftRight}
                    title={outbound ? 'Nothing lent out' : 'Nothing borrowed'}
                    description={outbound
                        ? 'When another organisation borrows a part, record it here so the stock shows as on loan rather than missing.'
                        : 'When NCAT borrows a part, record it here so it is tracked without being counted as NCAT stock.'}
                />
            )}
        </AppLayout>
    );
}

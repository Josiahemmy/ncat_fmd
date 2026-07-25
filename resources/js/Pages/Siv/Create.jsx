import { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, PackageMinus, Plus, Search, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';

function Field({ label, error, children }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

const blankItem = (sourceStoreId = '') => ({
    requisition_id: '', part_id: '', description: '', qty_required: '', qty_issued: '',
    source_store_id: sourceStoreId, stores_folio: '', rate: '', amount: '', charging_code: '', serial_ids: [],
});

export default function SivCreate({ parts, stores, approvedRequisitions, nextNumber, storeId }) {
    const today = new Date().toISOString().slice(0, 10);
    const [reqSearch, setReqSearch] = useState('');
    const form = useForm({
        requisition_id: '',
        requisition_for: '',
        ordered_by: '',
        ordered_by_date: today,
        school_section: '',
        approved_by: '',
        approved_by_date: '',
        entered_by: '',
        entered_by_date: '',
        issued_by: '',
        issued_by_date: today,
        received_by: '',
        received_by_date: '',
        remark: '',
        items: [blankItem(storeId ? String(storeId) : '')],
    });

    const partById = (id) => parts.find((p) => String(p.id) === String(id));
    const reqById = (id) => approvedRequisitions.find((r) => String(r.id) === String(id));

    const setItem = (i, patch) => {
        form.setData('items', form.data.items.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
    };
    const addRow = () => form.setData('items', [...form.data.items, blankItem(storeId ? String(storeId) : '')]);
    const removeRow = (i) => form.setData('items', form.data.items.filter((_, idx) => idx !== i));

    const pullRequisition = (i, reqId) => {
        const r = reqById(reqId);
        if (!r) { setItem(i, { requisition_id: '' }); return; }
        setItem(i, {
            requisition_id: r.id,
            part_id: r.part_id ? String(r.part_id) : form.data.items[i].part_id,
            description: r.full_description ?? form.data.items[i].description,
        });
    };

    // The header picker is the primary path: choosing a requisition stamps the
    // caption, the ordering officer and the request date, and seeds the first line.
    const headerRequisition = reqById(form.data.requisition_id);

    const matches = useMemo(() => {
        const q = reqSearch.trim().toLowerCase();
        const rows = q
            ? approvedRequisitions.filter((r) => `${r.requisition_no} ${r.full_description} ${r.part_no ?? ''} ${r.aircraft ?? ''}`
                .toLowerCase().includes(q))
            : approvedRequisitions;

        return rows.slice(0, 50);
    }, [reqSearch, approvedRequisitions]);

    const chooseRequisition = (reqId) => {
        const r = reqById(reqId);

        if (!r) {
            form.setData('requisition_id', '');
            return;
        }

        form.setData({
            ...form.data,
            requisition_id: String(r.id),
            requisition_for: r.label,
            ordered_by: r.ordered_by ?? '',
            ordered_by_date: r.ordered_by_date ?? today,
            items: form.data.items.map((it, idx) => (idx === 0 && !it.requisition_id ? {
                ...it,
                requisition_id: r.id,
                part_id: r.part_id ? String(r.part_id) : it.part_id,
                description: r.full_description ?? it.description,
            } : it)),
        });
    };

    const clearRequisition = () => form.setData({
        ...form.data,
        requisition_id: '',
        requisition_for: '',
        ordered_by: '',
        ordered_by_date: today,
    });

    const setQtyRequired = (i, v) => {
        const it = form.data.items[i];
        // Default qty_issued to match qty_required until the user overrides it.
        const patch = { qty_required: v };
        if (it.qty_issued === '' || String(it.qty_issued) === String(it.qty_required)) {
            patch.qty_issued = v;
        }
        setItem(i, patch);
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('issuing.store'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="New SIV" />
            <PageHeader
                eyebrow={<Link href={route('issuing.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Issuing</Link>}
                title="New store issue voucher"
                description={<>Store Issue Voucher for issuing parts from bonded / dope stores. Will be reserved as <span className="font-mono font-semibold text-ncat-navy">{nextNumber}</span>.</>}
                icon={PackageMinus}
            />

            <Card className="max-w-5xl p-6">
                <form onSubmit={submit} className="space-y-8">
                    {/* Header */}
                    <section className="space-y-4">
                        <div className="border-b border-border pb-2">
                            <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Voucher header</h3>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Field label="Requisition for" error={form.errors.requisition_id ?? form.errors.requisition_for}>
                                    {headerRequisition ? (
                                        <div className="flex flex-wrap items-center gap-3 rounded-lg border border-primary/40 bg-primary/5 px-3 py-2.5">
                                            <span className="font-mono text-sm font-bold text-ncat-navy">{headerRequisition.requisition_no}</span>
                                            <span className="min-w-0 flex-1 truncate text-sm text-ncat-navy">
                                                {headerRequisition.full_description}
                                                {headerRequisition.aircraft ? ` (${headerRequisition.aircraft})` : ''}
                                            </span>
                                            <Button type="button" variant="ghost" size="sm" onClick={clearRequisition}>Change</Button>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <div className="relative">
                                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    className="pl-9"
                                                    placeholder="Search approved requisitions by number, part or aircraft…"
                                                    value={reqSearch}
                                                    onChange={(e) => setReqSearch(e.target.value)}
                                                />
                                            </div>
                                            {matches.length ? (
                                                <ul className="max-h-56 divide-y divide-border overflow-y-auto rounded-lg border border-border">
                                                    {matches.map((r) => (
                                                        <li key={r.id}>
                                                            <button
                                                                type="button"
                                                                onClick={() => chooseRequisition(r.id)}
                                                                className="flex w-full items-baseline gap-3 px-3 py-2 text-left transition-colors hover:bg-accent"
                                                            >
                                                                <span className="font-mono text-sm font-semibold text-ncat-navy">{r.requisition_no}</span>
                                                                <span className="min-w-0 flex-1 truncate text-sm">{r.full_description}</span>
                                                                <span className="shrink-0 text-xs text-muted-foreground">{r.ordered_by}</span>
                                                            </button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                                                    {approvedRequisitions.length
                                                        ? 'No approved requisition matches that search.'
                                                        : 'No fully approved requisition is waiting to be issued.'}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Leave this empty for a standalone voucher and type the ordering officer by hand.
                                            </p>
                                        </div>
                                    )}
                                </Field>
                            </div>
                            <Field label="School / Section" error={form.errors.school_section}>
                                <Input value={form.data.school_section} onChange={(e) => form.setData('school_section', e.target.value)} />
                            </Field>
                            <div />
                            <Field
                                label={headerRequisition ? 'Ordered by (from the requisition)' : 'Ordered by'}
                                error={form.errors.ordered_by}
                            >
                                <div className="relative">
                                    <Input
                                        value={form.data.ordered_by}
                                        readOnly={!!headerRequisition}
                                        aria-readonly={!!headerRequisition}
                                        className={headerRequisition ? 'cursor-not-allowed bg-muted pr-9 text-muted-foreground' : undefined}
                                        onChange={(e) => !headerRequisition && form.setData('ordered_by', e.target.value)}
                                    />
                                    {headerRequisition && (
                                        <Lock className="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                    )}
                                </div>
                            </Field>
                            <Field
                                label={headerRequisition ? 'Request date (from the requisition)' : 'Ordered by date'}
                                error={form.errors.ordered_by_date}
                            >
                                <Input
                                    type="date"
                                    value={form.data.ordered_by_date}
                                    readOnly={!!headerRequisition}
                                    aria-readonly={!!headerRequisition}
                                    className={headerRequisition ? 'cursor-not-allowed bg-muted text-muted-foreground' : undefined}
                                    onChange={(e) => !headerRequisition && form.setData('ordered_by_date', e.target.value)}
                                />
                            </Field>
                            <Field label="Approved by" error={form.errors.approved_by}>
                                <Input value={form.data.approved_by} onChange={(e) => form.setData('approved_by', e.target.value)} />
                            </Field>
                            <Field label="Approved by date" error={form.errors.approved_by_date}>
                                <Input type="date" value={form.data.approved_by_date} onChange={(e) => form.setData('approved_by_date', e.target.value)} />
                            </Field>
                            <Field label="Entered by" error={form.errors.entered_by}>
                                <Input value={form.data.entered_by} onChange={(e) => form.setData('entered_by', e.target.value)} />
                            </Field>
                            <Field label="Entered by date" error={form.errors.entered_by_date}>
                                <Input type="date" value={form.data.entered_by_date} onChange={(e) => form.setData('entered_by_date', e.target.value)} />
                            </Field>
                            <Field label="Issued by" error={form.errors.issued_by}>
                                <Input value={form.data.issued_by} onChange={(e) => form.setData('issued_by', e.target.value)} />
                            </Field>
                            <Field label="Issued by date" error={form.errors.issued_by_date}>
                                <Input type="date" value={form.data.issued_by_date} onChange={(e) => form.setData('issued_by_date', e.target.value)} />
                            </Field>
                            <Field label="Received by" error={form.errors.received_by}>
                                <Input value={form.data.received_by} onChange={(e) => form.setData('received_by', e.target.value)} />
                            </Field>
                            <Field label="Received by date" error={form.errors.received_by_date}>
                                <Input type="date" value={form.data.received_by_date} onChange={(e) => form.setData('received_by_date', e.target.value)} />
                            </Field>
                        </div>
                    </section>

                    {/* Line items */}
                    <section className="space-y-4">
                        <div className="flex items-center justify-between border-b border-border pb-2">
                            <div>
                                <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Items issued</h3>
                                <p className="text-xs text-muted-foreground">Pull from an approved requisition or add a standalone line. Issues come from bonded / dope stores only.</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={addRow}><Plus className="size-4" /> Add row</Button>
                        </div>

                        {typeof form.errors.items === 'string' && <p className="text-sm text-destructive">{form.errors.items}</p>}

                        <div className="space-y-4">
                            {form.data.items.map((it, i) => {
                                const p = partById(it.part_id);
                                return (
                                    <div key={i} className="rounded-lg border border-border p-4">
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Item {i + 1}</span>
                                            <Button type="button" variant="ghost" size="icon" onClick={() => removeRow(i)} disabled={form.data.items.length === 1} title="Remove row">
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </div>

                                        <div className="mb-4">
                                            <Field label="Pull from approved requisition (optional)" error={form.errors[`items.${i}.requisition_id`]}>
                                                <Select value={it.requisition_id} onChange={(e) => pullRequisition(i, e.target.value)}>
                                                    <option value="">Standalone line (no requisition)</option>
                                                    {approvedRequisitions.map((r) => (
                                                        <option key={r.id} value={r.id}>
                                                            {r.requisition_no} - {r.full_description}{r.aircraft ? ` (${r.aircraft})` : ''}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </Field>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <div className="sm:col-span-2">
                                                <Field label="Part" error={form.errors[`items.${i}.part_id`]}>
                                                    <Select value={it.part_id} onChange={(e) => setItem(i, { part_id: e.target.value })}>
                                                        <option value="">Select part…</option>
                                                        {parts.map((pt) => (
                                                            <option key={pt.id} value={pt.id}>{pt.part_number}{pt.description ? ` - ${pt.description}` : ''}</option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Field label="Description" error={form.errors[`items.${i}.description`]}>
                                                    <Input value={it.description} onChange={(e) => setItem(i, { description: e.target.value })} />
                                                </Field>
                                            </div>
                                            <Field label="Qty required" error={form.errors[`items.${i}.qty_required`]}>
                                                <Input type="number" step="any" min="0" value={it.qty_required} onChange={(e) => setQtyRequired(i, e.target.value)} />
                                            </Field>
                                            <Field label="Qty issued" error={form.errors[`items.${i}.qty_issued`]}>
                                                <Input type="number" step="any" min="0" value={it.qty_issued} onChange={(e) => setItem(i, { qty_issued: e.target.value })} />
                                            </Field>
                                            <div className="sm:col-span-2">
                                                <Field label="Source store" error={form.errors[`items.${i}.source_store_id`]}>
                                                    <Select value={it.source_store_id} onChange={(e) => setItem(i, { source_store_id: e.target.value })}>
                                                        <option value="">Select store…</option>
                                                        {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                                    </Select>
                                                </Field>
                                            </div>
                                            <Field label="Stores folio" error={form.errors[`items.${i}.stores_folio`]}>
                                                <Input value={it.stores_folio} onChange={(e) => setItem(i, { stores_folio: e.target.value })} />
                                            </Field>
                                            <Field label="Rate" error={form.errors[`items.${i}.rate`]}>
                                                <Input type="number" step="any" min="0" value={it.rate} onChange={(e) => setItem(i, { rate: e.target.value })} />
                                            </Field>
                                            <Field label="Amount (₦/K)" error={form.errors[`items.${i}.amount`]}>
                                                <Input type="number" step="any" min="0" value={it.amount} onChange={(e) => setItem(i, { amount: e.target.value })} />
                                            </Field>
                                            <Field label="Charging code" error={form.errors[`items.${i}.charging_code`]}>
                                                <Input value={it.charging_code} onChange={(e) => setItem(i, { charging_code: e.target.value })} />
                                            </Field>
                                        </div>

                                        {p && p.is_serialized && (
                                            <div className="mt-4 rounded-md border border-dashed border-border bg-muted/30 p-3">
                                                <Field label="Serial IDs to issue" error={form.errors[`items.${i}.serial_ids`]}>
                                                    <Input
                                                        placeholder="Enter serial record IDs, comma separated, e.g. 12, 15, 18"
                                                        value={(it.serial_ids || []).join(', ')}
                                                        onChange={(e) => setItem(i, {
                                                            serial_ids: e.target.value.split(',')
                                                                .map((s) => s.trim())
                                                                .filter(Boolean)
                                                                .map((s) => Number(s))
                                                                .filter((n) => Number.isInteger(n)),
                                                        })}
                                                    />
                                                    <p className="mt-1 text-xs text-muted-foreground">Serialised part. Enter the serial record IDs being issued.</p>
                                                </Field>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <Button type="button" variant="outline" size="sm" onClick={addRow}><Plus className="size-4" /> Add row</Button>
                    </section>

                    {/* Remark */}
                    <section className="space-y-4">
                        <Field label="Remark (optional)" error={form.errors.remark}>
                            <textarea rows={3} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                value={form.data.remark} onChange={(e) => form.setData('remark', e.target.value)} />
                        </Field>
                    </section>

                    <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                        <Link href={route('issuing.index')}><Button type="button" variant="outline">Cancel</Button></Link>
                        <Button type="submit" disabled={form.processing}>Save draft</Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}

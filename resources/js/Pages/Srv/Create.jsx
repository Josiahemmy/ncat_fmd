import { useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus, Plus, Trash2 } from 'lucide-react';
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

const blankItem = {
    part_id: '', quantity: '', supplier_details: '', fol_no: '', rate: '', amount: '',
    invoice_no: '', acct_code: '', serials: [], batch_no: '', batch_year: '', expiry_date: '',
};

export default function SrvCreate({ parts, stores, quarantineId, fuelStoreId, nextNumber }) {
    const form = useForm({
        srv_date: new Date().toISOString().slice(0, 10),
        destination_store_id: quarantineId ?? '',
        supplier: '',
        lpo_or_petty_cash_ref: '',
        head_of_receiving_dept: '',
        storekeeper: '',
        remarks: '',
        items: [{ ...blankItem }],
    });

    const partById = (id) => parts.find((p) => String(p.id) === String(id));
    const hasFuel = form.data.items.some((it) => { const p = partById(it.part_id); return p && p.is_fuel; });

    // Fuel lock: any fuel part routes the whole voucher to the Fuel Dump.
    useEffect(() => {
        if (hasFuel) {
            if (String(form.data.destination_store_id) !== String(fuelStoreId)) {
                form.setData('destination_store_id', fuelStoreId ?? '');
            }
        } else if (String(form.data.destination_store_id) === String(fuelStoreId)) {
            form.setData('destination_store_id', quarantineId ?? '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasFuel]);

    const setItem = (i, patch) => {
        form.setData('items', form.data.items.map((it, idx) => (idx === i ? { ...it, ...patch } : it)));
    };
    const addRow = () => form.setData('items', [...form.data.items, { ...blankItem }]);
    const removeRow = (i) => form.setData('items', form.data.items.filter((_, idx) => idx !== i));

    const submit = (e) => {
        e.preventDefault();
        form.post(route('receiving.store'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="New SRV" />
            <PageHeader
                eyebrow={<Link href={route('receiving.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Receiving</Link>}
                title="New store receipt voucher"
                description={<>Store Receipt Voucher — receive goods into stores. Will be reserved as <span className="font-mono font-semibold text-ncat-navy">{nextNumber}</span>.</>}
                icon={PackagePlus}
            />

            <Card className="max-w-5xl p-6">
                <form onSubmit={submit} className="space-y-8">
                    {/* Header */}
                    <section className="space-y-4">
                        <div className="border-b border-border pb-2">
                            <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Voucher header</h3>
                        </div>

                        <p className="text-sm text-ncat-navy">
                            Please receive the following items into the{' '}
                            <span className="inline-flex align-middle">
                                <Select
                                    className="inline-block w-56"
                                    value={form.data.destination_store_id}
                                    disabled={hasFuel}
                                    onChange={(e) => form.setData('destination_store_id', e.target.value)}
                                >
                                    {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </span>{' '}
                            store.
                        </p>
                        {hasFuel && <p className="text-xs text-muted-foreground">Fuel routes to the Fuel Dump.</p>}
                        {form.errors.destination_store_id && <p className="text-sm text-destructive">{form.errors.destination_store_id}</p>}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="SRV date" error={form.errors.srv_date}>
                                <Input type="date" value={form.data.srv_date} onChange={(e) => form.setData('srv_date', e.target.value)} />
                            </Field>
                            <Field label="LPO / Petty cash voucher no." error={form.errors.lpo_or_petty_cash_ref}>
                                <Input value={form.data.lpo_or_petty_cash_ref} onChange={(e) => form.setData('lpo_or_petty_cash_ref', e.target.value)} />
                            </Field>
                            <Field label="Supplier" error={form.errors.supplier}>
                                <Input value={form.data.supplier} onChange={(e) => form.setData('supplier', e.target.value)} />
                            </Field>
                            <Field label="Head of receiving dept." error={form.errors.head_of_receiving_dept}>
                                <Input value={form.data.head_of_receiving_dept} onChange={(e) => form.setData('head_of_receiving_dept', e.target.value)} />
                            </Field>
                            <Field label="Storekeeper" error={form.errors.storekeeper}>
                                <Input value={form.data.storekeeper} onChange={(e) => form.setData('storekeeper', e.target.value)} />
                            </Field>
                        </div>
                    </section>

                    {/* Line items */}
                    <section className="space-y-4">
                        <div className="flex items-center justify-between border-b border-border pb-2">
                            <div>
                                <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Items received</h3>
                                <p className="text-xs text-muted-foreground">One row per part. Serialised and shelf-life parts ask for extra detail.</p>
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

                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <div className="sm:col-span-2">
                                                <Field label="Part" error={form.errors[`items.${i}.part_id`]}>
                                                    <Select value={it.part_id} onChange={(e) => setItem(i, { part_id: e.target.value })}>
                                                        <option value="">Select part…</option>
                                                        {parts.map((pt) => (
                                                            <option key={pt.id} value={pt.id}>{pt.part_number}{pt.description ? ` — ${pt.description}` : ''}</option>
                                                        ))}
                                                    </Select>
                                                </Field>
                                            </div>
                                            <Field label="Quantity" error={form.errors[`items.${i}.quantity`]}>
                                                <Input type="number" step="any" min="0" value={it.quantity} onChange={(e) => setItem(i, { quantity: e.target.value })} />
                                            </Field>
                                            <Field label="Rate" error={form.errors[`items.${i}.rate`]}>
                                                <Input type="number" step="any" min="0" value={it.rate} onChange={(e) => setItem(i, { rate: e.target.value })} />
                                            </Field>
                                            <div className="sm:col-span-2">
                                                <Field label="Suppliers & details of materials" error={form.errors[`items.${i}.supplier_details`]}>
                                                    <Input value={it.supplier_details} onChange={(e) => setItem(i, { supplier_details: e.target.value })} />
                                                </Field>
                                            </div>
                                            <Field label="FOL no." error={form.errors[`items.${i}.fol_no`]}>
                                                <Input value={it.fol_no} onChange={(e) => setItem(i, { fol_no: e.target.value })} />
                                            </Field>
                                            <Field label="Amount (₦/K)" error={form.errors[`items.${i}.amount`]}>
                                                <Input type="number" step="any" min="0" value={it.amount} onChange={(e) => setItem(i, { amount: e.target.value })} />
                                            </Field>
                                            <Field label="Invoice no." error={form.errors[`items.${i}.invoice_no`]}>
                                                <Input value={it.invoice_no} onChange={(e) => setItem(i, { invoice_no: e.target.value })} />
                                            </Field>
                                            <Field label="Acct code" error={form.errors[`items.${i}.acct_code`]}>
                                                <Input value={it.acct_code} onChange={(e) => setItem(i, { acct_code: e.target.value })} />
                                            </Field>
                                        </div>

                                        {p && p.is_serialized && (
                                            <div className="mt-4 rounded-md border border-dashed border-border bg-muted/30 p-3">
                                                <Field label="Serial numbers (comma separated)" error={form.errors[`items.${i}.serials`]}>
                                                    <Input
                                                        placeholder="e.g. SN-001, SN-002, SN-003"
                                                        value={(it.serials || []).join(', ')}
                                                        onChange={(e) => setItem(i, { serials: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })}
                                                    />
                                                </Field>
                                            </div>
                                        )}

                                        {p && p.has_shelf_life && (
                                            <div className="mt-4 grid gap-4 rounded-md border border-dashed border-border bg-muted/30 p-3 sm:grid-cols-3">
                                                <Field label="Batch no." error={form.errors[`items.${i}.batch_no`]}>
                                                    <Input value={it.batch_no} onChange={(e) => setItem(i, { batch_no: e.target.value })} />
                                                </Field>
                                                <Field label="Batch year" error={form.errors[`items.${i}.batch_year`]}>
                                                    <Input placeholder="e.g. 2026" value={it.batch_year} onChange={(e) => setItem(i, { batch_year: e.target.value })} />
                                                </Field>
                                                <Field label="Expiry date" error={form.errors[`items.${i}.expiry_date`]}>
                                                    <Input type="date" value={it.expiry_date} onChange={(e) => setItem(i, { expiry_date: e.target.value })} />
                                                </Field>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <Button type="button" variant="outline" size="sm" onClick={addRow}><Plus className="size-4" /> Add row</Button>
                    </section>

                    {/* Remarks */}
                    <section className="space-y-4">
                        <Field label="Remarks (optional)" error={form.errors.remarks}>
                            <textarea rows={3} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        </Field>
                    </section>

                    <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                        <Link href={route('receiving.index')}><Button type="button" variant="outline">Cancel</Button></Link>
                        <Button type="submit" disabled={form.processing}>Save draft</Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}

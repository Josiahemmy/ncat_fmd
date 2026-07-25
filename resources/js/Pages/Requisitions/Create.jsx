import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ScrollText } from 'lucide-react';
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

export default function RequisitionsCreate({ aircraft, parts, workOrders, workOrderId, originStore }) {
    const form = useForm({
        work_order_id: workOrderId ? String(workOrderId) : '',
        aircraft_id: '',
        aircraft_reg: '',
        engine_serial_no: '',
        position: '',
        authorised_by: '',
        // Raised from a store page: that store is the supply source on the sheet.
        supply_source: originStore?.name ?? '',
        full_description: '',
        part_id: '',
        part_no: '',
        stock_code: '',
        serial_number: '',
        batch_no: '',
        batch_year: '',
        bin_bal_line_no: '',
        requisition_date: new Date().toISOString().slice(0, 10),
        submit: 0,
    });

    // When a catalogued part is chosen, pre-fill the free-text identity fields.
    const pickPart = (val) => {
        const p = parts.find((x) => String(x.id) === val);
        form.setData((d) => ({
            ...d,
            part_id: val,
            part_no: p ? (p.part_number ?? d.part_no) : d.part_no,
            stock_code: p ? (p.stock_code ?? d.stock_code) : d.stock_code,
            full_description: p && !d.full_description ? (p.description ?? '') : d.full_description,
        }));
    };

    const save = (submitForApproval) => {
        form.transform((data) => ({ ...data, submit: submitForApproval ? 1 : 0 }));
        form.post(route('requisitions.store'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="New requisition" />
            <PageHeader
                eyebrow={<Link href={route('requisitions.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Requisitions</Link>}
                title="Raise requisition"
                description={originStore
                    ? `Aircraft Spare Parts Requisition Sheet, to be supplied from ${originStore.name}. One voucher per unit.`
                    : 'Aircraft Spare Parts Requisition Sheet. One voucher per unit.'}
                icon={ScrollText}
            />

            <Card className="max-w-3xl p-6">
                <form onSubmit={(e) => { e.preventDefault(); save(false); }} className="space-y-8">
                    {/* Required for */}
                    <section className="space-y-4">
                        <div className="border-b border-border pb-2">
                            <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Required for</h3>
                            <p className="text-xs text-muted-foreground">One voucher per unit. No quantity field.</p>
                        </div>
                        <Field label="Work order" error={form.errors.work_order_id}>
                            <Select value={form.data.work_order_id} onChange={(e) => form.setData('work_order_id', e.target.value)}>
                                <option value="">None</option>
                                {workOrders.map((w) => <option key={w.id} value={w.id}>{w.wo_ref}</option>)}
                            </Select>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Aircraft" error={form.errors.aircraft_id}>
                                <Select value={form.data.aircraft_id} onChange={(e) => form.setData('aircraft_id', e.target.value)}>
                                    <option value="">Select aircraft…</option>
                                    {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                                </Select>
                            </Field>
                            <Field label="Aircraft reg (as written)" error={form.errors.aircraft_reg}>
                                <Input value={form.data.aircraft_reg} onChange={(e) => form.setData('aircraft_reg', e.target.value)} placeholder="e.g. 5N-CAT" />
                            </Field>
                            <Field label="Engine serial no." error={form.errors.engine_serial_no}>
                                <Input value={form.data.engine_serial_no} onChange={(e) => form.setData('engine_serial_no', e.target.value)} />
                            </Field>
                            <Field label="Position" error={form.errors.position}>
                                <Input value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} />
                            </Field>
                            <Field label="Authorised by" error={form.errors.authorised_by}>
                                <Input value={form.data.authorised_by} onChange={(e) => form.setData('authorised_by', e.target.value)} />
                            </Field>
                            <Field label="Supply source" error={form.errors.supply_source}>
                                <Input value={form.data.supply_source} onChange={(e) => form.setData('supply_source', e.target.value)} />
                            </Field>
                        </div>
                    </section>

                    {/* Part identification */}
                    <section className="space-y-4">
                        <div className="border-b border-border pb-2">
                            <h3 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy">Part identification</h3>
                        </div>
                        <Field label="Full description" error={form.errors.full_description}>
                            <textarea rows={2} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                                value={form.data.full_description} onChange={(e) => form.setData('full_description', e.target.value)} />
                        </Field>
                        <Field label="Part (catalogue)" error={form.errors.part_id}>
                            <Select value={form.data.part_id} onChange={(e) => pickPart(e.target.value)}>
                                <option value="">Not in catalogue / free text</option>
                                {parts.map((p) => (
                                    <option key={p.id} value={p.id}>{p.part_number}{p.description ? ` - ${p.description}` : ''}</option>
                                ))}
                            </Select>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Part no." error={form.errors.part_no}>
                                <Input value={form.data.part_no} onChange={(e) => form.setData('part_no', e.target.value)} />
                            </Field>
                            <Field label="Stock code" error={form.errors.stock_code}>
                                <Input value={form.data.stock_code} onChange={(e) => form.setData('stock_code', e.target.value)} />
                            </Field>
                            <Field label="Serial number" error={form.errors.serial_number}>
                                <Input value={form.data.serial_number} onChange={(e) => form.setData('serial_number', e.target.value)} />
                            </Field>
                            <Field label="Batch no." error={form.errors.batch_no}>
                                <Input value={form.data.batch_no} onChange={(e) => form.setData('batch_no', e.target.value)} />
                            </Field>
                            <Field label="Batch year" error={form.errors.batch_year}>
                                <Input value={form.data.batch_year} onChange={(e) => form.setData('batch_year', e.target.value)} placeholder="e.g. 2026" />
                            </Field>
                            <Field label="Bin / bal line no." error={form.errors.bin_bal_line_no}>
                                <Input value={form.data.bin_bal_line_no} onChange={(e) => form.setData('bin_bal_line_no', e.target.value)} />
                            </Field>
                        </div>
                        <div className="sm:max-w-xs">
                            <Field label="Requisition date" error={form.errors.requisition_date}>
                                <Input type="date" value={form.data.requisition_date} onChange={(e) => form.setData('requisition_date', e.target.value)} />
                            </Field>
                        </div>
                    </section>

                    <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
                        <Link href={route('requisitions.index')}><Button type="button" variant="outline">Cancel</Button></Link>
                        <Button type="submit" variant="secondary" disabled={form.processing}>Save draft</Button>
                        <Button type="button" disabled={form.processing} onClick={() => save(true)}>Save &amp; submit for approval</Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}

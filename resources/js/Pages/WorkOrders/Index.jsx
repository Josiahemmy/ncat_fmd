import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Filter, Plus, Search, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter } from '@/Components/ui/Modal';
import { WorkOrderStatusBadge, WorkTypeBadge } from '@/Components/documents/WorkOrderBadges';
import { usePermissions } from '@/lib/permissions';

export default function WorkOrdersIndex({ workOrders, aircraft, filters, presets }) {
    const { can } = usePermissions();
    const canCreate = can('work_orders.create');
    const [f, setF] = useState(filters);
    const [creating, setCreating] = useState(false);

    const apply = (next = f) => router.get(route('work-orders.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Work Orders" />
            <PageHeader
                eyebrow="Operations"
                title="Work Orders"
                description="The department's work order register for snags and scheduled inspections, mirroring the ledger."
                icon={Wrench}
                actions={canCreate && <Button onClick={() => setCreating(true)}><Plus className="size-4" /> New work order</Button>}
            />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search ref / description / raised by…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.aircraft} onChange={(e) => set('aircraft', e.target.value)}>
                        <option value="">All aircraft</option>
                        {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                    </Select>
                    <Select value={f.type} onChange={(e) => set('type', e.target.value)}>
                        <option value="">All types</option>
                        <option value="snag">Snag</option>
                        <option value="scheduled_inspection">Scheduled inspection</option>
                        <option value="other">Other</option>
                    </Select>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In progress</option>
                        <option value="closed">Closed</option>
                    </Select>
                    <div className="flex items-center gap-2">
                        <Input type="date" value={f.from} onChange={(e) => set('from', e.target.value)} title="From date" />
                        <Input type="date" value={f.to} onChange={(e) => set('to', e.target.value)} title="To date" />
                    </div>
                </div>
            </Card>

            <Card className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-12">S/No</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>A/C Reg</TableHead>
                            <TableHead>Type of inspection / description</TableHead>
                            <TableHead>WO Ref</TableHead>
                            <TableHead>Raised by</TableHead>
                            <TableHead>QC check by</TableHead>
                            <TableHead>Records updated by</TableHead>
                            <TableHead>Filed by</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {workOrders.map((wo, i) => (
                            <TableRow key={wo.id}>
                                <TableCell className="tabular-nums text-muted-foreground">{i + 1}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{wo.work_date}</TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-sm">{wo.aircraft_reg}</TableCell>
                                <TableCell className="max-w-sm">
                                    <Link href={route('work-orders.show', wo.id)} className="font-medium text-ncat-navy hover:text-primary">
                                        {wo.title}
                                    </Link>
                                    <div className="mt-0.5"><WorkTypeBadge type={wo.work_type} /></div>
                                </TableCell>
                                <TableCell className="whitespace-nowrap font-mono text-xs">{wo.wo_ref}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{wo.raised_by}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{wo.qc_checked_by ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{wo.records_updated_by ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{wo.filed_by ?? '—'}</TableCell>
                                <TableCell><WorkOrderStatusBadge status={wo.status} /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!workOrders.length && (
                    <div className="p-10 text-center">
                        <Wrench className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No work orders match these filters.</p>
                    </div>
                )}
            </Card>

            {creating && <WorkOrderModal aircraft={aircraft} presets={presets} onClose={() => setCreating(false)} />}
        </AppLayout>
    );
}

function WorkOrderModal({ aircraft, presets, onClose }) {
    const form = useForm({
        aircraft_id: '',
        work_type: 'snag',
        inspection_type: '',
        title: '',
        description: '',
        raised_by: '',
        work_date: new Date().toISOString().slice(0, 10),
    });

    const isInspection = form.data.work_type === 'scheduled_inspection';
    const usePreset = (val) => {
        form.setData((d) => ({ ...d, inspection_type: val, title: val && val !== '__custom' ? `${val} INSPECTION` : '' }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('work-orders.store'), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <ModalHeader><ModalTitle>Raise work order</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Aircraft</Label>
                            <Select value={form.data.aircraft_id} onChange={(e) => form.setData('aircraft_id', e.target.value)}>
                                <option value="">Select aircraft…</option>
                                {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                            </Select>
                            {form.errors.aircraft_id && <p className="text-sm text-destructive">{form.errors.aircraft_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Work date</Label>
                            <Input type="date" value={form.data.work_date} onChange={(e) => form.setData('work_date', e.target.value)} />
                            {form.errors.work_date && <p className="text-sm text-destructive">{form.errors.work_date}</p>}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Work type</Label>
                        <div className="grid grid-cols-3 gap-2">
                            {[['snag', 'Snag'], ['scheduled_inspection', 'Inspection'], ['other', 'Other']].map(([val, label]) => (
                                <button key={val} type="button"
                                    onClick={() => form.setData((d) => ({ ...d, work_type: val, inspection_type: '' }))}
                                    className={`rounded-lg border px-3 py-2 text-sm font-medium transition ${form.data.work_type === val ? 'border-primary bg-primary/10 text-primary' : 'border-input text-muted-foreground hover:bg-muted'}`}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {isInspection && (
                        <div className="space-y-1.5">
                            <Label>Inspection interval</Label>
                            <Select value={presets.includes(form.data.inspection_type) ? form.data.inspection_type : (form.data.inspection_type ? '__custom' : '')}
                                onChange={(e) => usePreset(e.target.value)}>
                                <option value="">Select…</option>
                                {presets.map((p) => <option key={p} value={p}>{p}</option>)}
                                <option value="__custom">Other (free text)</option>
                            </Select>
                            {(form.data.inspection_type === '__custom' || (form.data.inspection_type && !presets.includes(form.data.inspection_type))) && (
                                <Input className="mt-2" placeholder="e.g. Hard Landing Check" value={form.data.inspection_type === '__custom' ? '' : form.data.inspection_type}
                                    onChange={(e) => form.setData((d) => ({ ...d, inspection_type: e.target.value, title: e.target.value }))} />
                            )}
                            {form.errors.inspection_type && <p className="text-sm text-destructive">{form.errors.inspection_type}</p>}
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>{form.data.work_type === 'snag' ? 'Snag description' : 'Register title'}</Label>
                        <Input placeholder={form.data.work_type === 'snag' ? 'SNAG: …' : 'e.g. 100 HRS INSPECTION'}
                            value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Details (optional)</Label>
                        <textarea rows={3} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Raised by</Label>
                        <Input placeholder="Name on the ledger" value={form.data.raised_by} onChange={(e) => form.setData('raised_by', e.target.value)} />
                        {form.errors.raised_by && <p className="text-sm text-destructive">{form.errors.raised_by}</p>}
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Raise work order</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

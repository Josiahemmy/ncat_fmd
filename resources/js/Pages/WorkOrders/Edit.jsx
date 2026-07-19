import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';

export default function WorkOrderEdit({ workOrder, aircraft, presets, openRequisitions }) {
    const form = useForm({
        aircraft_id: workOrder.aircraft_id,
        work_type: workOrder.work_type,
        inspection_type: workOrder.inspection_type || '',
        title: workOrder.title,
        description: workOrder.description || '',
        status: workOrder.status,
        raised_by: workOrder.raised_by,
        qc_checked_by: workOrder.qc_checked_by || '',
        records_updated_by: workOrder.records_updated_by || '',
        filed_by: workOrder.filed_by || '',
        work_date: workOrder.work_date,
        remarks: workOrder.remarks || '',
    });

    const closingOverOpen = form.data.status === 'closed' && workOrder.status !== 'closed' && openRequisitions > 0;

    const submit = (e) => {
        e.preventDefault();
        form.put(route('work-orders.update', workOrder.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit ${workOrder.wo_ref}`} />
            <PageHeader
                eyebrow={<Link href={route('work-orders.show', workOrder.id)} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> {workOrder.wo_ref}</Link>}
                title="Edit work order"
                icon={Wrench}
            />

            <Card className="max-w-2xl p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Aircraft</Label>
                            <Select value={form.data.aircraft_id} onChange={(e) => form.setData('aircraft_id', e.target.value)}>
                                {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Work date</Label>
                            <Input type="date" value={form.data.work_date} onChange={(e) => form.setData('work_date', e.target.value)} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Work type</Label>
                            <Select value={form.data.work_type} onChange={(e) => form.setData('work_type', e.target.value)}>
                                <option value="snag">Snag</option>
                                <option value="scheduled_inspection">Scheduled inspection</option>
                                <option value="other">Other</option>
                            </Select>
                        </div>
                        {form.data.work_type === 'scheduled_inspection' && (
                            <div className="space-y-1.5">
                                <Label>Inspection interval</Label>
                                <Input list="wo-presets" value={form.data.inspection_type} onChange={(e) => form.setData('inspection_type', e.target.value)} />
                                <datalist id="wo-presets">{presets.map((p) => <option key={p} value={p} />)}</datalist>
                                {form.errors.inspection_type && <p className="text-sm text-destructive">{form.errors.inspection_type}</p>}
                            </div>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Register title</Label>
                        <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                        {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Details</Label>
                        <textarea rows={3} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Raised by</Label>
                            <Input value={form.data.raised_by} onChange={(e) => form.setData('raised_by', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>QC check by</Label>
                            <Input value={form.data.qc_checked_by} onChange={(e) => form.setData('qc_checked_by', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Records updated by</Label>
                            <Input value={form.data.records_updated_by} onChange={(e) => form.setData('records_updated_by', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Filed by</Label>
                            <Input value={form.data.filed_by} onChange={(e) => form.setData('filed_by', e.target.value)} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Status</Label>
                        <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            <option value="open">Open</option>
                            <option value="in_progress">In progress</option>
                            <option value="closed">Closed</option>
                        </Select>
                    </div>

                    {closingOverOpen && (
                        <div className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <span>This work order has {openRequisitions} requisition(s) not yet closed. You can still close it, but confirm this is intended.</span>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Remarks</Label>
                        <Input value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Link href={route('work-orders.show', workOrder.id)}><Button type="button" variant="outline">Cancel</Button></Link>
                        <Button type="submit" disabled={form.processing}>Save changes</Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, ScrollText, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { WorkOrderStatusBadge, WorkTypeBadge } from '@/Components/documents/WorkOrderBadges';
import { RequisitionStatusBadge } from '@/Components/documents/RequisitionBadges';
import { usePermissions } from '@/lib/permissions';

function Field({ label, children }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm text-ncat-navy">{children ?? '—'}</dd>
        </div>
    );
}

export default function WorkOrderShow({ workOrder, requisitions, presets }) {
    const { can } = usePermissions();

    return (
        <AppLayout>
            <Head title={workOrder.wo_ref} />
            <PageHeader
                eyebrow={<Link href={route('work-orders.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Work Orders</Link>}
                title={workOrder.wo_ref}
                description={workOrder.title}
                icon={Wrench}
                actions={can('work_orders.edit') && (
                    <Link href={route('work-orders.edit', workOrder.id)}>
                        <Button variant="outline"><Pencil className="size-4" /> Edit</Button>
                    </Link>
                )}
            />

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="p-5 lg:col-span-2">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <WorkOrderStatusBadge status={workOrder.status} />
                        <WorkTypeBadge type={workOrder.work_type} />
                    </div>
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Field label="Aircraft">{workOrder.aircraft_reg} · {workOrder.aircraft_type}</Field>
                        <Field label="Work date">{workOrder.work_date}</Field>
                        {workOrder.work_type === 'scheduled_inspection' && <Field label="Inspection">{workOrder.inspection_type}</Field>}
                        <Field label="Raised by">{workOrder.raised_by}</Field>
                        <Field label="QC check by">{workOrder.qc_checked_by}</Field>
                        <Field label="Records updated by">{workOrder.records_updated_by}</Field>
                        <Field label="Filed by">{workOrder.filed_by}</Field>
                        {workOrder.closed_at && <Field label="Closed">{workOrder.closed_at}</Field>}
                    </dl>
                    {workOrder.description && (
                        <div className="mt-4 border-t border-border pt-4">
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Details</p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-ncat-navy">{workOrder.description}</p>
                        </div>
                    )}
                    {workOrder.remarks && (
                        <div className="mt-4 border-t border-border pt-4">
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Remarks</p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-ncat-navy">{workOrder.remarks}</p>
                        </div>
                    )}
                    <p className="mt-4 text-xs text-muted-foreground">Created by {workOrder.created_by ?? 'system'}</p>
                </Card>

                <Card className="p-5">
                    <div className="mb-3 flex items-center gap-2">
                        <ScrollText className="size-4 text-primary" />
                        <h3 className="font-semibold text-ncat-navy">Linked requisitions</h3>
                    </div>
                    {requisitions.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>No.</TableHead>
                                    <TableHead>Part</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requisitions.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>
                                            <Link href={route('requisitions.show', r.id)} className="font-mono text-xs text-primary hover:underline">{r.requisition_no}</Link>
                                        </TableCell>
                                        <TableCell className="text-sm">{r.part_no ?? r.description}</TableCell>
                                        <TableCell><RequisitionStatusBadge status={r.status} /></TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-sm text-muted-foreground">No requisitions raised against this work order yet.</p>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, FileDown, Printer, ScrollText, Wrench, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter } from '@/Components/ui/Modal';
import { RequisitionStatusBadge } from '@/Components/documents/RequisitionBadges';
import { usePermissions } from '@/lib/permissions';

/** A numbered form box: small uppercase label + value, resembling the paper voucher. */
function Box({ n, label, value, className = '', children }) {
    return (
        <div className={`min-h-[3.25rem] p-1.5 ${className}`}>
            <div className="text-[0.65rem] font-semibold uppercase leading-tight tracking-wide text-ncat-navy">
                {n != null && <span>({n}) </span>}{label}
            </div>
            <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium text-ncat-navy">
                {children ?? (value || ' ')}
            </div>
        </div>
    );
}

export default function RequisitionShow({ requisition, flow }) {
    const { can } = usePermissions();
    const r = requisition;
    const [approving, setApproving] = useState(false);
    const [rejecting, setRejecting] = useState(false);
    const [removing, setRemoving] = useState(false);

    return (
        <AppLayout>
            <Head title={r.requisition_no} />

            <div className="print:hidden">
                <PageHeader
                    eyebrow={<Link href={route('requisitions.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Requisitions</Link>}
                    title={r.requisition_no}
                    description={r.full_description}
                    icon={ScrollText}
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <RequisitionStatusBadge status={r.status} />
                    {r.work_order_id && (
                        <Link href={route('work-orders.show', r.work_order_id)} className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
                            <Wrench className="size-3.5" /> {r.wo_ref}
                        </Link>
                    )}
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {flow.canApprove && can('requisitions.approve') && (
                            <>
                                <Button onClick={() => setApproving(true)}><Check className="size-4" /> Approve</Button>
                                <Button variant="destructive" onClick={() => setRejecting(true)}><X className="size-4" /> Reject</Button>
                            </>
                        )}
                        {flow.canRemoval && can('requisitions.create') && (
                            <Button variant="navy" onClick={() => setRemoving(true)}><Wrench className="size-4" /> Record removal</Button>
                        )}
                        <Button variant="outline" onClick={() => window.print()}><Printer className="size-4" /> Print</Button>
                        <Button asChild variant="outline">
                            <a href={route('requisitions.pdf', r.id)} target="_blank" rel="noopener noreferrer"><FileDown className="size-4" /> PDF</a>
                        </Button>
                    </div>
                </div>

                {r.approval_remarks && (
                    <div className="mb-4 rounded-lg border border-border bg-muted/40 p-3 text-sm text-ncat-navy">
                        <span className="font-semibold">Approval remarks: </span>{r.approval_remarks}
                    </div>
                )}
            </div>

            {/* Paper sheet */}
            <div className="mx-auto max-w-4xl bg-white text-ncat-navy shadow-glass print:max-w-none print:shadow-none">
                <div className="border-2 border-ncat-navy print:border-black">
                    {/* Header */}
                    <div className="grid grid-cols-3 border-b-2 border-ncat-navy print:border-black">
                        <div className="col-span-2 border-r border-ncat-navy p-3 text-center print:border-black">
                            <div className="font-display text-lg font-bold uppercase leading-tight">Nigerian College of Aviation Technology, Zaria</div>
                            <div className="text-xs font-semibold uppercase">Flight Maintenance Department</div>
                            <div className="text-xs font-semibold uppercase">Aircraft Spare Parts Requisition Sheet</div>
                            <div className="mt-1 text-[0.7rem] font-semibold uppercase tracking-wide">(One voucher per unit)</div>
                        </div>
                        <div className="grid grid-rows-2">
                            <div className="flex items-baseline gap-2 border-b border-ncat-navy px-3 py-2 print:border-black">
                                <span className="text-xs font-semibold uppercase">No.</span>
                                <span className="font-mono text-xl font-bold tracking-wide">{r.requisition_no}</span>
                            </div>
                            <div className="flex items-baseline gap-2 px-3 py-2">
                                <span className="text-xs font-semibold uppercase">Date</span>
                                <span className="text-sm font-medium">{r.date || ' '}</span>
                            </div>
                        </div>
                    </div>

                    {/* Required for */}
                    <div className="border-b border-ncat-navy px-2 py-1 text-sm font-bold uppercase print:border-black">Required for:</div>
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b border-ncat-navy sm:grid-cols-5 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={1} label="Aircraft Reg" value={r.aircraft_reg} />
                        <Box n={2} label="Engine Ser. No." value={r.engine_serial_no} />
                        <Box n={3} label="Position" value={r.position} />
                        <Box n={4} label="Authorised By" value={r.authorised_by} />
                        <Box label="Supply Source" value={r.supply_source} />
                    </div>

                    {/* Description / Part no */}
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b border-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={5} label="Full Description" value={r.full_description} className="min-h-[4rem]" />
                        <Box n={6} label="Part No." value={r.part_no} className="min-h-[4rem]" />
                    </div>

                    {/* Stock code / serial / batch / bin */}
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b border-ncat-navy sm:grid-cols-4 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={7} label="Stock Code" value={r.stock_code} />
                        <Box n={8} label="Serial Number" value={r.serial_number} />
                        <Box n={9} label="Batch No / Year" value={[r.batch_no, r.batch_year].filter(Boolean).join(' / ')} />
                        <Box n={10} label="Bin / Bal Line No." value={r.bin_bal_line_no} />
                    </div>

                    {/* Issued by / serviced by */}
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b-2 border-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={11} label="Serviceable Unit Issued By (Storeman)" value={r.issued_by} />
                        <Box n={12} label="Unit Serviced By / Date">
                            {[r.unit_serviced_by, r.unit_serviced_date].filter(Boolean).join(' · ') || ' '}
                        </Box>
                    </div>

                    {/* Removal information */}
                    <div className="border-b border-ncat-navy px-2 py-1 text-sm font-bold uppercase print:border-black">
                        Removal Information <span className="text-[0.65rem] font-semibold">(must be completed by aircraft technician)</span>
                    </div>
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b border-ncat-navy sm:grid-cols-4 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={13} label="Serial No Removed" value={r.serial_no_removed} className="sm:col-span-2" />
                        <Box n={14} label="Zone" value={r.removal_zone} />
                        <Box n={15} label="Unit Changed By (block capitals)" value={r.unit_changed_by} />
                    </div>
                    <Box n={16} label="Reason for Removal" value={r.reason_for_removal} className="min-h-[4rem] border-b border-ncat-navy print:border-black" />
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b-2 border-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <Box n={17} label="Signature" value={r.removal_signature} />
                        <Box label="Date" value={r.removal_date} />
                    </div>

                    {/* Repair block + distribution */}
                    <div className="grid grid-cols-1 sm:grid-cols-3">
                        <div className="divide-y divide-ncat-navy sm:col-span-2 sm:border-r sm:border-ncat-navy print:divide-black print:border-black">
                            <Box n={18} label="Repair Facility / Work Shop" value={r.repair_facility} />
                            <div className="grid grid-cols-1 divide-y divide-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black">
                                <Box n={19} label="Date Sent" value={r.date_sent} />
                                <Box n={20} label="Repair Order (if applicable)" value={r.repair_order_ref} />
                            </div>
                        </div>
                        <div className="border-t border-ncat-navy p-2 text-center text-[0.7rem] font-semibold uppercase leading-snug sm:border-t-0 print:border-black">
                            This copy to:<br />
                            Stores / Progress / Shop<br />
                            Store / Conp. Planning and<br />
                            Chief Inspector
                        </div>
                    </div>
                </div>

                <div className="mt-2 flex justify-between px-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">
                    <span>Use ballpoint pen — lean heavily</span>
                    {r.requested_by && <span>Raised by: {r.requested_by}</span>}
                </div>
            </div>

            {/* Approve modal */}
            {approving && <ApproveModal requisition={r} onClose={() => setApproving(false)} />}
            {rejecting && <RejectModal requisition={r} onClose={() => setRejecting(false)} />}
            {removing && <RemovalModal requisition={r} onClose={() => setRemoving(false)} />}

            <style>{`@media print {
                body { background: #fff; }
                @page { margin: 12mm; }
            }`}</style>
        </AppLayout>
    );
}

function ApproveModal({ requisition, onClose }) {
    const form = useForm({ remarks: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('requisitions.approve', requisition.id), { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-w-md">
                <ModalHeader><ModalTitle>Approve requisition</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Remarks (optional)</Label>
                        <textarea rows={3} className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        {form.errors.approval && <p className="text-sm text-destructive">{form.errors.approval}</p>}
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}><Check className="size-4" /> Approve</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function RejectModal({ requisition, onClose }) {
    const form = useForm({ remarks: '' });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('requisitions.reject', requisition.id), { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-w-md">
                <ModalHeader><ModalTitle>Reject requisition</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Reason for rejection</Label>
                        <textarea rows={3} required className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} />
                        {form.errors.remarks && <p className="text-sm text-destructive">{form.errors.remarks}</p>}
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" variant="destructive" disabled={form.processing}><X className="size-4" /> Reject</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function RemovalModal({ requisition, onClose }) {
    const form = useForm({
        serial_no_removed: '',
        removal_zone: '',
        unit_changed_by: '',
        reason_for_removal: '',
        removal_signature: '',
        removal_date: new Date().toISOString().slice(0, 10),
        repair_facility: '',
        date_sent: '',
        repair_order_ref: '',
        disposition: 'at_repair',
    });
    const submit = (e) => {
        e.preventDefault();
        form.post(route('requisitions.removal', requisition.id), { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <ModalHeader><ModalTitle>Record removal information</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Serial no. removed</Label>
                        <Input value={form.data.serial_no_removed} onChange={(e) => form.setData('serial_no_removed', e.target.value)} />
                        {form.errors.serial_no_removed && <p className="text-sm text-destructive">{form.errors.serial_no_removed}</p>}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Zone</Label>
                            <Input value={form.data.removal_zone} onChange={(e) => form.setData('removal_zone', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Unit changed by</Label>
                            <Input value={form.data.unit_changed_by} onChange={(e) => form.setData('unit_changed_by', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Reason for removal</Label>
                        <textarea rows={3} required className="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            value={form.data.reason_for_removal} onChange={(e) => form.setData('reason_for_removal', e.target.value)} />
                        {form.errors.reason_for_removal && <p className="text-sm text-destructive">{form.errors.reason_for_removal}</p>}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Signature</Label>
                            <Input value={form.data.removal_signature} onChange={(e) => form.setData('removal_signature', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Removal date</Label>
                            <Input type="date" value={form.data.removal_date} onChange={(e) => form.setData('removal_date', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Repair facility / workshop</Label>
                            <Input value={form.data.repair_facility} onChange={(e) => form.setData('repair_facility', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Date sent</Label>
                            <Input type="date" value={form.data.date_sent} onChange={(e) => form.setData('date_sent', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Repair order ref</Label>
                            <Input value={form.data.repair_order_ref} onChange={(e) => form.setData('repair_order_ref', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Disposition</Label>
                            <Select value={form.data.disposition} onChange={(e) => form.setData('disposition', e.target.value)}>
                                <option value="at_repair">At repair</option>
                                <option value="removed_unserviceable">Removed unserviceable</option>
                            </Select>
                        </div>
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Save removal</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, PackageCheck, PackageMinus, Printer } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter } from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function SivShow({ siv }) {
    const { can } = usePermissions();
    const [posting, setPosting] = useState(false);
    const isPosted = siv.status === 'posted';

    return (
        <AppLayout>
            <Head title={siv.siv_number} />

            <div className="print:hidden">
                <PageHeader
                    eyebrow={<Link href={route('issuing.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Issuing</Link>}
                    title={siv.siv_number}
                    description={siv.requisition_for ? `Requisition for: ${siv.requisition_for}` : 'Store Issue Voucher'}
                    icon={PackageMinus}
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Badge variant={isPosted ? 'success' : 'neutral'}>{isPosted ? 'Posted' : 'Draft'}</Badge>
                    {isPosted && (siv.posted_at || siv.posted_by) && (
                        <span className="text-sm text-muted-foreground">
                            {siv.posted_by && <>by <span className="font-medium text-ncat-navy">{siv.posted_by}</span></>}
                            {siv.posted_at && <> · {siv.posted_at}</>}
                        </span>
                    )}
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {!isPosted && can('issues.post') && (
                            <Button onClick={() => setPosting(true)}><PackageCheck className="size-4" /> Post SIV</Button>
                        )}
                        <Button variant="outline" onClick={() => window.print()}><Printer className="size-4" /> Print</Button>
                    </div>
                </div>
            </div>

            {/* Paper sheet */}
            <div className="mx-auto max-w-4xl bg-white text-ncat-navy shadow-glass print:max-w-none print:shadow-none">
                <div className="border-2 border-ncat-navy print:border-black">
                    {/* Header */}
                    <div className="border-b-2 border-ncat-navy p-3 text-center print:border-black">
                        <div className="font-display text-lg font-bold uppercase leading-tight">Nigerian College of Aviation Technology, Zaria.</div>
                        <div className="text-xs font-semibold uppercase">Flight Maintenance Department</div>
                        <div className="mt-1 text-sm font-bold uppercase tracking-wide">Store Issue Voucher</div>
                    </div>

                    {/* Requisition / voucher meta */}
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b-2 border-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        {/* Left block */}
                        <div className="space-y-2 p-3">
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Requisition For</div>
                                <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{siv.requisition_for || ' '}</div>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Ordered By (Name / Signature)</div>
                                <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{siv.ordered_by || ' '}</div>
                                <div className="mt-0.5 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {siv.ordered_by_date || ''}</div>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">School / Section</div>
                                <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{siv.school_section || ' '}</div>
                            </div>
                        </div>

                        {/* Right block */}
                        <div className="space-y-2 p-3">
                            <div className="flex items-baseline gap-2">
                                <span className="text-[0.65rem] font-semibold uppercase tracking-wide">Store Issue Voucher No.</span>
                                <span className="font-mono text-lg font-bold tracking-wide">{siv.siv_number}</span>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Approved By</div>
                                <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{siv.approved_by || ' '}</div>
                                <div className="mt-0.5 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {siv.approved_by_date || ''}</div>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Entered By</div>
                                <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{siv.entered_by || ' '}</div>
                                <div className="mt-0.5 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {siv.entered_by_date || ''}</div>
                            </div>
                        </div>
                    </div>

                    {/* Items table */}
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr className="border-b-2 border-ncat-navy text-left uppercase print:border-black">
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Item No</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Part No</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Description</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Qty Required</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Qty Issued</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Stores Folio</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Rate</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Amount (₦/K)</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Charging Code</th>
                                    <th className="p-1.5 font-semibold">Req</th>
                                </tr>
                            </thead>
                            <tbody>
                                {siv.items.map((it) => (
                                    <tr key={it.id} className="border-b border-ncat-navy align-top print:border-black">
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.line_no}</td>
                                        <td className="border-r border-ncat-navy p-1.5 font-mono print:border-black">{it.part_number}</td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">
                                            <div className="font-medium">{it.description}</div>
                                            <div className="mt-0.5 text-[0.65rem] uppercase text-muted-foreground print:text-black">
                                                From: {it.store || '—'}
                                            </div>
                                        </td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.qty_required}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">
                                            {it.qty_issued}
                                            {it.qty_issued_words && (
                                                <div className="text-[0.65rem] italic text-muted-foreground print:text-black">({it.qty_issued_words})</div>
                                            )}
                                        </td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">{it.stores_folio || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.rate || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.amount || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">{it.charging_code || ' '}</td>
                                        <td className="p-1.5 font-mono">{it.requisition_no || ' '}</td>
                                    </tr>
                                ))}
                                {!siv.items.length && (
                                    <tr><td colSpan={10} className="p-4 text-center text-muted-foreground print:text-black">No items on this voucher.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Certification footer */}
                    <div className="border-t-2 border-ncat-navy p-3 print:border-black">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Issued By</div>
                                <div className="mt-0.5 min-h-[1.25rem] border-b border-ncat-navy pb-0.5 text-sm font-medium print:border-black">{siv.issued_by || ' '}</div>
                                <div className="mt-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {siv.issued_by_date || ''}</div>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Received By</div>
                                <div className="mt-0.5 min-h-[1.25rem] border-b border-ncat-navy pb-0.5 text-sm font-medium print:border-black">{siv.received_by || ' '}</div>
                                <div className="mt-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {siv.received_by_date || ''}</div>
                            </div>
                        </div>
                        {siv.remark && (
                            <div className="mt-3 border-t border-ncat-navy pt-2 print:border-black">
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Remark</div>
                                <div className="mt-0.5 text-sm font-medium">{siv.remark}</div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {posting && <PostModal siv={siv} onClose={() => setPosting(false)} />}

            <style>{`@media print {
                body { background: #fff; }
                @page { margin: 12mm; }
            }`}</style>
        </AppLayout>
    );
}

function PostModal({ siv, onClose }) {
    const [processing, setProcessing] = useState(false);
    const submit = () => {
        setProcessing(true);
        router.post(route('issuing.post', siv.id), {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
        });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-w-md">
                <ModalHeader><ModalTitle>Post SIV {siv.siv_number}</ModalTitle></ModalHeader>
                <p className="text-sm text-muted-foreground">
                    Posting is irreversible and issues the stock. Continue?
                </p>
                <ModalFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={processing}>Cancel</Button>
                    <Button type="button" onClick={submit} disabled={processing}><PackageCheck className="size-4" /> Post SIV</Button>
                </ModalFooter>
            </ModalContent>
        </Modal>
    );
}

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileDown, PackageCheck, PackagePlus, Printer } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter } from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function SrvShow({ srv }) {
    const { can } = usePermissions();
    const [posting, setPosting] = useState(false);
    const isPosted = srv.status === 'posted';

    return (
        <AppLayout>
            <Head title={srv.srv_number} />

            <div className="print:hidden">
                <PageHeader
                    eyebrow={<Link href={route('receiving.index')} className="inline-flex items-center gap-1 hover:text-primary"><ArrowLeft className="size-3" /> Receiving</Link>}
                    title={srv.srv_number}
                    description={srv.supplier ? `Supplier: ${srv.supplier}` : 'Store Receipt Voucher'}
                    icon={PackagePlus}
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Badge variant={isPosted ? 'success' : 'neutral'}>{isPosted ? 'Posted' : 'Draft'}</Badge>
                    {isPosted && (srv.posted_at || srv.posted_by) && (
                        <span className="text-sm text-muted-foreground">
                            {srv.posted_by && <>by <span className="font-medium text-ncat-navy">{srv.posted_by}</span></>}
                            {srv.posted_at && <> · {srv.posted_at}</>}
                        </span>
                    )}
                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {!isPosted && can('receiving.post') && (
                            <Button onClick={() => setPosting(true)}><PackageCheck className="size-4" /> Post SRV</Button>
                        )}
                        <Button variant="outline" onClick={() => window.print()}><Printer className="size-4" /> Print</Button>
                        <Button asChild variant="outline">
                            <a href={route('receiving.pdf', srv.id)} target="_blank" rel="noopener noreferrer"><FileDown className="size-4" /> PDF</a>
                        </Button>
                    </div>
                </div>
            </div>

            {/* Paper sheet */}
            <div className="mx-auto max-w-4xl bg-white text-ncat-navy shadow-glass print:max-w-none print:shadow-none">
                <div className="border-2 border-ncat-navy print:border-black">
                    {/* Header */}
                    <div className="grid grid-cols-3 border-b-2 border-ncat-navy print:border-black">
                        <div className="col-span-2 border-r border-ncat-navy p-3 text-center print:border-black">
                            <div className="font-display text-lg font-bold uppercase leading-tight">Nigerian College of Aviation Technology, Zaria</div>
                            <div className="text-xs font-semibold uppercase">Flight Maintenance Department</div>
                            <div className="mt-1 text-xs font-semibold uppercase">To the Storekeeper,</div>
                            <div className="mt-1 text-sm font-bold uppercase tracking-wide">Store Receipt Voucher</div>
                        </div>
                        <div className="grid grid-rows-2">
                            <div className="flex items-baseline gap-2 border-b border-ncat-navy px-3 py-2 print:border-black">
                                <span className="text-xs font-semibold uppercase">No.</span>
                                <span className="font-mono text-xl font-bold tracking-wide">{srv.srv_number}</span>
                            </div>
                            <div className="flex items-baseline gap-2 px-3 py-2">
                                <span className="text-xs font-semibold uppercase">Date</span>
                                <span className="text-sm font-medium">{srv.srv_date || ' '}</span>
                            </div>
                        </div>
                    </div>

                    {/* Instruction sentence */}
                    <div className="border-b border-ncat-navy p-3 text-sm print:border-black">
                        Please receive the following items into the{' '}
                        <span className="font-semibold uppercase">{srv.destination || '—'}</span> store and cross reference to
                        LPO / Petty Cash Voucher No.:{' '}
                        <span className="font-mono font-semibold">{srv.lpo_or_petty_cash_ref || '—'}</span>
                        {srv.purchase_order && (
                            <span className="mt-1 block print:hidden">
                                Received against purchase order{' '}
                                <Link
                                    href={route('purchase-orders.show', srv.purchase_order.id)}
                                    className="font-mono font-semibold text-primary hover:underline"
                                >
                                    {srv.purchase_order.po_number}
                                </Link>
                            </span>
                        )}
                    </div>

                    {/* Head of receiving dept. */}
                    <div className="grid grid-cols-1 divide-y divide-ncat-navy border-b border-ncat-navy sm:grid-cols-2 sm:divide-x sm:divide-y-0 print:divide-black print:border-black">
                        <div className="p-2">
                            <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Head of Receiving Dept.</div>
                            <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{srv.head_of_receiving_dept || ' '}</div>
                        </div>
                        <div className="p-2">
                            <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Supplier</div>
                            <div className="mt-0.5 min-h-[1.25rem] text-sm font-medium">{srv.supplier || ' '}</div>
                        </div>
                    </div>

                    {/* Items table */}
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr className="border-b-2 border-ncat-navy text-left uppercase print:border-black">
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Item No</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Quantity (Fig)</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Suppliers &amp; Details of Materials</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Part No</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">FOL No</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Rate</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Amount (₦/K)</th>
                                    <th className="border-r border-ncat-navy p-1.5 font-semibold print:border-black">Invoice</th>
                                    <th className="p-1.5 font-semibold">Acct Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                {srv.items.map((it) => (
                                    <tr key={it.id} className="border-b border-ncat-navy align-top print:border-black">
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.line_no}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.quantity}</td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">
                                            <div className="font-medium">{it.supplier_details || it.description}</div>
                                            {it.description && it.supplier_details && <div className="text-[0.65rem] text-muted-foreground print:text-black">{it.description}</div>}
                                            {it.serials && it.serials.length > 0 && (
                                                <div className="mt-0.5 text-[0.65rem]">S/N: {it.serials.join(', ')}</div>
                                            )}
                                            {(it.batch_no || it.batch_year || it.expiry_date) && (
                                                <div className="text-[0.65rem]">
                                                    {[it.batch_no && `Batch ${it.batch_no}`, it.batch_year, it.expiry_date && `Exp ${it.expiry_date}`].filter(Boolean).join(' · ')}
                                                </div>
                                            )}
                                        </td>
                                        <td className="border-r border-ncat-navy p-1.5 font-mono print:border-black">{it.part_number}</td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">{it.fol_no || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.rate || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 tabular-nums print:border-black">{it.amount || ' '}</td>
                                        <td className="border-r border-ncat-navy p-1.5 print:border-black">{it.invoice_no || ' '}</td>
                                        <td className="p-1.5">{it.acct_code || ' '}</td>
                                    </tr>
                                ))}
                                {!srv.items.length && (
                                    <tr><td colSpan={9} className="p-4 text-center text-muted-foreground print:text-black">No items on this voucher.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Certification footer */}
                    <div className="border-t-2 border-ncat-navy p-3 print:border-black">
                        <p className="text-xs">
                            I certify that the above mentioned goods were received in good order and condition are taken on stores charge:
                        </p>
                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Storekeeper</div>
                                <div className="mt-0.5 min-h-[1.25rem] border-b border-ncat-navy pb-0.5 text-sm font-medium print:border-black">{srv.storekeeper || ' '}</div>
                                <div className="mt-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date</div>
                            </div>
                            <div>
                                <div className="text-[0.65rem] font-semibold uppercase tracking-wide">Posted by</div>
                                <div className="mt-0.5 min-h-[1.25rem] border-b border-ncat-navy pb-0.5 text-sm font-medium print:border-black">{srv.posted_by || ' '}</div>
                                <div className="mt-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">Date {srv.posted_at || ''}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {srv.remarks && (
                    <div className="mt-2 px-1 text-[0.65rem] uppercase text-muted-foreground print:text-black">Remarks: {srv.remarks}</div>
                )}
            </div>

            {posting && <PostModal srv={srv} onClose={() => setPosting(false)} />}

            <style>{`@media print {
                body { background: #fff; }
                @page { margin: 12mm; }
            }`}</style>
        </AppLayout>
    );
}

function PostModal({ srv, onClose }) {
    const [processing, setProcessing] = useState(false);
    const submit = () => {
        setProcessing(true);
        router.post(route('receiving.post', srv.id), {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
        });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-w-md">
                <ModalHeader><ModalTitle>Post SRV {srv.srv_number}</ModalTitle></ModalHeader>
                <p className="text-sm text-muted-foreground">
                    Posting is irreversible and receives the stock into <span className="font-medium text-ncat-navy">{srv.destination}</span>. Continue?
                </p>
                <ModalFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={processing}>Cancel</Button>
                    <Button type="button" onClick={submit} disabled={processing}><PackageCheck className="size-4" /> Post SRV</Button>
                </ModalFooter>
            </ModalContent>
        </Modal>
    );
}

import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRightLeft, BadgeCheck, Scale, Search, Warehouse,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function StoreShow({ store, rows, filters, transferTargets }) {
    const { can } = usePermissions();
    const isQuarantine = store.type === 'quarantine';
    const [search, setSearch] = useState(filters.search || '');
    const [action, setAction] = useState(null); // { type, row }

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('stores.show', store.id), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={`${store.name} · Stores`} />
            <div className="mb-4">
                <Link href={route('stores.index')} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary">
                    <ArrowLeft className="size-4" /> Stores
                </Link>
            </div>
            <PageHeader
                eyebrow={isQuarantine ? 'Certification queue' : 'Store'}
                title={store.name}
                description={store.description}
                icon={Warehouse}
            />

            <form onSubmit={submitSearch} className="mb-4 max-w-sm">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Search part…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
            </form>

            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Part</TableHead>
                            <TableHead className="text-right">Qty</TableHead>
                            {isQuarantine && <TableHead>Received</TableHead>}
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((r) => (
                            <TableRow key={r.part_id}>
                                <TableCell>
                                    <Link href={route('parts.show', r.part_id)} className="font-semibold text-ncat-navy hover:text-primary">
                                        {r.part_number}
                                    </Link>
                                    <div className="text-xs text-muted-foreground">
                                        {r.description}
                                        {r.is_flammable && <Badge variant="error" className="ml-1">Flam</Badge>}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">{r.quantity} {r.unit_of_issue}</TableCell>
                                {isQuarantine && (
                                    <TableCell>
                                        <span className="text-sm">{r.received_at ?? '—'}</span>
                                        {r.aging_days >= 30 && <Badge variant="warning" className="ml-2">{r.aging_days}d</Badge>}
                                    </TableCell>
                                )}
                                <TableCell className="text-right">
                                    <div className="flex justify-end gap-1">
                                        {isQuarantine && can('quarantine.certify') && (
                                            <Button variant="ghost" size="sm" onClick={() => setAction({ type: 'certify', row: r })}>
                                                <BadgeCheck className="size-4" /> Certify
                                            </Button>
                                        )}
                                        {!isQuarantine && can('stock.transfer') && (
                                            <Button variant="ghost" size="sm" onClick={() => setAction({ type: 'transfer', row: r })}>
                                                <ArrowRightLeft className="size-4" /> Transfer
                                            </Button>
                                        )}
                                        {can('stock.adjust') && (
                                            <Button variant="ghost" size="sm" onClick={() => setAction({ type: 'adjust', row: r })}>
                                                <Scale className="size-4" /> Adjust
                                            </Button>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!rows.length && (
                    <p className="p-10 text-center text-sm text-muted-foreground">
                        {isQuarantine ? 'Nothing awaiting certification.' : 'No stock in this store.'}
                    </p>
                )}
            </Card>

            {action?.type === 'certify' && <CertifyModal row={action.row} onClose={() => setAction(null)} />}
            {action?.type === 'transfer' && <TransferModal row={action.row} store={store} targets={transferTargets} onClose={() => setAction(null)} />}
            {action?.type === 'adjust' && <AdjustModal row={action.row} store={store} onClose={() => setAction(null)} />}
        </AppLayout>
    );
}

function CertifyModal({ row, onClose }) {
    const form = useForm({
        part_id: row.part_id,
        quantity: row.quantity,
        decision: row.is_flammable ? 'release_to_dope' : 'release_to_bonded',
        remarks: '',
    });
    const submit = (e) => { e.preventDefault(); form.post(route('stock.certify'), { preserveScroll: true, onSuccess: onClose }); };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader>
                    <ModalTitle>Certify {row.part_number}</ModalTitle>
                    <ModalDescription>Release quarantined stock to a serviceable store, or reject it.</ModalDescription>
                </ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5"><Label>Quantity</Label><Input type="number" step="0.01" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} /></div>
                        <div className="space-y-1.5">
                            <Label>Decision</Label>
                            <Select value={form.data.decision} onChange={(e) => form.setData('decision', e.target.value)}>
                                <option value="release_to_bonded">Release to Bonded</option>
                                <option value="release_to_dope">Release to Dope{row.is_flammable ? ' (flammable)' : ''}</option>
                                <option value="reject">Reject / return</option>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1.5"><Label>Remarks</Label><Input value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} /></div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Post certification</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function TransferModal({ row, store, targets, onClose }) {
    const form = useForm({ part_id: row.part_id, from_store_id: store.id, to_store_id: targets[0]?.id || '', quantity: '', remarks: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('stock.transfer'), { preserveScroll: true, onSuccess: onClose }); };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>Transfer {row.part_number}</ModalTitle>
                    <ModalDescription>Available in {store.name}: {row.quantity} {row.unit_of_issue}</ModalDescription></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>To store</Label>
                        <Select value={form.data.to_store_id} onChange={(e) => form.setData('to_store_id', e.target.value)}>
                            {targets.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </Select>
                    </div>
                    <div className="space-y-1.5"><Label>Quantity</Label><Input type="number" step="0.01" max={row.quantity} value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} /></div>
                    <div className="space-y-1.5"><Label>Remarks</Label><Input value={form.data.remarks} onChange={(e) => form.setData('remarks', e.target.value)} /></div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Post transfer</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function AdjustModal({ row, store, onClose }) {
    const form = useForm({ part_id: row.part_id, store_id: store.id, delta: '', reason: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('stock.adjust'), { preserveScroll: true, onSuccess: onClose }); };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>Adjust {row.part_number}</ModalTitle>
                    <ModalDescription>Current in {store.name}: {row.quantity} {row.unit_of_issue}. Use a signed value (e.g. -2 or 3).</ModalDescription></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5"><Label>Signed quantity (delta)</Label><Input type="number" step="0.01" value={form.data.delta} onChange={(e) => form.setData('delta', e.target.value)} /></div>
                    <div className="space-y-1.5"><Label>Reason (required)</Label><Input value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></div>
                    {form.errors.reason && <p className="text-sm text-destructive">{form.errors.reason}</p>}
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Post adjustment</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

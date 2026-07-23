import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Boxes, Filter, Package, Pencil, Plus, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { StockStateBadge } from '@/Components/stock/StockStateBadge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function PartsIndex({ parts, stores, ataChapters, types, filters }) {
    const { can } = usePermissions();
    const canManage = can('parts.manage');
    const [f, setF] = useState(filters);
    const [editing, setEditing] = useState(null);

    const apply = (next = f) => router.get(route('parts.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Parts Catalogue" />
            <PageHeader
                eyebrow="Catalogue"
                title="Parts Catalogue"
                description="Every part, its levels, and on-hand balances per store."
                icon={Boxes}
                actions={canManage && <Button onClick={() => setEditing('new')}><Plus className="size-4" /> Add part</Button>}
            />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search part no / description…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.ata} onChange={(e) => set('ata', e.target.value)}>
                        <option value="">All ATA</option>
                        {ataChapters.map((a) => <option key={a.id} value={a.id}>{a.chapter_number} - {a.title}</option>)}
                    </Select>
                    <Select value={f.type} onChange={(e) => set('type', e.target.value)}>
                        <option value="">All types</option>
                        {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </Select>
                    <Select value={f.state} onChange={(e) => set('state', e.target.value)}>
                        <option value="">Any state</option>
                        <option value="ok">OK</option>
                        <option value="below_reorder">Below reorder</option>
                        <option value="below_min">Below min</option>
                        <option value="above_max">Above max</option>
                        <option value="expiring">Expiring</option>
                    </Select>
                </div>
            </Card>

            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Part</TableHead>
                            <TableHead>ATA</TableHead>
                            <TableHead>State</TableHead>
                            {stores.map((s) => <TableHead key={s.id} className="text-right">{s.name.split(' ')[0]}</TableHead>)}
                            <TableHead className="text-right">On hand</TableHead>
                            {canManage && <TableHead />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {parts.map((p) => (
                            <TableRow key={p.id}>
                                <TableCell>
                                    <Link href={route('parts.show', p.id)} className="font-semibold text-ncat-navy hover:text-primary">
                                        {p.part_number}
                                    </Link>
                                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                        {p.description}
                                        {p.is_serialized && <Badge variant="neutral" className="ml-1">S/N</Badge>}
                                        {p.is_flammable && <Badge variant="error">Flam</Badge>}
                                        {p.is_fuel && <Badge variant="info">Fuel</Badge>}
                                    </div>
                                </TableCell>
                                <TableCell className="font-mono text-sm">{p.ata ?? '—'}</TableCell>
                                <TableCell><StockStateBadge state={p.state} /></TableCell>
                                {stores.map((s) => (
                                    <TableCell key={s.id} className="text-right tabular-nums">
                                        {p.balances[s.slug] ? p.balances[s.slug] : <span className="text-muted-foreground/50">—</span>}
                                    </TableCell>
                                ))}
                                <TableCell className="text-right font-semibold tabular-nums">{p.total_on_hand}</TableCell>
                                {canManage && (
                                    <TableCell className="text-right">
                                        <Button variant="ghost" size="sm" onClick={() => setEditing(p)}>
                                            <Pencil className="size-4" />
                                        </Button>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!parts.length && (
                    <div className="p-10 text-center">
                        <Package className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No parts yet. Add parts or import opening balances.</p>
                    </div>
                )}
            </Card>

            {editing && (
                <PartFormModal
                    part={editing === 'new' ? null : editing}
                    ataChapters={ataChapters} types={types}
                    onClose={() => setEditing(null)}
                />
            )}
        </AppLayout>
    );
}

const BOOL_FLAGS = [
    ['is_serialized', 'Serialized (tracked by serial no.)'],
    ['has_shelf_life', 'Has shelf life (batch expiry)'],
    ['is_flammable', 'Flammable (certifies to Dope)'],
    ['is_fuel', 'Bulk fuel (litres)'],
];

function PartFormModal({ part, ataChapters, types, onClose }) {
    const isNew = !part;
    const form = useForm({
        part_number: part?.part_number || '',
        description: part?.description || '',
        ata_chapter_id: part?.ata_chapter_id || '',
        aircraft_type_id: part?.aircraft_type_id || '',
        unit_of_issue: part?.unit_of_issue || 'EA',
        unit_price: part?.unit_price ?? '',
        bin_location: part?.bin_location || '',
        min_level: part?.min_level ?? 0,
        max_level: part?.max_level ?? '',
        reorder_level: part?.reorder_level ?? 0,
        is_serialized: part?.is_serialized || false,
        has_shelf_life: part?.has_shelf_life || false,
        is_flammable: part?.is_flammable || false,
        is_fuel: part?.is_fuel || false,
        stock_code: part?.stock_code || '',
        ledger_folio: part?.ledger_folio || '',
        notes: part?.notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        isNew ? form.post(route('parts.store'), opts) : form.put(route('parts.update', part.id), opts);
    };

    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <ModalHeader><ModalTitle>{isNew ? 'Add part' : `Edit ${part.part_number}`}</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>Part number</Label>
                            <Input value={form.data.part_number} onChange={(e) => form.setData('part_number', e.target.value)} />
                            {form.errors.part_number && <p className="text-sm text-destructive">{form.errors.part_number}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Unit of issue</Label>
                            <Input value={form.data.unit_of_issue} onChange={(e) => form.setData('unit_of_issue', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Description</Label>
                        <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        {form.errors.description && <p className="text-sm text-destructive">{form.errors.description}</p>}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label>ATA chapter</Label>
                            <Select value={form.data.ata_chapter_id} onChange={(e) => form.setData('ata_chapter_id', e.target.value)}>
                                <option value="">—</option>
                                {ataChapters.map((a) => <option key={a.id} value={a.id}>{a.chapter_number} - {a.title}</option>)}
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Aircraft type (blank = cross-type)</Label>
                            <Select value={form.data.aircraft_type_id} onChange={(e) => form.setData('aircraft_type_id', e.target.value)}>
                                <option value="">Cross-type</option>
                                {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <div className="space-y-1.5"><Label>Min</Label><Input type="number" step="0.01" value={form.data.min_level} onChange={(e) => form.setData('min_level', e.target.value)} /></div>
                        <div className="space-y-1.5"><Label>Reorder</Label><Input type="number" step="0.01" value={form.data.reorder_level} onChange={(e) => form.setData('reorder_level', e.target.value)} /></div>
                        <div className="space-y-1.5"><Label>Max</Label><Input type="number" step="0.01" value={form.data.max_level} onChange={(e) => form.setData('max_level', e.target.value)} /></div>
                        <div className="space-y-1.5"><Label>Unit ₦</Label><Input type="number" step="0.01" value={form.data.unit_price} onChange={(e) => form.setData('unit_price', e.target.value)} /></div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5"><Label>Stock code</Label><Input value={form.data.stock_code} onChange={(e) => form.setData('stock_code', e.target.value)} /></div>
                        <div className="space-y-1.5"><Label>Bin / location</Label><Input value={form.data.bin_location} onChange={(e) => form.setData('bin_location', e.target.value)} /></div>
                    </div>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {BOOL_FLAGS.map(([key, label]) => (
                            <label key={key} className="flex cursor-pointer items-center gap-2.5 text-sm">
                                <input type="checkbox" checked={form.data[key]} onChange={(e) => form.setData(key, e.target.checked)}
                                    className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40" />
                                {label}
                            </label>
                        ))}
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{isNew ? 'Add part' : 'Save'}</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

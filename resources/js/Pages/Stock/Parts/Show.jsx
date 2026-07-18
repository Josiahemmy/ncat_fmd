import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Boxes } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { StockStateBadge } from '@/Components/stock/StockStateBadge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { cn } from '@/lib/utils';

const TABS = ['Overview', 'Batches', 'Serials', 'Movements'];

export default function PartShow({ part, batches, serials, movements, stores }) {
    const [tab, setTab] = useState('Overview');

    return (
        <AppLayout>
            <Head title={`${part.part_number} · Parts`} />
            <div className="mb-4">
                <Link href={route('parts.index')} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary">
                    <ArrowLeft className="size-4" /> Catalogue
                </Link>
            </div>
            <PageHeader
                eyebrow={part.type ? `${part.type} · ATA ${part.ata ?? '—'}` : `ATA ${part.ata ?? '—'}`}
                title={part.part_number}
                description={part.description}
                icon={Boxes}
                actions={(
                    <div className="flex items-center gap-2">
                        <StockStateBadge state={part.state} />
                        <Link href={route('tally-cards.show', part.id)}>
                            <Button variant="outline" size="sm">Tally card</Button>
                        </Link>
                    </div>
                )}
            />

            {/* Per-store balance strip */}
            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                {stores.map((s) => (
                    <Card key={s.id} variant="glass" className="p-4">
                        <p className="text-xs font-medium text-muted-foreground">{s.name}</p>
                        <p className="mt-1 font-display text-2xl font-bold text-ncat-navy tabular-nums">{part.balances[s.slug] ?? 0}</p>
                    </Card>
                ))}
                <Card className="border-primary/30 bg-primary/5 p-4">
                    <p className="text-xs font-medium text-primary">Total on hand</p>
                    <p className="mt-1 font-display text-2xl font-bold text-primary tabular-nums">{part.total_on_hand}</p>
                </Card>
            </div>

            <div className="mb-4 flex gap-1 border-b border-border">
                {TABS.map((t) => (
                    <button key={t} onClick={() => setTab(t)}
                        className={cn('border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                            tab === t ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground')}>
                        {t}
                    </button>
                ))}
            </div>

            {tab === 'Overview' && (
                <Card><CardContent className="grid gap-x-8 gap-y-3 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    {[
                        ['Part number', part.part_number], ['Stock code', part.stock_code || '—'],
                        ['Ledger folio', part.ledger_folio || '—'], ['Unit of issue', part.unit_of_issue],
                        ['Unit price', part.unit_price != null ? `₦${part.unit_price}` : '—'], ['Bin / location', part.bin_location || '—'],
                        ['Min level', part.min_level], ['Reorder level', part.reorder_level], ['Max level', part.max_level ?? '—'],
                    ].map(([k, v]) => (
                        <div key={k}>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{k}</dt>
                            <dd className="mt-0.5 font-medium text-ncat-navy">{v}</dd>
                        </div>
                    ))}
                    <div className="sm:col-span-2 lg:col-span-3">
                        <div className="flex flex-wrap gap-2">
                            {part.is_serialized && <Badge variant="neutral">Serialized</Badge>}
                            {part.has_shelf_life && <Badge variant="warning">Shelf life</Badge>}
                            {part.is_flammable && <Badge variant="error">Flammable</Badge>}
                            {part.is_fuel && <Badge variant="info">Fuel</Badge>}
                        </div>
                    </div>
                </CardContent></Card>
            )}

            {tab === 'Batches' && (
                <Card className="overflow-hidden">
                    <Table>
                        <TableHeader><TableRow>
                            <TableHead>Batch</TableHead><TableHead>Year</TableHead><TableHead>Expiry</TableHead>
                            <TableHead className="text-right">Qty received</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>
                            {batches.map((b) => (
                                <TableRow key={b.id}>
                                    <TableCell className="font-medium">{b.batch_number}</TableCell>
                                    <TableCell>{b.batch_year ?? '—'}</TableCell>
                                    <TableCell>
                                        {b.expiry_date ?? '—'}
                                        {b.expired && <Badge variant="error" className="ml-2">Expired</Badge>}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">{b.qty_received}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!batches.length && <p className="p-6 text-center text-sm text-muted-foreground">No batches.</p>}
                </Card>
            )}

            {tab === 'Serials' && (
                <Card className="overflow-hidden">
                    <Table>
                        <TableHeader><TableRow>
                            <TableHead>Serial no.</TableHead><TableHead>Status</TableHead><TableHead>Location</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>
                            {serials.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell className="font-medium">{s.serial_number}</TableCell>
                                    <TableCell><Badge variant="neutral">{s.status}</Badge></TableCell>
                                    <TableCell>{s.store ?? (s.position || '—')}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!serials.length && <p className="p-6 text-center text-sm text-muted-foreground">No serials.</p>}
                </Card>
            )}

            {tab === 'Movements' && (
                <Card className="overflow-hidden">
                    <Table>
                        <TableHeader><TableRow>
                            <TableHead>Date</TableHead><TableHead>Store</TableHead><TableHead>Type</TableHead>
                            <TableHead className="text-right">In</TableHead><TableHead className="text-right">Out</TableHead>
                            <TableHead className="text-right">Balance</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>
                            {movements.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{m.date}</TableCell>
                                    <TableCell>{m.store}</TableCell>
                                    <TableCell><Badge variant="neutral">{m.type}</Badge></TableCell>
                                    <TableCell className="text-right tabular-nums text-success">{m.direction === 'in' ? m.quantity : ''}</TableCell>
                                    <TableCell className="text-right tabular-nums text-destructive">{m.direction === 'out' ? m.quantity : ''}</TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">{m.balance_after}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!movements.length && <p className="p-6 text-center text-sm text-muted-foreground">No movements yet.</p>}
                </Card>
            )}
        </AppLayout>
    );
}

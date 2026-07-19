import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BookOpenCheck, FileDown, Printer } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { Select } from '@/Components/ui/Select';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';

export default function TallyShow({ part, stores, currentStore, card, filters }) {
    const reload = (patch) => router.get(route('tally-cards.show', part.id),
        { ...filters, ...patch }, { preserveState: true, replace: true });

    const consolidated = card?.consolidated === true;
    const leadCols = consolidated ? 3 : 2;
    const emptyCols = consolidated ? 9 : 8;

    const Field = ({ label, value }) => (
        <div className="flex gap-2 border-b border-ncat-navy/20 py-1">
            <span className="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</span>
            <span className="font-medium text-ncat-navy">{value ?? '—'}</span>
        </div>
    );

    return (
        <AppLayout>
            <Head title={`Tally · ${part.part_number}`} />

            <div className="mb-4 flex items-center justify-between print:hidden">
                <Link href={route('tally-cards.index')} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary">
                    <ArrowLeft className="size-4" /> Tally cards
                </Link>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => window.print()}><Printer className="size-4" /> Print</Button>
                    <Button asChild variant="outline" size="sm">
                        <a
                            href={route('tally-cards.pdf', { part: part.id, store: filters.store, from: filters.from, to: filters.to })}
                            target="_blank"
                            rel="noopener noreferrer"
                        ><FileDown className="size-4" /> PDF</a>
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <Card className="mb-4 p-4 print:hidden">
                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="space-y-1.5">
                        <Label>Store</Label>
                        <Select value={filters.store || ''} onChange={(e) => reload({ store: e.target.value })}>
                            <option value="all">All stores (consolidated)</option>
                            {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </Select>
                    </div>
                    <div className="space-y-1.5"><Label>From</Label><Input type="date" value={filters.from || ''} onChange={(e) => reload({ from: e.target.value })} /></div>
                    <div className="space-y-1.5"><Label>To</Label><Input type="date" value={filters.to || ''} onChange={(e) => reload({ to: e.target.value })} /></div>
                </div>
            </Card>

            {/* AD38 card */}
            <div className="rounded-lg border-2 border-ncat-navy bg-white p-6 shadow-glass print:border print:shadow-none">
                <div className="mb-1 text-center">
                    <h2 className="font-display text-lg font-bold uppercase tracking-wide text-ncat-navy">Nigerian College of Aviation Technology, Zaria</h2>
                    <p className="text-sm font-semibold uppercase tracking-[0.3em] text-primary">Tally Card</p>
                    {consolidated ? (
                        <div className="mt-1 flex justify-center">
                            <Badge variant="warning">Consolidated · non-AD38</Badge>
                        </div>
                    ) : (
                        currentStore && <p className="text-xs text-muted-foreground">{currentStore.name}</p>
                    )}
                </div>

                <div className="mb-4 grid gap-x-8 gap-y-0 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Description" value={part.description} />
                    <Field label="Ledger Folio" value={part.ledger_folio} />
                    <div className="row-span-3 hidden lg:block">
                        <div className="rounded-md border border-ncat-navy/30 p-2">
                            <p className="mb-1 text-center text-[11px] font-bold uppercase text-ncat-navy">Levels</p>
                            <div className="flex justify-between text-sm"><span className="font-semibold">MAX</span><span>{part.max_level ?? '—'}</span></div>
                            <div className="flex justify-between text-sm"><span className="font-semibold">MIN</span><span>{part.min_level}</span></div>
                            <div className="flex justify-between text-sm"><span className="font-semibold">REORDER</span><span>{part.reorder_level}</span></div>
                        </div>
                    </div>
                    <Field label="Part No." value={part.part_number} />
                    <Field label="ATA" value={part.ata} />
                    <Field label="Location" value={part.bin_location} />
                    <Field label="Unit of Issue" value={part.unit_of_issue} />
                    <Field label="Unit Price" value={part.unit_price != null ? `₦${part.unit_price}` : null} />
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-y-2 border-ncat-navy text-xs uppercase text-ncat-navy [&>th]:px-2 [&>th]:py-1.5 [&>th]:text-left">
                                <th>Date</th><th>Voucher No.</th>
                                {consolidated && <th>Store</th>}
                                <th>Particulars</th>
                                <th className="text-right">Received</th><th className="text-right">Issued</th>
                                <th className="text-right">Balance</th><th>Sign.</th><th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody className="[&>tr]:border-b [&>tr]:border-ncat-silver [&>tr>td]:px-2 [&>tr>td]:py-1.5">
                            {card ? (
                                <>
                                    <tr className="bg-ncat-mist font-semibold">
                                        <td colSpan={leadCols} />
                                        <td className="font-bold">B/F</td>
                                        <td /><td />
                                        <td className="text-right tabular-nums">{card.brought_forward}</td>
                                        <td /><td />
                                    </tr>
                                    {card.lines.map((l, i) => (
                                        <tr key={i}>
                                            <td className="whitespace-nowrap">{l.date}</td>
                                            <td>{l.reference}</td>
                                            {consolidated && <td className="whitespace-nowrap">{l.store ?? '—'}</td>}
                                            <td>{l.particulars}</td>
                                            <td className="text-right tabular-nums text-success">{l.received ?? ''}</td>
                                            <td className="text-right tabular-nums text-destructive">{l.issued ?? ''}</td>
                                            <td className="text-right font-semibold tabular-nums">{l.balance}</td>
                                            <td className="text-xs text-muted-foreground">{l.user}</td>
                                            <td className="text-xs">{l.remarks}</td>
                                        </tr>
                                    ))}
                                    <tr className="border-t-2 border-ncat-navy bg-ncat-mist font-bold">
                                        <td colSpan={leadCols} />
                                        <td>TOTALS C/F</td>
                                        <td className="text-right tabular-nums">{card.total_received}</td>
                                        <td className="text-right tabular-nums">{card.total_issued}</td>
                                        <td className="text-right tabular-nums">{card.carried_forward}</td>
                                        <td /><td />
                                    </tr>
                                </>
                            ) : (
                                <tr><td colSpan={emptyCols} className="py-8 text-center text-muted-foreground">No movements for this part in any store yet.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <p className="mt-3 text-right text-[10px] text-muted-foreground">AD38</p>
            </div>
        </AppLayout>
    );
}

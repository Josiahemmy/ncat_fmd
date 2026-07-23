import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Fuel, PlaneTakeoff, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';

export default function FuelIndex({ fuels, aircraft, movements = [] }) {
    const { can } = usePermissions();
    const canPost = can('fuel.post');

    return (
        <AppLayout>
            <Head title="Fuel Dump" />
            <div className="mb-4">
                <Link href={route('stores.index')} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary">
                    <ArrowLeft className="size-4" /> Stores
                </Link>
            </div>
            <PageHeader
                eyebrow="Operations"
                title="Fuel Dump"
                description="Aviation fuel received and issued in litres, tagged to aircraft."
                icon={Fuel}
            />

            {/* Level gauges */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {fuels.map((f) => {
                    const low = f.reorder_level > 0 && f.level <= f.reorder_level;
                    return (
                        <Card key={f.id} variant="glass" className="relative overflow-hidden p-6">
                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ncat-cyan/15 to-transparent" style={{ height: '40%' }} />
                            <div className="relative">
                                <div className="flex items-start justify-between">
                                    <span className="flex size-11 items-center justify-center rounded-xl bg-ncat-navy text-white"><Fuel className="size-5" /></span>
                                    {low && <Badge variant="error">Low</Badge>}
                                </div>
                                <p className="mt-4 text-sm text-muted-foreground">{f.description}</p>
                                <p className="mt-1 font-display text-4xl font-bold text-ncat-navy tabular-nums">
                                    {f.level.toLocaleString()}<span className="ml-1 text-lg font-semibold text-muted-foreground">L</span>
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">{f.part_number}{f.reorder_level > 0 ? ` · reorder ${f.reorder_level.toLocaleString()} L` : ''}</p>
                            </div>
                        </Card>
                    );
                })}
                {!fuels.length && (
                    <Card className="p-8 text-center text-sm text-muted-foreground sm:col-span-2 lg:col-span-3">
                        No fuel parts yet. Add a part flagged as “fuel”, then receive stock here.
                    </Card>
                )}
            </div>

            {canPost && fuels.length > 0 && (
                <div className="grid gap-6 lg:grid-cols-2">
                    <ReceiveFuel fuels={fuels} />
                    <IssueFuel fuels={fuels} aircraft={aircraft} />
                </div>
            )}

            {movements?.length > 0 && (
                <Card className="mt-6">
                    <CardHeader><CardTitle className="flex items-center gap-2"><Fuel className="size-4 text-primary" /> Recent fuel movements</CardTitle></CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[560px] text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        <th className="px-3 py-2.5">Date</th>
                                        <th className="px-3 py-2.5">Fuel</th>
                                        <th className="px-3 py-2.5">Movement</th>
                                        <th className="px-3 py-2.5 text-right">Litres</th>
                                        <th className="px-3 py-2.5">Reference</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {movements.map((m) => (
                                        <tr key={m.id} className="transition-colors hover:bg-accent">
                                            <td className="px-3 py-2.5 text-muted-foreground">{m.date}</td>
                                            <td className="px-3 py-2.5 font-medium text-ncat-navy">{m.part_number}</td>
                                            <td className="px-3 py-2.5">
                                                <Badge variant={m.direction === 'in' ? 'success' : 'warning'}>
                                                    {m.type === 'fuel_receive' ? 'Received' : 'Issued'}{m.aircraft ? ` · ${m.aircraft}` : ''}
                                                </Badge>
                                            </td>
                                            <td className={cn('px-3 py-2.5 text-right font-semibold', m.direction === 'in' ? 'text-success' : 'text-[hsl(30_65%_32%)]')}>
                                                {m.direction === 'in' ? '+' : '−'}{m.quantity.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-2.5">
                                                {m.link ? (
                                                    <Link href={m.link} className="font-medium text-primary hover:underline">{m.reference || 'View source'}</Link>
                                                ) : (
                                                    <span className="text-muted-foreground">{m.reference || '—'}</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}

function ReceiveFuel({ fuels }) {
    const form = useForm({ part_id: fuels[0]?.id || '', quantity: '', unit_price: '', reference: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('fuel.receive'), { preserveScroll: true, onSuccess: () => form.reset('quantity') }); };
    return (
        <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><Plus className="size-4 text-success" /> Receive fuel</CardTitle></CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Fuel</Label>
                        <Select value={form.data.part_id} onChange={(e) => form.setData('part_id', e.target.value)}>
                            {fuels.map((f) => <option key={f.id} value={f.id}>{f.description}</option>)}
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5"><Label>Litres</Label><Input type="number" step="0.01" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} /></div>
                        <div className="space-y-1.5"><Label>Unit price ₦/L</Label><Input type="number" step="0.01" value={form.data.unit_price} onChange={(e) => form.setData('unit_price', e.target.value)} /></div>
                    </div>
                    <div className="space-y-1.5"><Label>Supplier / reference</Label><Input value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} /></div>
                    <Button type="submit" disabled={form.processing}>Receive fuel</Button>
                </form>
            </CardContent>
        </Card>
    );
}

function IssueFuel({ fuels, aircraft }) {
    const form = useForm({ part_id: fuels[0]?.id || '', aircraft_id: aircraft[0]?.id || '', quantity: '', purpose: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('fuel.issue'), { preserveScroll: true, onSuccess: () => form.reset('quantity') }); };
    return (
        <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><PlaneTakeoff className="size-4 text-primary" /> Issue fuel</CardTitle></CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fuel</Label>
                            <Select value={form.data.part_id} onChange={(e) => form.setData('part_id', e.target.value)}>
                                {fuels.map((f) => <option key={f.id} value={f.id}>{f.description}</option>)}
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Aircraft</Label>
                            <Select value={form.data.aircraft_id} onChange={(e) => form.setData('aircraft_id', e.target.value)}>
                                {aircraft.map((a) => <option key={a.id} value={a.id}>{a.registration}</option>)}
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1.5"><Label>Litres</Label><Input type="number" step="0.01" value={form.data.quantity} onChange={(e) => form.setData('quantity', e.target.value)} /></div>
                    <div className="space-y-1.5"><Label>Purpose</Label><Input value={form.data.purpose} onChange={(e) => form.setData('purpose', e.target.value)} placeholder="e.g. Training sortie" /></div>
                    <Button type="submit" disabled={form.processing}>Issue fuel</Button>
                </form>
            </CardContent>
        </Card>
    );
}

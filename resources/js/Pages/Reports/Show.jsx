import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Download, FileText } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { EmptyState } from '@/Components/ui/EmptyState';

const STATE_OPTIONS = [
    { value: 'below_reorder', label: 'Below reorder' },
    { value: 'below_min', label: 'Below minimum' },
    { value: 'above_max', label: 'Above maximum' },
    { value: 'ok', label: 'OK' },
];

const SCOPE_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'expired', label: 'Expired' },
    { value: 'expiring', label: 'Expiring' },
];

/** Keep only filter entries that carry a value — for clean export URLs. */
function activeOnly(filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v != null && v !== ''),
    );
}

export default function ReportShow({
    report,
    title,
    columns = [],
    rows = [],
    truncatedAt,
    filters = {},
    options = {},
}) {
    const [f, setF] = useState(filters);
    const { stores = [], types = [], movementTypes = [] } = options;

    const apply = (next = f) =>
        router.get(route('reports.show', report), next, { preserveState: true, replace: true });
    const set = (k, v) => {
        const n = { ...f, [k]: v };
        setF(n);
        apply(n);
    };

    const active = activeOnly(f);
    const exportUrl = (format) =>
        route('reports.export', { report, format, ...active });

    const show = {
        store: report === 'stock-summary' || report === 'movements',
        state: report === 'stock-summary',
        movementType: report === 'movements',
        aircraftType: report === 'consumption',
        dates: report === 'movements' || report === 'consumption',
        scope: report === 'expiry',
    };
    const hasFilters =
        show.store || show.state || show.movementType || show.aircraftType || show.dates || show.scope;

    return (
        <AppLayout>
            <Head title={title} />

            <PageHeader
                eyebrow={
                    <Link href={route('reports')} className="inline-flex items-center gap-1 hover:text-primary">
                        <ArrowLeft className="size-3" /> Reports
                    </Link>
                }
                title={title}
                icon={BarChart3}
                actions={
                    <div className="flex flex-wrap items-center gap-2 print:hidden">
                        <Button asChild variant="outline">
                            <a href={exportUrl('csv')}><Download className="size-4" /> Export CSV</a>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={exportUrl('pdf')}><FileText className="size-4" /> Export PDF</a>
                        </Button>
                    </div>
                }
            />

            {hasFilters && (
                <Card className="mb-4 p-4 print:hidden">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {show.store && (
                            <div className="space-y-1.5">
                                <Label>Store</Label>
                                <Select value={f.store || ''} onChange={(e) => set('store', e.target.value)}>
                                    <option value="">All stores</option>
                                    {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </Select>
                            </div>
                        )}
                        {show.state && (
                            <div className="space-y-1.5">
                                <Label>State</Label>
                                <Select value={f.state || ''} onChange={(e) => set('state', e.target.value)}>
                                    <option value="">Any state</option>
                                    {STATE_OPTIONS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </Select>
                            </div>
                        )}
                        {show.movementType && (
                            <div className="space-y-1.5">
                                <Label>Movement type</Label>
                                <Select value={f.movementType || ''} onChange={(e) => set('movementType', e.target.value)}>
                                    <option value="">Any type</option>
                                    {movementTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                                </Select>
                            </div>
                        )}
                        {show.aircraftType && (
                            <div className="space-y-1.5">
                                <Label>Aircraft type</Label>
                                <Select value={f.aircraftType || ''} onChange={(e) => set('aircraftType', e.target.value)}>
                                    <option value="">All types</option>
                                    {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </Select>
                            </div>
                        )}
                        {show.scope && (
                            <>
                                <div className="space-y-1.5">
                                    <Label>Scope</Label>
                                    <Select value={f.scope || ''} onChange={(e) => set('scope', e.target.value)}>
                                        {SCOPE_OPTIONS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Days</Label>
                                    <Input
                                        type="number"
                                        value={f.days ?? ''}
                                        onChange={(e) => set('days', e.target.value)}
                                    />
                                </div>
                            </>
                        )}
                        {show.dates && (
                            <>
                                <div className="space-y-1.5">
                                    <Label>From</Label>
                                    <Input type="date" value={f.from || ''} onChange={(e) => set('from', e.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>To</Label>
                                    <Input type="date" value={f.to || ''} onChange={(e) => set('to', e.target.value)} />
                                </div>
                            </>
                        )}
                    </div>
                </Card>
            )}

            {rows.length ? (
                <Card className="overflow-x-auto">
                    <table className="w-full caption-bottom text-sm">
                        <thead className="[&_tr]:border-b [&_tr]:border-border">
                            <tr>
                                {columns.map((col) => {
                                    const numeric = rows.some((r) => typeof r[col] === 'number');
                                    return (
                                        <th
                                            key={col}
                                            className={`h-11 whitespace-nowrap bg-muted/40 px-4 align-middle text-xs font-semibold uppercase tracking-wide text-muted-foreground ${numeric ? 'text-right' : 'text-left'}`}
                                        >
                                            {col}
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody className="[&_tr:last-child]:border-0">
                            {rows.map((row, i) => (
                                <tr key={i} className="border-b border-border/70 transition-colors hover:bg-accent/60">
                                    {columns.map((col) => {
                                        const val = row[col];
                                        const numeric = typeof val === 'number';
                                        return (
                                            <td
                                                key={col}
                                                className={`px-4 py-3 align-middle text-foreground ${numeric ? 'text-right tabular-nums' : 'text-left'}`}
                                            >
                                                {numeric ? val.toLocaleString() : (val ?? '—')}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            ) : (
                <EmptyState
                    icon={BarChart3}
                    title="No data for this report"
                    description="Try adjusting the filters above, or export to CSV to inspect the full dataset."
                />
            )}

            {rows.length === truncatedAt && (
                <p className="mt-3 text-center text-xs text-muted-foreground">
                    Showing first {truncatedAt} rows. Export CSV for the full set.
                </p>
            )}
        </AppLayout>
    );
}

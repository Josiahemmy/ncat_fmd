import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PackagePlus, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import { usePermissions } from '@/lib/permissions';

export default function SrvIndex({ srvs, filters }) {
    const { can } = usePermissions();
    const [f, setF] = useState(filters);

    const apply = (next = f) => router.get(route('receiving.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Receiving - SRV" />
            <PageHeader
                eyebrow="Operations"
                title="Receiving - SRV"
                description="Store Receipt Vouchers for goods received into stores against LPO or petty cash."
                icon={PackagePlus}
                actions={can('receiving.post') && (
                    <Button onClick={() => router.visit(route('receiving.create'))}>
                        <PackagePlus className="size-4" /> New SRV
                    </Button>
                )}
            />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search SRV no. / supplier…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.status} onChange={(e) => set('status', e.target.value)}>
                        <option value="">Any status</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                    </Select>
                </div>
            </Card>

            <Card className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>SRV No.</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Supplier</TableHead>
                            <TableHead>Destination</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {srvs.map((s) => (
                            <TableRow key={s.id}>
                                <TableCell className="whitespace-nowrap font-mono text-sm">
                                    <Link href={route('receiving.show', s.id)} className="font-medium text-ncat-navy hover:text-primary">
                                        {s.srv_number}
                                    </Link>
                                </TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{s.srv_date}</TableCell>
                                <TableCell className="text-sm">{s.supplier ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{s.destination ?? '—'}</TableCell>
                                <TableCell>
                                    <Badge variant={s.status === 'posted' ? 'success' : 'neutral'}>
                                        {s.status === 'posted' ? 'Posted' : 'Draft'}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!srvs.length && (
                    <div className="p-10 text-center">
                        <PackagePlus className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No store receipt vouchers match these filters.</p>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PackageMinus, Search } from 'lucide-react';
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

export default function SivIndex({ sivs, filters }) {
    const { can } = usePermissions();
    const [f, setF] = useState(filters);

    const apply = (next = f) => router.get(route('issuing.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    return (
        <AppLayout>
            <Head title="Issuing - SIV" />
            <PageHeader
                eyebrow="Operations"
                title="Issuing - SIV"
                description="Store Issue Vouchers for parts issued out of bonded and dope stores against approved requisitions."
                icon={PackageMinus}
                actions={can('issues.post') && (
                    <Button onClick={() => router.visit(route('issuing.create'))}>
                        <PackageMinus className="size-4" /> New SIV
                    </Button>
                )}
            />

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search SIV no. / requisition for…" defaultValue={f.search}
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
                            <TableHead>SIV No.</TableHead>
                            <TableHead>Requisition for</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sivs.map((s) => (
                            <TableRow key={s.id}>
                                <TableCell className="whitespace-nowrap font-mono text-sm">
                                    <Link href={route('issuing.show', s.id)} className="font-medium text-ncat-navy hover:text-primary">
                                        {s.siv_number}
                                    </Link>
                                </TableCell>
                                <TableCell className="text-sm">{s.requisition_for ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{s.date ?? '—'}</TableCell>
                                <TableCell>
                                    <Badge variant={s.status === 'posted' ? 'success' : 'neutral'}>
                                        {s.status === 'posted' ? 'Posted' : 'Draft'}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!sivs.length && (
                    <div className="p-10 text-center">
                        <PackageMinus className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No store issue vouchers match these filters.</p>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

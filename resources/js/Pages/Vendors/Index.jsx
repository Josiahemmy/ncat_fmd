import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
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

const TYPE_LABELS = {
    supplier: 'Supplier',
    repair_organization: 'Repair org.',
    both: 'Supplier + repair',
};

const blank = {
    name: '', type: 'supplier', address: '', country: '',
    email: '', phone: '', contact_person: '', notes: '', is_active: true,
};

/**
 * Vendors. The add-new form sits above the list rather than on its own page,
 * which is the layout management asked for: a vendor is usually added while
 * scanning the list to check it is not already there.
 */
export default function VendorsIndex({ vendors, filters, countries, can }) {
    const [f, setF] = useState(filters);
    const [adding, setAdding] = useState(false);
    const form = useForm(blank);

    const apply = (next) => router.get(route('vendors.index'), next, { preserveState: true, replace: true });
    const set = (k, v) => { const n = { ...f, [k]: v }; setF(n); apply(n); };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('vendors.store'), {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setAdding(false); },
        });
    };

    return (
        <AppLayout>
            <Head title="Vendors" />
            <PageHeader
                eyebrow="Operations"
                title="Vendors"
                description="Suppliers and repair organisations that purchase and repair orders are addressed to."
                icon={Building2}
                actions={can.manage && (
                    <Button onClick={() => setAdding((v) => !v)} variant={adding ? 'outline' : 'default'}>
                        <Plus className="size-4" /> {adding ? 'Cancel' : 'Add vendor'}
                    </Button>
                )}
            />

            {adding && can.manage && (
                <Card className="mb-6 p-5">
                    <h2 className="mb-4 font-display text-base font-semibold text-ncat-navy">New vendor</h2>
                    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="v-name">Name</Label>
                            <Input id="v-name" value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)} />
                            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="v-type">Type</Label>
                            <Select id="v-type" value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}>
                                <option value="supplier">Supplier</option>
                                <option value="repair_organization">Repair organisation</option>
                                <option value="both">Both</option>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Only repair organisations can be named on a repair order.
                            </p>
                        </div>

                        <div className="space-y-1.5 sm:col-span-2">
                            <Label htmlFor="v-address">Address</Label>
                            <textarea
                                id="v-address" rows={4} value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                                placeholder={'One line per line, exactly as it should print on the order.'}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="v-country">Country</Label>
                            <Input id="v-country" value={form.data.country}
                                onChange={(e) => form.setData('country', e.target.value)} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="v-contact">Contact person</Label>
                            <Input id="v-contact" value={form.data.contact_person}
                                onChange={(e) => form.setData('contact_person', e.target.value)} />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="v-email">Email</Label>
                            <Input id="v-email" type="email" value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)} />
                            {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="v-phone">Phone</Label>
                            <Input id="v-phone" value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)} />
                        </div>

                        <div className="flex items-center justify-end gap-3 sm:col-span-2">
                            <Button type="submit" disabled={form.processing}>Save vendor</Button>
                        </div>
                    </form>
                </Card>
            )}

            <Card className="mb-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="relative lg:col-span-2">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input className="pl-9" placeholder="Search name / contact / email…" defaultValue={f.search}
                            onKeyDown={(e) => e.key === 'Enter' && set('search', e.target.value)} />
                    </div>
                    <Select value={f.type} onChange={(e) => set('type', e.target.value)}>
                        <option value="">Any type</option>
                        <option value="supplier">Supplier</option>
                        <option value="repair_organization">Repair organisation</option>
                        <option value="both">Both</option>
                    </Select>
                    <Select value={f.country} onChange={(e) => set('country', e.target.value)}>
                        <option value="">Any country</option>
                        {countries.map((c) => <option key={c} value={c}>{c}</option>)}
                    </Select>
                    <Select value={f.active} onChange={(e) => set('active', e.target.value)}>
                        <option value="">Active and inactive</option>
                        <option value="active">Active only</option>
                        <option value="inactive">Inactive only</option>
                    </Select>
                </div>
            </Card>

            <Card className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Country</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead className="text-right">Orders</TableHead>
                            <TableHead>Status</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {vendors.map((v) => (
                            <TableRow key={v.id}>
                                <TableCell>
                                    <Link href={route('vendors.show', v.id)} className="font-medium text-ncat-navy hover:text-primary">
                                        {v.name}
                                    </Link>
                                </TableCell>
                                <TableCell className="whitespace-nowrap text-sm">{TYPE_LABELS[v.type]}</TableCell>
                                <TableCell className="text-sm">{v.country ?? '—'}</TableCell>
                                <TableCell className="text-sm">
                                    {v.contact_person ?? '—'}
                                    {v.email && <span className="block text-xs text-muted-foreground">{v.email}</span>}
                                </TableCell>
                                <TableCell className="text-right text-sm tabular-nums">{v.order_count}</TableCell>
                                <TableCell>
                                    <Badge variant={v.is_active ? 'success' : 'neutral'}>
                                        {v.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!vendors.length && (
                    <div className="p-10 text-center">
                        <Building2 className="mx-auto mb-3 size-8 text-muted-foreground/50" />
                        <p className="text-sm text-muted-foreground">No vendors match these filters.</p>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

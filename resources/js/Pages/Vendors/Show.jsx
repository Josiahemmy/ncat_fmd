import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, Mail, MapPin, Phone, Trash2, User } from 'lucide-react';
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

const STATUS_VARIANTS = {
    draft: 'neutral', issued: 'info', partially_received: 'warning',
    at_vendor: 'warning', received: 'success', returned: 'success',
    closed: 'success', cancelled: 'destructive',
};

const statusLabel = (s) => s.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

/**
 * Vendor detail: an info card plus one tab per linked document family.
 * Shipments and Loans arrive in Phase 8, so the tab strip is driven by a list
 * and already accepts them; they are shown disabled rather than hidden so the
 * shape of the finished screen is visible.
 */
export default function VendorShow({ vendor, purchaseOrders, repairOrders, can }) {
    const [tab, setTab] = useState('purchase');
    const [editing, setEditing] = useState(false);

    const form = useForm({
        name: vendor.name, type: vendor.type, address: vendor.address ?? '',
        country: vendor.country ?? '', email: vendor.email ?? '', phone: vendor.phone ?? '',
        contact_person: vendor.contact_person ?? '', notes: vendor.notes ?? '',
        is_active: vendor.is_active,
    });

    const destroy = useForm({});

    const tabs = [
        { key: 'purchase', label: 'Purchase Orders', count: purchaseOrders.length },
        { key: 'repair', label: 'Repair Orders', count: repairOrders.length },
        { key: 'shipments', label: 'Shipments', disabled: true },
        { key: 'loans', label: 'Loans', disabled: true },
    ];

    const rows = tab === 'purchase' ? purchaseOrders : repairOrders;
    const routeFor = tab === 'purchase' ? 'purchase-orders.show' : 'repair-orders.show';

    const save = (e) => {
        e.preventDefault();
        form.put(route('vendors.update', vendor.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`${vendor.name} · Vendors`} />
            <PageHeader
                eyebrow="Vendors"
                title={vendor.name}
                description={vendor.type_label}
                icon={Building2}
                actions={can.manage && (
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setEditing((v) => !v)}>
                            {editing ? 'Cancel' : 'Edit'}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={destroy.processing}
                            onClick={() => destroy.delete(route('vendors.destroy', vendor.id), { preserveScroll: true })}
                        >
                            <Trash2 className="size-4 text-destructive" /> Delete
                        </Button>
                    </div>
                )}
            />

            {destroy.errors.vendor && (
                <p className="mb-4 rounded-md border border-warning/40 bg-warning/10 p-3 text-sm text-ncat-navy">
                    {destroy.errors.vendor}
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                <Card className="p-5">
                    {editing ? (
                        <form onSubmit={save} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="e-name">Name</Label>
                                <Input id="e-name" value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)} />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-type">Type</Label>
                                <Select id="e-type" value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}>
                                    <option value="supplier">Supplier</option>
                                    <option value="repair_organization">Repair organisation</option>
                                    <option value="both">Both</option>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-address">Address</Label>
                                <textarea id="e-address" rows={5} value={form.data.address}
                                    onChange={(e) => form.setData('address', e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring/40" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-country">Country</Label>
                                <Input id="e-country" value={form.data.country}
                                    onChange={(e) => form.setData('country', e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-contact">Contact person</Label>
                                <Input id="e-contact" value={form.data.contact_person}
                                    onChange={(e) => form.setData('contact_person', e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-email">Email</Label>
                                <Input id="e-email" type="email" value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="e-phone">Phone</Label>
                                <Input id="e-phone" value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)} />
                            </div>
                            <label className="flex cursor-pointer items-center gap-2.5">
                                <input type="checkbox" checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                    className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40" />
                                <span className="text-sm">Active</span>
                            </label>
                            <Button type="submit" disabled={form.processing} className="w-full">Save changes</Button>
                        </form>
                    ) : (
                        <dl className="space-y-4 text-sm">
                            <div className="flex items-center gap-2">
                                <Badge variant={vendor.is_active ? 'success' : 'neutral'}>
                                    {vendor.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                                {vendor.can_repair && <Badge variant="info">Can repair</Badge>}
                            </div>

                            {vendor.address_lines.length > 0 && (
                                <div className="flex gap-2.5">
                                    <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <div>
                                        {vendor.address_lines.map((line, i) => <div key={i}>{line}</div>)}
                                        {vendor.country && <div className="text-muted-foreground">{vendor.country}</div>}
                                    </div>
                                </div>
                            )}
                            {vendor.contact_person && (
                                <div className="flex gap-2.5">
                                    <User className="size-4 shrink-0 text-muted-foreground" />
                                    <span>{vendor.contact_person}</span>
                                </div>
                            )}
                            {vendor.email && (
                                <div className="flex gap-2.5">
                                    <Mail className="size-4 shrink-0 text-muted-foreground" />
                                    <a href={`mailto:${vendor.email}`} className="text-primary hover:underline">{vendor.email}</a>
                                </div>
                            )}
                            {vendor.phone && (
                                <div className="flex gap-2.5">
                                    <Phone className="size-4 shrink-0 text-muted-foreground" />
                                    <span>{vendor.phone}</span>
                                </div>
                            )}
                            {vendor.notes && (
                                <p className="border-t border-border pt-4 text-muted-foreground">{vendor.notes}</p>
                            )}
                        </dl>
                    )}
                </Card>

                <div>
                    <div className="mb-4 flex flex-wrap gap-1 border-b border-border">
                        {tabs.map((t) => (
                            <button
                                key={t.key} type="button" disabled={t.disabled}
                                onClick={() => setTab(t.key)}
                                className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                                    tab === t.key
                                        ? 'border-primary text-ncat-navy'
                                        : 'border-transparent text-muted-foreground hover:text-ncat-navy'
                                } ${t.disabled ? 'cursor-not-allowed opacity-40 hover:text-muted-foreground' : ''}`}
                                title={t.disabled ? 'Arrives in Phase 8' : undefined}
                            >
                                {t.label}
                                {t.count !== undefined && (
                                    <span className="ml-2 text-xs tabular-nums text-muted-foreground">{t.count}</span>
                                )}
                            </button>
                        ))}
                    </div>

                    <Card className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((o) => (
                                    <TableRow key={o.id}>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">
                                            <Link href={route(routeFor, o.id)} className="font-medium text-ncat-navy hover:text-primary">
                                                {o.number ?? 'Draft'}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm">{o.order_date}</TableCell>
                                        <TableCell>
                                            <Badge variant={STATUS_VARIANTS[o.status] ?? 'neutral'}>
                                                {statusLabel(o.status)}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {!rows.length && (
                            <p className="p-10 text-center text-sm text-muted-foreground">
                                No {tab === 'purchase' ? 'purchase' : 'repair'} orders for this vendor yet.
                            </p>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

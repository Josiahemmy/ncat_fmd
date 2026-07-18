import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Flame, Fuel, Pencil, Plus, ShieldQuestion, Warehouse } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Badge } from '@/Components/ui/Badge';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

const TYPE_META = {
    quarantine: { icon: ShieldQuestion, tone: 'warning', label: 'Quarantine' },
    bonded: { icon: Warehouse, tone: 'success', label: 'Bonded' },
    dope: { icon: Flame, tone: 'error', label: 'Dope' },
    fuel: { icon: Fuel, tone: 'info', label: 'Fuel' },
    general: { icon: Warehouse, tone: 'neutral', label: 'General' },
};

export default function StoresIndex({ stores }) {
    const { can } = usePermissions();
    const canManage = can('stores.manage');
    const [editing, setEditing] = useState(null);

    return (
        <AppLayout>
            <Head title="Stores · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Stores"
                description="The department's physical stores. Types are fixed for the core four; extra stores are 'general'."
                icon={Warehouse}
                actions={canManage && (
                    <Button onClick={() => setEditing('new')}><Plus className="size-4" /> Add store</Button>
                )}
            />
            <AdminNav />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {stores.map((s) => {
                    const meta = TYPE_META[s.type] ?? TYPE_META.general;
                    const Icon = meta.icon;
                    return (
                        <Card key={s.id} variant="glass" className="flex flex-col p-5">
                            <div className="flex items-start justify-between">
                                <span className="flex size-10 items-center justify-center rounded-lg bg-ncat-navy text-white">
                                    <Icon className="size-5" />
                                </span>
                                <div className="flex flex-col items-end gap-1">
                                    <Badge variant={meta.tone}>{meta.label}</Badge>
                                    {!s.is_active && <Badge variant="error">Inactive</Badge>}
                                </div>
                            </div>
                            <h3 className="mt-3 font-display text-base font-semibold text-ncat-navy">{s.name}</h3>
                            <p className="mt-1 flex-1 text-sm text-muted-foreground">{s.description}</p>
                            {canManage && (
                                <Button variant="ghost" size="sm" className="mt-3 self-start" onClick={() => setEditing(s)}>
                                    <Pencil className="size-4" /> Edit
                                </Button>
                            )}
                        </Card>
                    );
                })}
            </div>

            {editing && <StoreModal store={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function StoreModal({ store, onClose }) {
    const isNew = !store;
    const form = useForm({
        name: store?.name || '',
        description: store?.description || '',
        is_active: store ? store.is_active : true,
    });
    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isNew) form.post(route('admin.stores.store'), opts);
        else form.put(route('admin.stores.update', store.id), opts);
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>{isNew ? 'Add store' : `Edit ${store.name}`}</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="sname">Name</Label>
                        <Input id="sname" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="sdesc">Description</Label>
                        <Input id="sdesc" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </div>
                    <label className="flex cursor-pointer items-center gap-2.5">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                        />
                        <span className="text-sm">Active</span>
                    </label>
                    {isNew && <p className="text-xs text-muted-foreground">New stores are created as the “general” type.</p>}
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{isNew ? 'Create' : 'Save'}</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

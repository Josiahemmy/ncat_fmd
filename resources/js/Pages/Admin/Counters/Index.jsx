import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, ListOrdered, Pencil } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Badge } from '@/Components/ui/Badge';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function CountersIndex({ counters }) {
    const { can } = usePermissions();
    const canManage = can('counters.manage');
    const [editing, setEditing] = useState(null);

    return (
        <AppLayout>
            <Head title="Document Counters · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Document Counters"
                description="Running numbers that continue the department's paper sequences. Confirm exact values before Phase 3."
                icon={ListOrdered}
            />
            <AdminNav />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {counters.map((c) => (
                    <Card key={c.id} variant="glass" className="flex flex-col p-5">
                        <div className="flex items-start justify-between">
                            <p className="text-sm font-medium text-muted-foreground">{c.label}</p>
                            {c.confirmed
                                ? <Badge variant="success">Confirmed</Badge>
                                : <Badge variant="warning" className="gap-1"><AlertTriangle className="size-3" /> Provisional</Badge>}
                        </div>
                        <p className="mt-3 font-display text-3xl font-bold text-ncat-navy">{c.preview}</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">next number to issue</p>
                        {c.notes && <p className="mt-3 flex-1 text-xs text-muted-foreground">{c.notes}</p>}
                        {canManage && (
                            <Button variant="ghost" size="sm" className="mt-3 self-start" onClick={() => setEditing(c)}>
                                <Pencil className="size-4" /> Edit
                            </Button>
                        )}
                    </Card>
                ))}
            </div>

            {editing && <CounterModal counter={editing} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function CounterModal({ counter, onClose }) {
    const form = useForm({
        prefix: counter.prefix || '',
        next_number: counter.next_number,
        padding: counter.padding,
        confirmed: counter.confirmed,
        notes: counter.notes || '',
    });
    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.counters.update', counter.id), { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader>
                    <ModalTitle>Edit {counter.label}</ModalTitle>
                    <ModalDescription>Set the next number to be issued and its display format.</ModalDescription>
                </ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="prefix">Prefix</Label>
                            <Input id="prefix" value={form.data.prefix} onChange={(e) => form.setData('prefix', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="next">Next number</Label>
                            <Input id="next" type="number" min="1" value={form.data.next_number} onChange={(e) => form.setData('next_number', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="pad">Padding</Label>
                            <Input id="pad" type="number" min="0" max="12" value={form.data.padding} onChange={(e) => form.setData('padding', e.target.value)} />
                        </div>
                    </div>
                    {form.errors.next_number && <p className="text-sm text-destructive">{form.errors.next_number}</p>}
                    <div className="space-y-1.5">
                        <Label htmlFor="notes">Notes</Label>
                        <Input id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </div>
                    <label className="flex cursor-pointer items-center gap-2.5">
                        <input
                            type="checkbox"
                            checked={form.data.confirmed}
                            onChange={(e) => form.setData('confirmed', e.target.checked)}
                            className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                        />
                        <span className="text-sm">Confirmed with the department</span>
                    </label>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Save</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

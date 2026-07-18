import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plane, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
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
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

const STATUS = { active: 'success', maintenance: 'warning', retired: 'neutral' };

export default function FleetIndex({ aircraft, types }) {
    const { can } = usePermissions();
    const canManage = can('aircraft.manage');
    const [editAircraft, setEditAircraft] = useState(null);
    const [editType, setEditType] = useState(null);

    return (
        <AppLayout>
            <Head title="Fleet · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Aircraft & Types"
                description="The six fleet types and 26 registrations."
                icon={Plane}
                actions={canManage && (
                    <Button onClick={() => setEditAircraft('new')}><Plus className="size-4" /> Add aircraft</Button>
                )}
            />
            <AdminNav />

            {/* Types */}
            <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                {types.map((t) => (
                    <Card key={t.id} variant="glass" className="group overflow-hidden p-3 text-center">
                        <div className="mb-2 flex h-20 items-center justify-center rounded-md bg-ncat-mist">
                            {t.image_path
                                ? <img src={t.image_path} alt={t.name} className="max-h-full max-w-full object-contain" loading="lazy" />
                                : <Plane className="size-8 text-ncat-steel" />}
                        </div>
                        <p className="text-sm font-semibold text-ncat-navy">{t.name}</p>
                        <p className="text-xs text-muted-foreground">{t.aircraft_count} aircraft</p>
                        {canManage && (
                            <Button variant="ghost" size="sm" className="mt-1 w-full" onClick={() => setEditType(t)}>
                                <Pencil className="size-3.5" /> Edit
                            </Button>
                        )}
                    </Card>
                ))}
            </div>

            {/* Aircraft */}
            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Registration</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            {canManage && <TableHead className="text-right">Actions</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {aircraft.map((a) => (
                            <TableRow key={a.id}>
                                <TableCell className="font-semibold text-ncat-navy">{a.registration}</TableCell>
                                <TableCell>{a.type}</TableCell>
                                <TableCell><Badge variant={STATUS[a.status]}>{a.status}</Badge></TableCell>
                                {canManage && (
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => setEditAircraft(a)}>
                                                <Pencil className="size-4" /> Edit
                                            </Button>
                                            <Button
                                                variant="ghost" size="sm"
                                                onClick={() => confirm(`Remove ${a.registration}?`) &&
                                                    router.delete(route('admin.aircraft.destroy', a.id), { preserveScroll: true })}
                                            >
                                                <Trash2 className="size-4" /> Remove
                                            </Button>
                                        </div>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>

            {editAircraft && (
                <AircraftModal
                    aircraft={editAircraft === 'new' ? null : editAircraft}
                    types={types}
                    onClose={() => setEditAircraft(null)}
                />
            )}
            {editType && <TypeModal type={editType} onClose={() => setEditType(null)} />}
        </AppLayout>
    );
}

function AircraftModal({ aircraft, types, onClose }) {
    const isNew = !aircraft;
    const form = useForm({
        registration: aircraft?.registration || '',
        aircraft_type_id: aircraft?.aircraft_type_id || types[0]?.id || '',
        status: aircraft?.status || 'active',
        notes: aircraft?.notes || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isNew) form.post(route('admin.aircraft.store'), opts);
        else form.put(route('admin.aircraft.update', aircraft.id), opts);
    };

    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>{isNew ? 'Add aircraft' : `Edit ${aircraft.registration}`}</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="reg">Registration</Label>
                        <Input id="reg" value={form.data.registration} onChange={(e) => form.setData('registration', e.target.value)} />
                        {form.errors.registration && <p className="text-sm text-destructive">{form.errors.registration}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="type">Type</Label>
                        <Select id="type" value={form.data.aircraft_type_id} onChange={(e) => form.setData('aircraft_type_id', e.target.value)}>
                            {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="status">Status</Label>
                        <Select id="status" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </Select>
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{isNew ? 'Add' : 'Save'}</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function TypeModal({ type, onClose }) {
    const form = useForm({ name: type.name, wo_code: type.wo_code || '' });
    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.types.update', type.id), { preserveScroll: true, onSuccess: onClose });
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>Edit {type.name}</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="tname">Name</Label>
                        <Input id="tname" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="wocode">Work Order code</Label>
                        <Input id="wocode" value={form.data.wo_code} onChange={(e) => form.setData('wo_code', e.target.value)} />
                        <p className="text-xs text-muted-foreground">Token used in FMD/{'{code}'}/MM/YY/serial references.</p>
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Save</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

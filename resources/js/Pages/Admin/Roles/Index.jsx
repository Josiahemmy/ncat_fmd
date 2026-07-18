import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PermissionMatrix } from '@/Components/admin/PermissionMatrix';
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

export default function RolesIndex({ roles, permissionGroups }) {
    const { can } = usePermissions();
    const canManage = can('roles.manage');
    const [editing, setEditing] = useState(null);

    return (
        <AppLayout>
            <Head title="Roles · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Roles & Permissions"
                description="Define roles and grant permissions via grouped toggles. Super Admin is immutable."
                icon={ShieldCheck}
                actions={canManage && (
                    <Button onClick={() => setEditing('new')}><Plus className="size-4" /> New role</Button>
                )}
            />
            <AdminNav />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                {roles.map((role) => (
                    <Card key={role.id} variant="glass" className="flex flex-col p-5">
                        <div className="flex items-start justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-display text-base font-semibold text-ncat-navy">{role.name}</h3>
                                    {role.immutable && <Lock className="size-3.5 text-muted-foreground" />}
                                </div>
                                <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                    <Users className="size-3.5" /> {role.users_count} user{role.users_count === 1 ? '' : 's'}
                                </p>
                            </div>
                            <Badge variant="neutral">{role.permissions.length} perms</Badge>
                        </div>

                        {canManage && (
                            <div className="mt-4 flex gap-1 border-t border-border pt-3">
                                <Button
                                    variant="ghost" size="sm"
                                    disabled={role.immutable}
                                    onClick={() => setEditing(role)}
                                >
                                    <Pencil className="size-4" /> Edit
                                </Button>
                                <Button
                                    variant="ghost" size="sm"
                                    disabled={role.immutable || role.users_count > 0}
                                    title={role.users_count > 0 ? 'Reassign users first' : undefined}
                                    onClick={() => {
                                        if (confirm(`Delete role ${role.name}?`)) {
                                            router.delete(route('admin.roles.destroy', role.id), { preserveScroll: true });
                                        }
                                    }}
                                >
                                    <Trash2 className="size-4" /> Delete
                                </Button>
                            </div>
                        )}
                    </Card>
                ))}
            </div>

            {editing && (
                <RoleFormModal
                    role={editing === 'new' ? null : editing}
                    permissionGroups={permissionGroups}
                    onClose={() => setEditing(null)}
                />
            )}
        </AppLayout>
    );
}

function RoleFormModal({ role, permissionGroups, onClose }) {
    const isNew = !role;
    const form = useForm({
        name: role?.name || '',
        permissions: role?.permissions || [],
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isNew) form.post(route('admin.roles.store'), opts);
        else form.put(route('admin.roles.update', role.id), opts);
    };

    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <ModalHeader>
                    <ModalTitle>{isNew ? 'New role' : `Edit ${role.name}`}</ModalTitle>
                    <ModalDescription>Grant permissions with the grouped toggles below.</ModalDescription>
                </ModalHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <Label htmlFor="rolename">Role name</Label>
                        <Input id="rolename" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="space-y-2">
                        <Label>Permissions</Label>
                        <PermissionMatrix
                            groups={permissionGroups}
                            value={form.data.permissions}
                            onChange={(v) => form.setData('permissions', v)}
                        />
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{isNew ? 'Create role' : 'Save changes'}</Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

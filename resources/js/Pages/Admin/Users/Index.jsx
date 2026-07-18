import { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Pencil, Search, ShieldCheck, UserPlus } from 'lucide-react';
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
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalDescription, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function UsersIndex({ users, filters, roles, permissionGroups }) {
    const { can } = usePermissions();
    const canManage = can('users.manage');
    const { flash } = usePage().props;

    const [search, setSearch] = useState(filters.search || '');
    const [editing, setEditing] = useState(null); // user object or 'new'
    const [tempPw, setTempPw] = useState(null);

    // Surface the one-time temp password after create/reset.
    useEffect(() => {
        if (flash?.generated_password) {
            setTempPw({ password: flash.generated_password, email: flash.generated_for });
        }
    }, [flash?.generated_password, flash?.generated_for]);

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.users.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Users · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Users"
                description="Provision accounts, assign roles and per-user permission overrides."
                icon={ShieldCheck}
                actions={canManage && (
                    <Button onClick={() => setEditing('new')}>
                        <UserPlus className="size-4" /> Add user
                    </Button>
                )}
            />
            <AdminNav />

            <form onSubmit={submitSearch} className="mb-4 max-w-sm">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="pl-9"
                        placeholder="Search name or email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
            </form>

            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Last login</TableHead>
                            {canManage && <TableHead className="text-right">Actions</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.map((u) => (
                            <TableRow key={u.id}>
                                <TableCell>
                                    <div className="font-medium text-ncat-navy">{u.name}</div>
                                    <div className="text-xs text-muted-foreground">{u.email}</div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {u.roles.length ? u.roles.map((r) => (
                                            <Badge key={r} variant={r === 'Super Admin' ? 'navy' : 'default'}>{r}</Badge>
                                        )) : <span className="text-xs text-muted-foreground">—</span>}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {u.is_active
                                        ? <Badge variant="success">Active</Badge>
                                        : <Badge variant="error">Deactivated</Badge>}
                                    {u.must_change_password && (
                                        <Badge variant="warning" className="ml-1">Must reset</Badge>
                                    )}
                                </TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {u.last_login_at || 'Never'}
                                </TableCell>
                                {canManage && (
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => setEditing(u)}>
                                                <Pencil className="size-4" /> Edit
                                            </Button>
                                            <Button
                                                variant="ghost" size="sm"
                                                onClick={() => {
                                                    if (confirm(`Reset password for ${u.name}?`)) {
                                                        router.post(route('admin.users.reset', u.id), {}, { preserveScroll: true });
                                                    }
                                                }}
                                            >
                                                <KeyRound className="size-4" /> Reset
                                            </Button>
                                        </div>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!users.data.length && (
                    <p className="p-8 text-center text-sm text-muted-foreground">No users match your search.</p>
                )}
            </Card>

            {/* Pagination */}
            {users.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {users.links.map((link, i) => (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            {editing && (
                <UserFormModal
                    user={editing === 'new' ? null : editing}
                    roles={roles}
                    permissionGroups={permissionGroups}
                    onClose={() => setEditing(null)}
                />
            )}

            {tempPw && <TempPasswordModal data={tempPw} onClose={() => setTempPw(null)} />}
        </AppLayout>
    );
}

function UserFormModal({ user, roles, permissionGroups, onClose }) {
    const isNew = !user;
    const form = useForm({
        name: user?.name || '',
        email: user?.email || '',
        is_active: user ? user.is_active : true,
        roles: user?.roles || [],
        permissions: user?.permission_overrides || [],
    });

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isNew) form.post(route('admin.users.store'), opts);
        else form.put(route('admin.users.update', user.id), opts);
    };

    const toggleRole = (r) => {
        form.setData('roles', form.data.roles.includes(r)
            ? form.data.roles.filter((x) => x !== r)
            : [...form.data.roles, r]);
    };

    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <ModalHeader>
                    <ModalTitle>{isNew ? 'Add user' : `Edit ${user.name}`}</ModalTitle>
                    <ModalDescription>
                        {isNew
                            ? 'A one-time temporary password is generated and shown once.'
                            : 'Update details, roles and per-user permission overrides.'}
                    </ModalDescription>
                </ModalHeader>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="name">Full name</Label>
                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                        </div>
                    </div>

                    {!isNew && (
                        <label className="flex cursor-pointer items-center gap-2.5">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) => form.setData('is_active', e.target.checked)}
                                className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                            />
                            <span className="text-sm">Account active (deactivated users cannot sign in)</span>
                        </label>
                    )}

                    <div className="space-y-2">
                        <Label>Roles</Label>
                        <div className="flex flex-wrap gap-2">
                            {roles.map((r) => (
                                <button
                                    type="button"
                                    key={r}
                                    onClick={() => toggleRole(r)}
                                    className={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
                                        form.data.roles.includes(r)
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border text-muted-foreground hover:border-primary/40'
                                    }`}
                                >
                                    {r}
                                </button>
                            ))}
                        </div>
                    </div>

                    {!isNew && (
                        <div className="space-y-2">
                            <Label>Direct permission overrides (in addition to roles)</Label>
                            <PermissionMatrix
                                groups={permissionGroups}
                                value={form.data.permissions}
                                onChange={(v) => form.setData('permissions', v)}
                            />
                        </div>
                    )}

                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>
                            {isNew ? 'Create user' : 'Save changes'}
                        </Button>
                    </ModalFooter>
                </form>
            </ModalContent>
        </Modal>
    );
}

function TempPasswordModal({ data, onClose }) {
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader>
                    <ModalTitle>Temporary password</ModalTitle>
                    <ModalDescription>
                        Share this with <strong>{data.email}</strong> securely. It is shown once and
                        must be changed on first sign-in.
                    </ModalDescription>
                </ModalHeader>
                <div className="flex items-center justify-between rounded-md border border-border bg-muted/50 p-4">
                    <code className="font-mono text-lg font-semibold text-ncat-navy">{data.password}</code>
                    <Button variant="outline" size="sm" onClick={() => navigator.clipboard?.writeText(data.password)}>
                        <Copy className="size-4" /> Copy
                    </Button>
                </div>
                <ModalFooter>
                    <Button onClick={onClose}>Done</Button>
                </ModalFooter>
            </ModalContent>
        </Modal>
    );
}

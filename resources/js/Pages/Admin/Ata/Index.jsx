import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Hash, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';
import {
    Modal, ModalContent, ModalHeader, ModalTitle, ModalFooter,
} from '@/Components/ui/Modal';
import { usePermissions } from '@/lib/permissions';

export default function AtaIndex({ chapters, filters }) {
    const { can } = usePermissions();
    const canManage = can('ata.manage');
    const [search, setSearch] = useState(filters.search || '');
    const [editing, setEditing] = useState(null);

    const submitSearch = (e) => {
        e.preventDefault();
        router.get(route('admin.ata.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="ATA Chapters · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="ATA Chapters"
                description="The ATA 100 chapter reference used to classify parts."
                icon={Hash}
                actions={canManage && (
                    <Button onClick={() => setEditing('new')}><Plus className="size-4" /> Add chapter</Button>
                )}
            />
            <AdminNav />

            <form onSubmit={submitSearch} className="mb-4 max-w-sm">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Search chapter or title…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
            </form>

            <Card className="overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-28">Chapter</TableHead>
                            <TableHead>Title</TableHead>
                            {canManage && <TableHead className="text-right">Actions</TableHead>}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {chapters.map((c) => (
                            <TableRow key={c.id}>
                                <TableCell className="font-mono font-semibold text-ncat-navy">{c.chapter_number}</TableCell>
                                <TableCell>{c.title}</TableCell>
                                {canManage && (
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => setEditing(c)}>
                                                <Pencil className="size-4" /> Edit
                                            </Button>
                                            <Button
                                                variant="ghost" size="sm"
                                                onClick={() => confirm(`Delete ATA ${c.chapter_number}?`) &&
                                                    router.delete(route('admin.ata.destroy', c.id), { preserveScroll: true })}
                                            >
                                                <Trash2 className="size-4" /> Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                {!chapters.length && <p className="p-8 text-center text-sm text-muted-foreground">No chapters found.</p>}
            </Card>

            {editing && <AtaModal chapter={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function AtaModal({ chapter, onClose }) {
    const isNew = !chapter;
    const form = useForm({ chapter_number: chapter?.chapter_number || '', title: chapter?.title || '' });
    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (isNew) form.post(route('admin.ata.store'), opts);
        else form.put(route('admin.ata.update', chapter.id), opts);
    };
    return (
        <Modal open onOpenChange={(o) => !o && onClose()}>
            <ModalContent>
                <ModalHeader><ModalTitle>{isNew ? 'Add ATA chapter' : `Edit chapter ${chapter.chapter_number}`}</ModalTitle></ModalHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="num">Chapter number</Label>
                        <Input id="num" value={form.data.chapter_number} onChange={(e) => form.setData('chapter_number', e.target.value)} placeholder="e.g. 32" />
                        {form.errors.chapter_number && <p className="text-sm text-destructive">{form.errors.chapter_number}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="title">Title</Label>
                        <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="e.g. Landing Gear" />
                        {form.errors.title && <p className="text-sm text-destructive">{form.errors.title}</p>}
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

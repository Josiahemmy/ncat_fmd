import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle, ArrowDown, ArrowUp, GitBranch, Plus, Trash2,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';

const blankLevel = { id: null, name: '', binding_type: 'permission', binding_value: 'requisitions.approve', is_active: true };

/**
 * Approval Workflow: the ordered level chain a requisition travels through.
 *
 * The whole list posts as one save so add / rename / remove / reorder are a
 * single atomic change and sequence numbers stay contiguous. Reordering uses
 * explicit up/down controls rather than drag so it stays keyboard-operable.
 */
export default function ApprovalsIndex({ workflow, levels, permissions, roles, inFlight }) {
    const form = useForm({ levels: levels.length ? levels : [{ ...blankLevel, name: 'Approval' }] });
    const [dirty, setDirty] = useState(false);

    const rows = form.data.levels;

    const write = (next) => {
        form.setData('levels', next);
        setDirty(true);
    };

    const patch = (i, changes) => write(rows.map((r, idx) => (idx === i ? { ...r, ...changes } : r)));

    const setBindingType = (i, type) => patch(i, {
        binding_type: type,
        binding_value: type === 'role' ? (roles[0] ?? '') : 'requisitions.approve',
    });

    const move = (i, delta) => {
        const to = i + delta;
        if (to < 0 || to >= rows.length) return;
        const next = [...rows];
        [next[i], next[to]] = [next[to], next[i]];
        write(next);
    };

    const add = () => write([...rows, { ...blankLevel }]);
    const remove = (i) => write(rows.filter((_, idx) => idx !== i));

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.approvals.update', workflow.id), {
            preserveScroll: true,
            onSuccess: () => setDirty(false),
        });
    };

    return (
        <AppLayout>
            <Head title="Approval Workflow · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Approval Workflow"
                description="The levels a requisition passes through before it can be issued. Each level is bound to one permission or one role."
                icon={GitBranch}
            />
            <AdminNav />

            {inFlight > 0 && (
                <div className="mb-6 flex gap-3 rounded-lg border border-warning/40 bg-warning/10 p-4">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-[hsl(30_65%_30%)]" />
                    <div className="text-sm text-ncat-navy">
                        <p className="font-semibold">
                            {inFlight} requisition{inFlight === 1 ? '' : 's'} {inFlight === 1 ? 'is' : 'are'} part-way through approval right now.
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Changes here apply to requisitions submitted from now on. Anything already in
                            progress keeps the levels it started with, so nobody loses an approval they
                            have already given.
                        </p>
                    </div>
                </div>
            )}

            <form onSubmit={submit} className="max-w-3xl">
                {typeof form.errors.levels === 'string' && (
                    <p className="mb-4 text-sm text-destructive">{form.errors.levels}</p>
                )}

                {/* The chain: a connector rail runs behind the sequence chips, echoing
                    the numbered boxes on the paper voucher. */}
                <div className="relative space-y-3 pl-11">
                    <span aria-hidden className="absolute bottom-8 left-[1.1875rem] top-8 w-px bg-border" />

                    {rows.map((level, i) => (
                        <div key={level.id ?? `new-${i}`} className="relative">
                            <span className="absolute -left-11 top-5 flex size-9 items-center justify-center rounded-full border border-border bg-background font-display text-sm font-bold text-ncat-navy">
                                {i + 1}
                            </span>

                            <Card className="p-4">
                                <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5 sm:col-span-2">
                                            <Label htmlFor={`level-name-${i}`}>Level name</Label>
                                            <Input
                                                id={`level-name-${i}`}
                                                placeholder="e.g. HOD Approval"
                                                value={level.name}
                                                onChange={(e) => patch(i, { name: e.target.value })}
                                            />
                                            {form.errors[`levels.${i}.name`] && (
                                                <p className="text-sm text-destructive">{form.errors[`levels.${i}.name`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label htmlFor={`level-type-${i}`}>Bound to</Label>
                                            <Select
                                                id={`level-type-${i}`}
                                                value={level.binding_type}
                                                onChange={(e) => setBindingType(i, e.target.value)}
                                            >
                                                <option value="permission">A permission</option>
                                                <option value="role">A role</option>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label htmlFor={`level-value-${i}`}>
                                                {level.binding_type === 'role' ? 'Role' : 'Permission'}
                                            </Label>
                                            <Select
                                                id={`level-value-${i}`}
                                                value={level.binding_value}
                                                onChange={(e) => patch(i, { binding_value: e.target.value })}
                                            >
                                                {(level.binding_type === 'role' ? roles : permissions).map((v) => (
                                                    <option key={v} value={v}>{v}</option>
                                                ))}
                                            </Select>
                                            {form.errors[`levels.${i}.binding_value`] && (
                                                <p className="text-sm text-destructive">{form.errors[`levels.${i}.binding_value`]}</p>
                                            )}
                                        </div>

                                        <label className="flex cursor-pointer items-center gap-2.5 sm:col-span-2">
                                            <input
                                                type="checkbox"
                                                checked={level.is_active}
                                                onChange={(e) => patch(i, { is_active: e.target.checked })}
                                                className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                                            />
                                            <span className="text-sm">
                                                Active
                                                <span className="ml-1.5 text-muted-foreground">
                                                    (an inactive level is skipped by new submissions)
                                                </span>
                                            </span>
                                            {!level.is_active && <Badge variant="neutral">Skipped</Badge>}
                                        </label>
                                    </div>

                                    <div className="flex gap-1 sm:flex-col">
                                        <Button
                                            type="button" variant="ghost" size="icon" title="Move up"
                                            aria-label={`Move ${level.name || `level ${i + 1}`} up`}
                                            disabled={i === 0} onClick={() => move(i, -1)}
                                        >
                                            <ArrowUp className="size-4" />
                                        </Button>
                                        <Button
                                            type="button" variant="ghost" size="icon" title="Move down"
                                            aria-label={`Move ${level.name || `level ${i + 1}`} down`}
                                            disabled={i === rows.length - 1} onClick={() => move(i, 1)}
                                        >
                                            <ArrowDown className="size-4" />
                                        </Button>
                                        <Button
                                            type="button" variant="ghost" size="icon" title="Remove level"
                                            aria-label={`Remove ${level.name || `level ${i + 1}`}`}
                                            disabled={rows.length === 1} onClick={() => remove(i)}
                                        >
                                            <Trash2 className="size-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    ))}
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2 pl-11">
                    <Button type="button" variant="outline" size="sm" onClick={add}>
                        <Plus className="size-4" /> Add level
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        A requisition becomes issuable once the last level approves.
                    </p>
                </div>

                <div className="mt-6 flex items-center justify-end gap-3 border-t border-border pt-4">
                    {dirty && <span className="text-sm text-muted-foreground">Unsaved changes</span>}
                    <Button type="submit" disabled={form.processing || !dirty}>Save workflow</Button>
                </div>
            </form>
        </AppLayout>
    );
}

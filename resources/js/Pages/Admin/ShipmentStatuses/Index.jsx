import { Head, useForm } from '@inertiajs/react';
import { GripVertical, Plus, Ship, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';

export default function ShipmentStatusesIndex({ settings, defaults }) {
    const form = useForm({
        statuses: settings.statuses,
        arrival_status: settings.arrival_status,
    });

    const setAt = (i, value) => {
        const next = [...form.data.statuses];
        next[i] = value;
        form.setData('statuses', next);
    };

    const removeAt = (i) => form.setData('statuses', form.data.statuses.filter((_, n) => n !== i));
    const add = () => form.setData('statuses', [...form.data.statuses, '']);

    const move = (i, delta) => {
        const next = [...form.data.statuses];
        const target = i + delta;
        if (target < 0 || target >= next.length) return;
        [next[i], next[target]] = [next[target], next[i]];
        form.setData('statuses', next);
    };

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.shipment-statuses.update'), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Shipment statuses · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Shipment statuses"
                description="What the timeline's status picker offers. Clerks can always type something else."
                icon={Ship}
            />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
                <Card className="p-5">
                    <h2 className="mb-1 font-display text-sm font-semibold text-ncat-navy">Suggested statuses</h2>
                    <p className="mb-4 text-xs text-muted-foreground">
                        In the order they appear in the picker. Changing this list never changes an event
                        that has already been recorded.
                    </p>

                    <ul className="space-y-2">
                        {form.data.statuses.map((status, i) => (
                            <li key={i} className="flex items-center gap-2">
                                <span className="flex flex-col text-muted-foreground">
                                    <button type="button" onClick={() => move(i, -1)} disabled={i === 0}
                                        className="px-1 text-xs leading-none disabled:opacity-30" aria-label={`Move ${status} up`}>
                                        ▲
                                    </button>
                                    <button type="button" onClick={() => move(i, 1)} disabled={i === form.data.statuses.length - 1}
                                        className="px-1 text-xs leading-none disabled:opacity-30" aria-label={`Move ${status} down`}>
                                        ▼
                                    </button>
                                </span>
                                <GripVertical className="size-4 shrink-0 text-muted-foreground/50" aria-hidden="true" />
                                <Input value={status} onChange={(e) => setAt(i, e.target.value)} />
                                <Button type="button" variant="outline" onClick={() => removeAt(i)}
                                    aria-label={`Remove ${status}`}>
                                    <Trash2 className="size-4 text-destructive" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    {form.errors.statuses && <p className="mt-2 text-sm text-destructive">{form.errors.statuses}</p>}

                    <Button type="button" variant="outline" className="mt-4" onClick={add}>
                        <Plus className="size-4" /> Add a status
                    </Button>
                </Card>

                <div className="space-y-6">
                    <Card className="space-y-4 p-5">
                        <div className="space-y-1.5">
                            <Label htmlFor="arrival">Arrival status</Label>
                            <Select id="arrival" value={form.data.arrival_status}
                                onChange={(e) => form.setData('arrival_status', e.target.value)}>
                                {form.data.statuses.filter(Boolean).map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Picking this status pre-ticks "arrived at NCAT" on the event form. The tick on
                                the event is what closes the shipment, so renaming this is safe.
                            </p>
                            {form.errors.arrival_status && (
                                <p className="text-sm text-destructive">{form.errors.arrival_status}</p>
                            )}
                        </div>

                        <Button type="submit" disabled={form.processing} className="w-full">Save statuses</Button>

                        <Button
                            type="button" variant="outline" className="w-full"
                            onClick={() => form.setData({ ...defaults })}
                        >
                            Restore the defaults
                        </Button>
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}

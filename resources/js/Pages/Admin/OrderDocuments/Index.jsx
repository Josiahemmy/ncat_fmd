import { Head, useForm } from '@inertiajs/react';
import { FileText, RotateCcw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AdminNav } from '@/Components/admin/AdminNav';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';

function Text({ id, label, hint, form }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} value={form.data[id] ?? ''} onChange={(e) => form.setData(id, e.target.value)} />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {form.errors[id] && <p className="text-sm text-destructive">{form.errors[id]}</p>}
        </div>
    );
}

function Note({ id, label, hint, form }) {
    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id} rows={6} value={form.data[id] ?? ''}
                onChange={(e) => form.setData(id, e.target.value)}
                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-ring/40"
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

/**
 * The letterhead, contacts, and NOTE text printed on the two order forms.
 * Defaults are transcribed from the sample paper, including the places where
 * the two forms disagree with each other, so nothing is silently normalised
 * before the department has decided what it wants.
 */
export default function OrderDocumentsIndex({ settings, defaults }) {
    const form = useForm({ ...settings });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('admin.order-documents.update'), { preserveScroll: true });
    };

    const reset = () => form.setData({ ...defaults });

    return (
        <AppLayout>
            <Head title="Order Documents · Administration" />
            <PageHeader
                eyebrow="Administration"
                title="Order Documents"
                description="What prints on the Purchase Order and Repair Order letterheads."
                icon={FileText}
                actions={(
                    <Button type="button" variant="outline" onClick={reset}>
                        <RotateCcw className="size-4" /> Restore sample values
                    </Button>
                )}
            />
            <AdminNav />

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <Card className="p-5">
                    <h2 className="mb-4 font-display text-base font-semibold text-ncat-navy">Letterhead</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Text id="address_line_1" label="Address line 1" form={form} />
                        <Text id="address_line_2" label="Address line 2" form={form} />
                        <Text
                            id="email_line_1" label="Email line 1" form={form}
                            hint="Transcribed from the sample exactly as printed. It reads as a typo on the paper (two addresses run together) and is worth confirming."
                        />
                        <Text id="email_line_2" label="Email line 2" form={form} />
                        <Text id="website" label="Website" form={form} />
                    </div>
                </Card>

                <Card className="p-5">
                    <h2 className="mb-4 font-display text-base font-semibold text-ncat-navy">NCAT contacts</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Text id="contact_1_name" label="Contact I name" form={form} />
                        <Text id="contact_1_email" label="Contact I email" form={form} />
                        <Text id="contact_2_name" label="Contact II name" form={form} />
                        <Text id="contact_2_email" label="Contact II email" form={form} />
                    </div>
                </Card>

                <Card className="p-5">
                    <h2 className="mb-1 font-display text-base font-semibold text-ncat-navy">Sign-off</h2>
                    <p className="mb-4 text-sm text-muted-foreground">
                        The two forms do not agree on this line. The purchase order sample signs off
                        &ldquo;Head, Materials and Stores.&rdquo; and the repair order sample signs off
                        &ldquo;Materials and Stores.&rdquo; Both are reproduced as printed; set them to
                        match once the department decides.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Text id="po_prepared_by" label="Purchase order prepared by" form={form} />
                        <Text id="ro_prepared_by" label="Repair order prepared by" form={form} />
                    </div>
                </Card>

                <Card className="p-5">
                    <h2 className="mb-4 font-display text-base font-semibold text-ncat-navy">Printed NOTE text</h2>
                    <div className="grid gap-5">
                        <Note id="po_note" label="Purchase order NOTE" form={form} />
                        <Note id="ro_note" label="Repair order NOTE" form={form} />
                    </div>
                </Card>

                <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <Button type="submit" disabled={form.processing}>Save settings</Button>
                </div>
            </form>
        </AppLayout>
    );
}

import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, Download, Upload, XCircle } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Label } from '@/Components/ui/Label';
import { Select } from '@/Components/ui/Select';
import { Badge } from '@/Components/ui/Badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/Table';

export default function OpeningBalancesIndex({ stores, parts, preview, rows }) {
    // Manual single entry
    const manual = useForm({ part_id: parts[0]?.id || '', store_id: stores[0]?.id || '', quantity: '', unit_price: '', remarks: '' });
    const postManual = (e) => { e.preventDefault(); manual.post(route('opening-balances.store'), { preserveScroll: true, onSuccess: () => manual.reset('quantity') }); };

    // CSV upload
    const upload = useForm({ file: null });
    const postUpload = (e) => { e.preventDefault(); upload.post(route('opening-balances.preview'), { forceFormData: true, preserveScroll: true }); };

    const allValid = preview && preview.every((p) => p.valid);
    const commit = () => router.post(route('opening-balances.import'), { rows }, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Opening Balances" />
            <PageHeader
                eyebrow="Catalogue"
                title="Opening Balances"
                description="Digitise the paper tally cards: enter stock manually or import a CSV."
                icon={ClipboardList}
            />

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Manual entry */}
                <Card>
                    <CardHeader><CardTitle>Single entry</CardTitle></CardHeader>
                    <CardContent>
                        <form onSubmit={postManual} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label>Part</Label>
                                <Select value={manual.data.part_id} onChange={(e) => manual.setData('part_id', e.target.value)}>
                                    {parts.map((p) => <option key={p.id} value={p.id}>{p.part_number} - {p.description}</option>)}
                                </Select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>Store</Label>
                                    <Select value={manual.data.store_id} onChange={(e) => manual.setData('store_id', e.target.value)}>
                                        {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </Select>
                                </div>
                                <div className="space-y-1.5"><Label>Quantity</Label><Input type="number" step="0.01" value={manual.data.quantity} onChange={(e) => manual.setData('quantity', e.target.value)} /></div>
                            </div>
                            <div className="space-y-1.5"><Label>Unit price ₦ (optional)</Label><Input type="number" step="0.01" value={manual.data.unit_price} onChange={(e) => manual.setData('unit_price', e.target.value)} /></div>
                            <Button type="submit" disabled={manual.processing}>Post opening balance</Button>
                        </form>
                    </CardContent>
                </Card>

                {/* CSV import */}
                <Card>
                    <CardHeader><CardTitle>CSV import</CardTitle></CardHeader>
                    <CardContent className="space-y-4">
                        <a href={route('opening-balances.template')}>
                            <Button variant="outline" type="button"><Download className="size-4" /> Download template</Button>
                        </a>
                        <form onSubmit={postUpload} className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>CSV file</Label>
                                <Input type="file" accept=".csv,text/csv" onChange={(e) => upload.setData('file', e.target.files[0])} />
                                {upload.errors.file && <p className="text-sm text-destructive">{upload.errors.file}</p>}
                            </div>
                            <Button type="submit" variant="secondary" disabled={upload.processing || !upload.data.file}>
                                <Upload className="size-4" /> Preview (dry run)
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Dry-run preview */}
            {preview && (
                <Card className="mt-6 overflow-hidden">
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>Import preview: {preview.length} rows, {preview.filter((p) => p.valid).length} valid</CardTitle>
                        <Button onClick={commit} disabled={!allValid}>
                            {allValid ? `Commit ${preview.length} rows` : 'Fix errors to commit'}
                        </Button>
                    </CardHeader>
                    <Table>
                        <TableHeader><TableRow>
                            <TableHead>#</TableHead><TableHead>Part</TableHead><TableHead>Store</TableHead>
                            <TableHead className="text-right">Qty</TableHead><TableHead>Status</TableHead>
                        </TableRow></TableHeader>
                        <TableBody>
                            {preview.map((p) => (
                                <TableRow key={p.row}>
                                    <TableCell>{p.row}</TableCell>
                                    <TableCell className="font-medium">{p.data.part_number || '—'}</TableCell>
                                    <TableCell>{p.data.store_id ? stores.find((s) => s.id === p.data.store_id)?.name : '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{p.data.qty ?? '—'}</TableCell>
                                    <TableCell>
                                        {p.valid
                                            ? <Badge variant="success" className="gap-1"><CheckCircle2 className="size-3" /> OK</Badge>
                                            : <span className="flex items-center gap-1 text-sm text-destructive"><XCircle className="size-3.5" /> {p.errors.join('; ')}</span>}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { BookOpenCheck, ChevronRight, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

export default function TallyIndex({ parts, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const submit = (e) => {
        e.preventDefault();
        router.get(route('tally-cards.index'), { search }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Tally Cards" />
            <PageHeader
                eyebrow="Operations"
                title="Tally Cards"
                description="Per-part per-store ledger cards in the AD38 layout."
                icon={BookOpenCheck}
            />

            <form onSubmit={submit} className="mb-4 max-w-lg">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Search a part to open its tally card…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
            </form>

            <Card className="divide-y divide-border">
                {parts.map((p) => (
                    <Link key={p.id} href={route('tally-cards.show', p.id)}
                        className="flex items-center justify-between px-5 py-3.5 transition-colors hover:bg-accent/60">
                        <div>
                            <p className="font-semibold text-ncat-navy">{p.part_number}</p>
                            <p className="text-xs text-muted-foreground">{p.description}</p>
                        </div>
                        <ChevronRight className="size-4 text-muted-foreground" />
                    </Link>
                ))}
                {!parts.length && <p className="p-8 text-center text-sm text-muted-foreground">No parts found.</p>}
            </Card>
        </AppLayout>
    );
}

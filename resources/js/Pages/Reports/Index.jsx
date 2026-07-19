import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftRight, BarChart3, Boxes, CalendarClock, Plane, ShieldQuestion, ArrowRight,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';

const ICONS = {
    'stock-summary': Boxes,
    movements: ArrowLeftRight,
    expiry: CalendarClock,
    consumption: Plane,
    quarantine: ShieldQuestion,
};

export default function ReportsIndex({ reports = [] }) {
    return (
        <AppLayout>
            <Head title="Reports" />
            <PageHeader
                eyebrow="Analytics"
                title="Reports"
                description="Inventory intelligence — stock levels, movements, expiry, consumption and quarantine, ready to export."
                icon={BarChart3}
            />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((r) => {
                    const Icon = ICONS[r.key] ?? BarChart3;
                    return (
                        <Link key={r.key} href={route('reports.show', r.key)} className="group">
                            <Card className="flex h-full items-start gap-4 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow">
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-ncat-navy text-white shadow-glass transition-colors group-hover:bg-primary">
                                    <Icon className="size-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h3 className="font-display text-base font-semibold leading-tight text-ncat-navy">
                                        {r.title}
                                    </h3>
                                    <p className="mt-1 inline-flex items-center gap-1 text-sm font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                        View report <ArrowRight className="size-3.5" />
                                    </p>
                                </div>
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}

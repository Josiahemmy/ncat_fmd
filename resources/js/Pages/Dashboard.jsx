import { Head, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Activity,
    BarChart3,
    Boxes,
    Fuel,
    Layers,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { StatCard } from '@/Components/ui/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { AlertPanel } from '@/Components/dashboard/AlertPanel';
import { ActivityFeed } from '@/Components/dashboard/ActivityFeed';
import {
    ConsumptionByTypeChart,
    MovementsTrendChart,
    ReceivingVsIssuingChart,
} from '@/Components/dashboard/DashboardCharts';

function greeting(d = new Date()) {
    const h = d.getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}

const longDate = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const naira = new Intl.NumberFormat('en-NG', { maximumFractionDigits: 0 });

/** Compact fuel tile with a mini level gauge (nominal 20,000 L dump). */
function FuelTile({ litres = 0, delay = 0 }) {
    const pct = Math.max(0, Math.min(1, litres / 20000));
    return (
        <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay, ease: [0.22, 1, 0.36, 1] }}
        >
            <Card variant="glass" className="group relative overflow-hidden p-5">
                <div className="pointer-events-none absolute -right-8 -top-8 size-24 rounded-full bg-ncat-cyan/10 blur-2xl" />
                <div className="flex items-start justify-between">
                    <p className="text-sm font-medium text-muted-foreground">Fuel on Hand</p>
                    <span className="flex size-9 items-center justify-center rounded-lg bg-info/10 text-info">
                        <Fuel className="size-[18px]" />
                    </span>
                </div>
                <div className="mt-3 flex items-end gap-1">
                    <span className="font-display text-3xl font-bold leading-none tracking-tight text-ncat-navy">
                        {naira.format(litres)}
                    </span>
                    <span className="ml-1 text-base font-semibold text-muted-foreground">L</span>
                </div>
                <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-muted">
                    <motion.div
                        initial={{ width: 0 }}
                        animate={{ width: `${pct * 100}%` }}
                        transition={{ duration: 0.9, delay: delay + 0.2, ease: [0.22, 1, 0.36, 1] }}
                        className="h-full rounded-full bg-ncat-accent"
                    />
                </div>
                <p className="mt-1.5 text-xs text-muted-foreground">Aviation fuel · Fuel Dump</p>
            </Card>
        </motion.div>
    );
}

export default function Dashboard() {
    const page = usePage().props;
    const user = page.auth?.user ?? { name: 'there' };
    const firstName = (user.name || 'there').split(' ')[0];

    const alertCards = page.alertCards ?? [];
    const kpis = page.kpis ?? {};
    const charts = page.charts ?? {};
    const activity = page.activity ?? [];

    return (
        <AppLayout>
            <Head title="Dashboard" />

            {/* Greeting */}
            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
                className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                        Flight Maintenance Department
                    </p>
                    <h1 className="mt-1 font-display text-2xl font-bold tracking-tight text-ncat-navy sm:text-3xl">
                        {greeting()}, {firstName}.
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">{longDate.format(new Date())}</p>
                </div>
                <Badge variant="neutral" className="w-fit gap-1.5 py-1">
                    <span className="size-1.5 rounded-full bg-success" />
                    Command centre · live
                </Badge>
            </motion.div>

            {/* Alert panel (CAMP-style) */}
            <section className="mb-6">
                <div className="mb-2.5 flex items-center gap-2">
                    <h2 className="font-display text-sm font-semibold uppercase tracking-wide text-ncat-navy/70">
                        Attention required
                    </h2>
                    <span className="h-px flex-1 bg-border" />
                </div>
                <AlertPanel cards={alertCards} />
            </section>

            {/* KPI tiles */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Distinct Parts"
                    value={kpis.distinct_parts ?? 0}
                    icon={Boxes}
                    tone="brand"
                    delay={0}
                    hint="In the catalogue"
                />
                <StatCard
                    label="Stock Value"
                    value={`₦${naira.format(kpis.stock_value ?? 0)}`}
                    icon={Layers}
                    tone="gold"
                    delay={0.06}
                    hint="Where prices are known"
                />
                <StatCard
                    label="Open Work Orders"
                    value={kpis.open_work_orders ?? 0}
                    icon={Wrench}
                    tone="info"
                    delay={0.12}
                    hint="Snags + inspections"
                />
                <FuelTile litres={kpis.fuel_litres ?? 0} delay={0.18} />
            </div>

            {/* Charts */}
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card variant="glass" className="lg:col-span-2">
                    <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="size-[18px] text-primary" />
                            Stock Movement Trend
                        </CardTitle>
                        <Badge variant="outline">Last 12 weeks</Badge>
                    </CardHeader>
                    <CardContent className="pt-2">
                        <MovementsTrendChart data={charts.movements_trend} />
                    </CardContent>
                </Card>

                <Card variant="glass">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2">
                            <BarChart3 className="size-[18px] text-primary" />
                            Consumption by Type
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="pt-2">
                        <ConsumptionByTypeChart data={charts.consumption_by_type} />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card variant="glass" className="lg:col-span-2">
                    <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="flex items-center gap-2">
                            <BarChart3 className="size-[18px] text-primary" />
                            Receiving vs Issuing
                        </CardTitle>
                        <Badge variant="outline">Last 12 months</Badge>
                    </CardHeader>
                    <CardContent className="pt-2">
                        <ReceivingVsIssuingChart data={charts.receiving_vs_issuing} />
                    </CardContent>
                </Card>

                <Card variant="glass">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="size-[18px] text-primary" />
                            Recent Activity
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="max-h-[420px] overflow-y-auto pt-2">
                        <ActivityFeed items={activity} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

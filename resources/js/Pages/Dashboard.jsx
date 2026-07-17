import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Activity,
    BarChart3,
    Boxes,
    ClipboardCheck,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { StatCard } from '@/Components/ui/StatCard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Badge } from '@/Components/ui/Badge';

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

export default function Dashboard() {
    const user = usePage().props.auth?.user ?? { name: 'there' };
    const firstName = (user.name || 'there').split(' ')[0];

    // Simulate the initial data fetch so the KPI tiles play their
    // skeleton → value reveal. Real figures arrive in Phase 4.
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        const t = setTimeout(() => setLoading(false), 750);
        return () => clearTimeout(t);
    }, []);

    return (
        <AppLayout>
            <Head title="Dashboard" />

            {/* Greeting */}
            <motion.div
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
                className="mb-7 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
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
                    System ready · Phase 0
                </Badge>
            </motion.div>

            {/* KPI tiles */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total Parts"
                    value={0}
                    icon={Boxes}
                    tone="brand"
                    loading={loading}
                    delay={0}
                    hint="Catalogue lands in Phase 2"
                />
                <StatCard
                    label="Open Work Orders"
                    value={0}
                    icon={Wrench}
                    tone="info"
                    loading={loading}
                    delay={0.06}
                    hint="Documents land in Phase 3"
                />
                <StatCard
                    label="Pending Approvals"
                    value={0}
                    icon={ClipboardCheck}
                    tone="warning"
                    loading={loading}
                    delay={0.12}
                    hint="Requisition flow in Phase 3"
                />
                <StatCard
                    label="Fleet Aircraft"
                    value={26}
                    icon={TrendingUp}
                    tone="brand"
                    loading={loading}
                    delay={0.18}
                    hint="Across 6 aircraft types"
                />
            </div>

            {/* Charts / activity row */}
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card variant="glass" className="lg:col-span-2">
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="flex items-center gap-2">
                            <BarChart3 className="size-[18px] text-primary" />
                            Stock Movement Trend
                        </CardTitle>
                        <Badge variant="outline">Phase 4</Badge>
                    </CardHeader>
                    <CardContent>
                        <EmptyState
                            icon={BarChart3}
                            title="No movement data yet"
                            description="Once receiving and issuing go live, this chart plots stock in/out across the department's fleet."
                            className="border-0 bg-transparent py-10"
                        />
                    </CardContent>
                </Card>

                <Card variant="glass">
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="size-[18px] text-primary" />
                            Recent Activity
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EmptyState
                            icon={Activity}
                            title="Nothing logged yet"
                            description="User actions and stock transactions will appear here as an audit feed."
                            className="border-0 bg-transparent py-10"
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

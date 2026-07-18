import { Head, Link } from '@inertiajs/react';
import { Flame, Fuel, ShieldQuestion, Warehouse } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';

const META = {
    quarantine: { icon: ShieldQuestion, tint: 'from-warning/20 to-warning/5', ring: 'ring-warning/30' },
    bonded: { icon: Warehouse, tint: 'from-success/20 to-success/5', ring: 'ring-success/30' },
    dope: { icon: Flame, tint: 'from-destructive/20 to-destructive/5', ring: 'ring-destructive/30' },
    fuel: { icon: Fuel, tint: 'from-info/20 to-info/5', ring: 'ring-info/30' },
    general: { icon: Warehouse, tint: 'from-muted to-muted/40', ring: 'ring-border' },
};

export default function StoresIndex({ stores }) {
    return (
        <AppLayout>
            <Head title="Stores" />
            <PageHeader
                eyebrow="Operations"
                title="Stores"
                description="On-hand stock across the department's four stores."
                icon={Warehouse}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stores.map((s) => {
                    const meta = META[s.type] ?? META.general;
                    const Icon = meta.icon;
                    return (
                        <Link key={s.id} href={route('stores.show', s.id)}>
                            <Card variant="glass" className={`group h-full overflow-hidden p-5 ring-1 ${meta.ring} transition-shadow hover:shadow-glass-lg`}>
                                <div className={`absolute inset-x-0 top-0 h-24 bg-gradient-to-b ${meta.tint} opacity-60`} />
                                <div className="relative">
                                    <div className="flex items-start justify-between">
                                        <span className="flex size-11 items-center justify-center rounded-xl bg-ncat-navy text-white shadow-glass">
                                            <Icon className="size-5" />
                                        </span>
                                        {s.awaiting_certification > 0 && (
                                            <Badge variant="warning">{s.awaiting_certification} to certify</Badge>
                                        )}
                                    </div>
                                    <h3 className="mt-4 font-display text-lg font-semibold text-ncat-navy">{s.name}</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">{s.description}</p>
                                    <div className="mt-4 flex items-end justify-between border-t border-border/60 pt-3">
                                        <div>
                                            <p className="font-display text-2xl font-bold text-ncat-navy tabular-nums">{s.item_count}</p>
                                            <p className="text-xs text-muted-foreground">line items</p>
                                        </div>
                                        {s.total_value > 0 && (
                                            <div className="text-right">
                                                <p className="font-semibold text-ncat-navy tabular-nums">₦{s.total_value.toLocaleString()}</p>
                                                <p className="text-xs text-muted-foreground">value</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        </Link>
                    );
                })}
            </div>
        </AppLayout>
    );
}

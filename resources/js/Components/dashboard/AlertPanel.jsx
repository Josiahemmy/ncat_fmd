import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarClock,
    ClipboardCheck,
    PackageX,
    ShieldQuestion,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * CAMP-style alert panel (spec §5.9). Each card is a compact, scannable tile:
 * a live count, a plain-language label, and a one-tap drill-through to the
 * pre-filtered list. Only cards the viewer can act on are passed in.
 *
 * The information design is borrowed from CAMP's inventory alert strip; the
 * visual language is entirely NCAT — glass surfaces, brand tones, no CAMP chrome.
 */
const META = {
    below_reorder: { icon: TrendingUp },
    below_min: { icon: PackageX },
    above_max: { icon: ArrowUpRight },
    expired: { icon: PackageX },
    expiring: { icon: CalendarClock },
    quarantine: { icon: ShieldQuestion },
    requisitions_pending: { icon: ClipboardCheck },
    open_work_orders: { icon: Wrench },
};

const TONES = {
    destructive: {
        ring: 'hover:border-destructive/40',
        glow: 'bg-destructive/10',
        chip: 'bg-destructive/10 text-destructive',
        count: 'text-destructive',
    },
    warning: {
        ring: 'hover:border-warning/40',
        glow: 'bg-warning/10',
        chip: 'bg-warning/15 text-[hsl(30_65%_32%)]',
        count: 'text-[hsl(30_65%_32%)]',
    },
    info: {
        ring: 'hover:border-info/40',
        glow: 'bg-info/10',
        chip: 'bg-info/10 text-info',
        count: 'text-info',
    },
    brand: {
        ring: 'hover:border-primary/40',
        glow: 'bg-ncat-cyan/10',
        chip: 'bg-primary/10 text-primary',
        count: 'text-primary',
    },
};

export function AlertPanel({ cards = [] }) {
    if (!cards.length) {
        return (
            <div className="glass-panel flex items-center gap-3 rounded-lg p-5">
                <span className="flex size-9 items-center justify-center rounded-lg bg-success/10 text-success">
                    <ShieldQuestion className="size-[18px]" />
                </span>
                <div>
                    <p className="font-display text-sm font-semibold text-ncat-navy">All clear</p>
                    <p className="text-xs text-muted-foreground">No alerts need your attention right now.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {cards.map((card, i) => {
                const Icon = META[card.key]?.icon ?? AlertTriangle;
                const tone = TONES[card.tone] ?? TONES.brand;
                const href = route(card.route, card.params ?? {});
                const active = card.count > 0;

                return (
                    <motion.div
                        key={card.key}
                        initial={{ opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.45, delay: i * 0.04, ease: [0.22, 1, 0.36, 1] }}
                    >
                        <Link
                            href={href}
                            className={cn(
                                'group relative flex h-full flex-col justify-between overflow-hidden rounded-lg border border-border bg-card p-4 shadow-glass transition-all duration-300',
                                'hover:-translate-y-0.5 hover:shadow-glass-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                tone.ring,
                            )}
                        >
                            <div
                                className={cn(
                                    'pointer-events-none absolute -right-6 -top-6 size-20 rounded-full opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100',
                                    tone.glow,
                                )}
                            />
                            <div className="flex items-start justify-between">
                                <span className={cn('flex size-8 items-center justify-center rounded-lg', tone.chip)}>
                                    <Icon className="size-4" />
                                </span>
                                <ArrowUpRight className="size-4 text-muted-foreground/40 transition-colors group-hover:text-foreground" />
                            </div>
                            <div className="mt-3">
                                <p className={cn('font-display text-2xl font-bold leading-none tracking-tight', active ? tone.count : 'text-muted-foreground')}>
                                    {card.count}
                                </p>
                                <p className="mt-1 text-xs font-medium text-muted-foreground">{card.label}</p>
                            </div>
                        </Link>
                    </motion.div>
                );
            })}
        </div>
    );
}

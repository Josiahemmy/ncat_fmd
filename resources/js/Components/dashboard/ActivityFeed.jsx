import { motion } from 'framer-motion';
import {
    Activity,
    Boxes,
    LogIn,
    PackageMinus,
    PackagePlus,
    Plane,
    ScrollText,
    ShieldCheck,
    Wrench,
} from 'lucide-react';
import { EmptyState } from '@/Components/ui/EmptyState';
import { cn } from '@/lib/utils';

/** Map a Spatie log_name to an icon + tone so the feed is scannable. */
const LOG_META = {
    stock: { icon: Boxes, tone: 'text-primary bg-primary/10' },
    serial: { icon: ShieldCheck, tone: 'text-info bg-info/10' },
    aircraft: { icon: Plane, tone: 'text-ncat-navy bg-ncat-navy/10' },
    aircraft_type: { icon: Plane, tone: 'text-ncat-navy bg-ncat-navy/10' },
    work_order: { icon: Wrench, tone: 'text-[hsl(30_65%_32%)] bg-warning/15' },
    requisition: { icon: ScrollText, tone: 'text-primary bg-primary/10' },
    srv: { icon: PackagePlus, tone: 'text-success bg-success/10' },
    siv: { icon: PackageMinus, tone: 'text-[hsl(30_65%_32%)] bg-warning/15' },
    auth: { icon: LogIn, tone: 'text-muted-foreground bg-muted' },
    default: { icon: Activity, tone: 'text-muted-foreground bg-muted' },
};

export function ActivityFeed({ items = [] }) {
    if (!items.length) {
        return (
            <EmptyState
                icon={Activity}
                title="Nothing logged yet"
                description="Stock postings, document actions and sign-ins will appear here as an audit trail."
                className="border-0 bg-transparent py-8"
            />
        );
    }

    return (
        <ol className="relative space-y-1">
            {/* connective spine */}
            <span className="absolute left-[18px] top-2 bottom-2 w-px bg-border" aria-hidden />
            {items.map((a, i) => {
                const meta = LOG_META[a.log_name] ?? LOG_META.default;
                const Icon = meta.icon;
                return (
                    <motion.li
                        key={a.id}
                        initial={{ opacity: 0, x: -6 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.35, delay: Math.min(i * 0.03, 0.3), ease: [0.22, 1, 0.36, 1] }}
                        className="relative flex gap-3 rounded-md px-1.5 py-2"
                    >
                        <span className={cn('z-10 mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full ring-4 ring-card', meta.tone)}>
                            <Icon className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm text-foreground">{a.description}</p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                <span className="font-medium text-foreground/70">{a.causer}</span> · {a.at}
                            </p>
                        </div>
                    </motion.li>
                );
            })}
        </ol>
    );
}

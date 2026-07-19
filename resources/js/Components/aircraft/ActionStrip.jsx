import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowUpRight, BookOpenCheck, PackageMinus, PackagePlus, ScrollText, Wrench } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The animated horizontal action strip on the Aircraft Workspace. Each card
 * springs into place with a stagger, lifts on hover, and deep-links into its
 * module pre-filtered to this aircraft.
 */
const CARDS = [
    { key: 'work_orders', label: 'Work Orders', icon: Wrench, tone: 'text-[hsl(30_65%_32%)] bg-warning/15' },
    { key: 'requisitions', label: 'Requisitions', icon: ScrollText, tone: 'text-primary bg-primary/10' },
    { key: 'receiving', label: 'Receiving', icon: PackagePlus, tone: 'text-success bg-success/10' },
    { key: 'issuing', label: 'Issuing', icon: PackageMinus, tone: 'text-info bg-info/10' },
    { key: 'tally', label: 'Tally Cards', icon: BookOpenCheck, tone: 'text-ncat-navy bg-ncat-navy/10' },
];

const container = {
    hidden: {},
    show: { transition: { staggerChildren: 0.07, delayChildren: 0.1 } },
};

const item = {
    hidden: { opacity: 0, y: 18, scale: 0.96 },
    show: { opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 380, damping: 26 } },
};

export function ActionStrip({ links = {} }) {
    return (
        <motion.div
            variants={container}
            initial="hidden"
            animate="show"
            className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
        >
            {CARDS.map((card) => {
                const Icon = card.icon;
                const href = links[card.key];
                if (!href) return null;
                return (
                    <motion.div key={card.key} variants={item} whileHover={{ y: -4 }} whileTap={{ scale: 0.97 }}>
                        <Link
                            href={href}
                            className="group relative flex h-full flex-col gap-3 overflow-hidden rounded-xl border border-border bg-card p-4 shadow-glass transition-shadow duration-300 hover:shadow-glass-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="flex items-center justify-between">
                                <span className={cn('flex size-10 items-center justify-center rounded-lg', card.tone)}>
                                    <Icon className="size-5" />
                                </span>
                                <ArrowUpRight className="size-4 text-muted-foreground/40 transition-all duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground" />
                            </div>
                            <span className="font-display text-sm font-semibold text-ncat-navy">{card.label}</span>
                        </Link>
                    </motion.div>
                );
            })}
        </motion.div>
    );
}

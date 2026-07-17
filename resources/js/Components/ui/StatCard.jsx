import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowDownRight, ArrowUpRight } from 'lucide-react';
import { Card } from '@/Components/ui/Card';
import { Skeleton } from '@/Components/ui/Skeleton';
import { cn } from '@/lib/utils';

/**
 * StatCard — dashboard KPI tile. While `loading`, shows a shimmer skeleton;
 * when the value arrives it counts up and fades into place. `trend` (a number)
 * renders a subtle up/down delta chip.
 *
 * `tone` tints the leading icon: brand | success | warning | info | gold.
 */
const toneStyles = {
    brand: 'bg-primary/10 text-primary',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-[hsl(30_65%_32%)]',
    info: 'bg-info/10 text-info',
    gold: 'bg-ncat-gold/20 text-[hsl(38_90%_28%)]',
};

function useCountUp(target, run, duration = 900) {
    const [value, setValue] = useState(0);
    useEffect(() => {
        if (!run || typeof target !== 'number') return;
        let raf;
        const start = performance.now();
        const tick = (now) => {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            setValue(Math.round(target * eased));
            if (p < 1) raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [target, run, duration]);
    return value;
}

function StatCard({
    label,
    value,
    unit,
    icon: Icon,
    tone = 'brand',
    trend,
    hint,
    loading = false,
    delay = 0,
    className,
}) {
    const isNumeric = typeof value === 'number';
    const counted = useCountUp(isNumeric ? value : 0, !loading && isNumeric);
    const display = loading ? null : isNumeric ? counted.toLocaleString() : value;

    return (
        <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay, ease: [0.22, 1, 0.36, 1] }}
        >
            <Card variant="glass" className={cn('group relative overflow-hidden p-5', className)}>
                {/* subtle corner glow */}
                <div className="pointer-events-none absolute -right-8 -top-8 size-24 rounded-full bg-ncat-cyan/10 blur-2xl transition-opacity group-hover:opacity-100" />
                <div className="flex items-start justify-between">
                    <p className="text-sm font-medium text-muted-foreground">{label}</p>
                    {Icon && (
                        <span className={cn('flex size-9 items-center justify-center rounded-lg', toneStyles[tone])}>
                            <Icon className="size-[18px]" />
                        </span>
                    )}
                </div>

                <div className="mt-3 flex items-end gap-2">
                    {loading ? (
                        <Skeleton className="h-9 w-24" />
                    ) : (
                        <span className="font-display text-3xl font-bold leading-none tracking-tight text-ncat-navy">
                            {display}
                            {unit && <span className="ml-1 text-base font-semibold text-muted-foreground">{unit}</span>}
                        </span>
                    )}
                </div>

                <div className="mt-3 flex items-center gap-2">
                    {typeof trend === 'number' && !loading && (
                        <span
                            className={cn(
                                'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold',
                                trend >= 0 ? 'bg-success/10 text-success' : 'bg-destructive/10 text-destructive',
                            )}
                        >
                            {trend >= 0 ? <ArrowUpRight className="size-3" /> : <ArrowDownRight className="size-3" />}
                            {Math.abs(trend)}%
                        </span>
                    )}
                    {hint && <span className="text-xs text-muted-foreground">{hint}</span>}
                </div>
            </Card>
        </motion.div>
    );
}

export { StatCard };

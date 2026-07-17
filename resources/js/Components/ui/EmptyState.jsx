import { motion } from 'framer-motion';
import { PackageOpen } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * EmptyState — friendly "nothing here yet" panel with a soft branded
 * illustration halo. Pass `action` for a call-to-action button.
 */
function EmptyState({
    title = 'Nothing here yet',
    description,
    icon: Icon = PackageOpen,
    action,
    className,
}) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 px-6 py-14 text-center',
                className,
            )}
        >
            <div className="relative mb-5">
                <span className="absolute inset-0 -z-10 animate-pulse rounded-full bg-primary/10 blur-xl" />
                <span className="flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-ncat-blue/15 to-ncat-cyan/10 text-primary ring-1 ring-inset ring-primary/20">
                    <Icon className="size-7" />
                </span>
            </div>
            <h3 className="font-display text-base font-semibold text-ncat-navy">{title}</h3>
            {description && (
                <p className="mt-1.5 max-w-sm text-sm text-muted-foreground">{description}</p>
            )}
            {action && <div className="mt-6">{action}</div>}
        </motion.div>
    );
}

export { EmptyState };

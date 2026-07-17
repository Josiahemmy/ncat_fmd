import { motion } from 'framer-motion';
import { cn } from '@/lib/utils';

/**
 * PageHeader — the consistent title block at the top of every module page.
 * `eyebrow` is a small uppercase kicker; `actions` renders buttons on the right.
 */
function PageHeader({ title, description, eyebrow, actions, icon: Icon, className }) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
                'flex flex-col gap-4 pb-6 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="flex items-start gap-4">
                {Icon && (
                    <span className="mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-xl bg-ncat-navy text-white shadow-glass">
                        <Icon className="size-5" />
                    </span>
                )}
                <div>
                    {eyebrow && (
                        <p className="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                            {eyebrow}
                        </p>
                    )}
                    <h1 className="font-display text-2xl font-bold tracking-tight text-ncat-navy sm:text-[1.75rem]">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{description}</p>
                    )}
                </div>
            </div>
            {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
        </motion.div>
    );
}

export { PageHeader };

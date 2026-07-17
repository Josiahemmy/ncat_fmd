import * as React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-primary/10 text-primary',
                navy: 'border-transparent bg-ncat-navy/10 text-ncat-navy',
                neutral: 'border-border bg-muted text-muted-foreground',
                success: 'border-transparent bg-success/10 text-success',
                warning: 'border-transparent bg-warning/10 text-[hsl(30_65%_30%)]',
                error: 'border-transparent bg-destructive/10 text-destructive',
                info: 'border-transparent bg-info/10 text-info',
                // Reserved for achievements / recognition (use sparingly)
                gold: 'border-transparent bg-ncat-gold/20 text-[hsl(38_90%_28%)]',
                outline: 'border-border text-foreground',
            },
        },
        defaultVariants: { variant: 'default' },
    },
);

function Badge({ className, variant, ...props }) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };

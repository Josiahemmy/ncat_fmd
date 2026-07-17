import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 active:translate-y-px select-none',
    {
        variants: {
            variant: {
                // Primary — Aviation Blue, the default action
                default:
                    'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 hover:shadow-glow',
                // Deep Navy — strong secondary emphasis
                navy: 'bg-ncat-navy text-white shadow-sm hover:bg-ncat-navy/90',
                // Gold — reserved for achievements / recognition (use sparingly)
                gold: 'bg-ncat-gold text-ncat-midnight shadow-sm hover:brightness-105',
                destructive:
                    'bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/90',
                outline:
                    'border border-input bg-transparent text-foreground hover:border-primary/40 hover:bg-accent hover:text-accent-foreground',
                secondary:
                    'bg-muted text-foreground hover:bg-muted/70',
                ghost: 'text-foreground hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3',
                lg: 'h-12 rounded-md px-7 text-base',
                icon: 'size-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

const Button = React.forwardRef(
    ({ className, variant, size, asChild = false, ...props }, ref) => {
        const Comp = asChild ? Slot : 'button';
        return (
            <Comp
                ref={ref}
                className={cn(buttonVariants({ variant, size, className }))}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';

export { Button, buttonVariants };

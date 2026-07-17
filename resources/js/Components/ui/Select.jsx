import * as React from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Select — native <select> skinned to the NCAT system. A native control keeps
 * the kit lightweight and fully accessible/keyboard-friendly out of the box;
 * a Radix listbox can layer on later where rich option rendering is needed.
 */
const Select = React.forwardRef(({ className, children, ...props }, ref) => (
    <div className="relative">
        <select
            ref={ref}
            className={cn(
                'flex h-10 w-full appearance-none rounded-md border border-input bg-white px-3 pr-9 text-sm text-foreground shadow-sm transition-colors',
                'focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/40',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            {children}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
    </div>
));
Select.displayName = 'Select';

export { Select };

import * as React from 'react';
import { cn } from '@/lib/utils';

const Input = React.forwardRef(({ className, type = 'text', ...props }, ref) => (
    <input
        ref={ref}
        type={type}
        className={cn(
            'flex h-10 w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-foreground shadow-sm transition-colors',
            'placeholder:text-muted-foreground/70',
            'focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/40',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'file:border-0 file:bg-transparent file:text-sm file:font-medium',
            className,
        )}
        {...props}
    />
));
Input.displayName = 'Input';

export { Input };

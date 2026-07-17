import * as React from 'react';
import { Calendar } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * DatePicker — a branded wrapper over the native date input. Native gives us
 * a locale-aware, accessible calendar for free; the NCAT skin (icon, focus
 * ring, radius) keeps it consistent with the rest of the kit.
 */
const DatePicker = React.forwardRef(({ className, ...props }, ref) => (
    <div className="relative">
        <Calendar className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
            ref={ref}
            type="date"
            className={cn(
                'flex h-10 w-full rounded-md border border-input bg-white pl-9 pr-3 text-sm text-foreground shadow-sm transition-colors',
                'focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/40',
                'disabled:cursor-not-allowed disabled:opacity-50',
                '[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:inset-0 [&::-webkit-calendar-picker-indicator]:w-full [&::-webkit-calendar-picker-indicator]:cursor-pointer',
                className,
            )}
            {...props}
        />
    </div>
));
DatePicker.displayName = 'DatePicker';

export { DatePicker };

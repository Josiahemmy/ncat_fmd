import { cn } from '@/lib/utils';

/** Aircraft airworthiness status chip (active / maintenance / retired). */
const STYLES = {
    active: { dot: 'bg-success', text: 'text-success', bg: 'bg-success/10', label: 'Active' },
    maintenance: { dot: 'bg-warning', text: 'text-[hsl(30_65%_32%)]', bg: 'bg-warning/15', label: 'Maintenance' },
    retired: { dot: 'bg-muted-foreground', text: 'text-muted-foreground', bg: 'bg-muted', label: 'Retired' },
};

export function AircraftStatusChip({ status, className }) {
    const s = STYLES[status] ?? STYLES.retired;
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold', s.bg, s.text, className)}>
            <span className={cn('size-1.5 rounded-full', s.dot)} />
            {s.label}
        </span>
    );
}

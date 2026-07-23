import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import {
    ArrowUpRight,
    Bell,
    CalendarClock,
    Check,
    ClipboardCheck,
    PackageX,
    ShieldQuestion,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const ICON = {
    below_reorder: TrendingUp,
    below_min: PackageX,
    above_max: ArrowUpRight,
    expired: PackageX,
    expiring: CalendarClock,
    quarantine: ShieldQuestion,
    requisitions_pending: ClipboardCheck,
    open_work_orders: Wrench,
};

const DOT = {
    destructive: 'bg-destructive',
    warning: 'bg-warning',
    info: 'bg-info',
    brand: 'bg-primary',
};

const SEEN_KEY = 'ncat.alertsSeen';

/**
 * Notification bell — the live, permission-filtered alert groups (served by the
 * cached DashboardService path). Grouped by alert type with humanized lines, a
 * mark-as-seen control, and a "view all" deep-link per group.
 */
export function NotificationsBell() {
    const page = usePage().props;
    const { count = 0, groups = [] } = page.alerts ?? {};

    const [seenCount, setSeenCount] = useState(0);
    useEffect(() => {
        setSeenCount(Number(localStorage.getItem(SEEN_KEY) || 0));
    }, []);

    // Unread = current count exceeds what the user last acknowledged.
    const unread = Math.max(0, count - seenCount);
    const showBadge = unread > 0;

    const markSeen = () => {
        localStorage.setItem(SEEN_KEY, String(count));
        setSeenCount(count);
    };

    return (
        <DropdownMenu.Root onOpenChange={(o) => o && count > 0 && markSeen()}>
            <DropdownMenu.Trigger asChild>
                <button
                    className="relative rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    aria-label={`Alerts (${count})`}
                >
                    <Bell className="size-5" />
                    {showBadge && (
                        <span className="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-white ring-2 ring-background">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </button>
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content
                    align="end"
                    sideOffset={8}
                    className="z-50 w-[22rem] rounded-xl border border-border bg-popover p-0 text-popover-foreground shadow-glass-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
                >
                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                        <div>
                            <p className="font-display text-sm font-semibold text-ncat-navy">Alerts</p>
                            <p className="text-xs text-muted-foreground">{count} need attention</p>
                        </div>
                        {count > 0 && (
                            <button
                                onClick={markSeen}
                                className="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                <Check className="size-3.5" /> Mark seen
                            </button>
                        )}
                    </div>

                    <div className="max-h-[70vh] overflow-y-auto p-1.5">
                        {groups.length === 0 && (
                            <p className="px-2.5 py-8 text-center text-sm text-muted-foreground">All clear. Nothing needs attention.</p>
                        )}

                        {groups.map((g) => {
                            const Icon = ICON[g.key] ?? Bell;
                            return (
                                <div key={g.key} className="mb-1.5">
                                    <div className="flex items-center gap-2 px-2.5 py-1.5">
                                        <span className={cn('size-1.5 rounded-full', DOT[g.tone] ?? DOT.brand)} />
                                        <span className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            <Icon className="size-3.5" /> {g.label}
                                        </span>
                                        <span className="ml-auto rounded-full bg-muted px-1.5 text-xs font-bold text-foreground">{g.count}</span>
                                    </div>
                                    <ul>
                                        {(g.items ?? []).slice(0, 3).map((it, i) => (
                                            <li key={i}>
                                                <Link href={it.href} className="block truncate rounded-md px-3 py-1.5 text-sm text-foreground transition-colors hover:bg-accent">
                                                    {it.label}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                    <Link
                                        href={route(g.route, g.params ?? {})}
                                        className="mx-2.5 mt-0.5 flex items-center gap-1 rounded-md px-1 py-1 text-xs font-semibold text-primary transition-colors hover:underline"
                                    >
                                        View all {g.label.toLowerCase()} <ArrowUpRight className="size-3" />
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
                </DropdownMenu.Content>
            </DropdownMenu.Portal>
        </DropdownMenu.Root>
    );
}

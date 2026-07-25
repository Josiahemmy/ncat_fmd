import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import {
    ArrowUpRight,
    Bell,
    CalendarClock,
    Check,
    ClipboardCheck,
    Inbox,
    PackageMinus,
    PackageX,
    ShieldQuestion,
    Stamp,
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
    // Event notifications (database channel)
    requisition_awaiting_approval: Stamp,
    requisition_decided: ClipboardCheck,
    requisition_ready_for_issue: PackageMinus,
    low_stock: TrendingUp,
};

const DOT = {
    destructive: 'bg-destructive',
    warning: 'bg-warning',
    info: 'bg-info',
    brand: 'bg-primary',
};

const SEEN_KEY = 'ncat.alertsSeen';

/**
 * Notification bell. Two independent feeds live here:
 *
 *  - `notices`: event notifications from the database channel (approval
 *    decisions, low stock). Real records, so mark-as-read is server-side.
 *  - `alerts`: the live, permission-filtered stock alert groups computed by
 *    DashboardService. Nothing to persist, so "seen" is a local acknowledgement.
 */
export function NotificationsBell() {
    const page = usePage().props;
    const { count = 0, groups = [] } = page.alerts ?? {};
    const { count: noticeCount = 0, items: notices = [] } = page.notices ?? {};

    const [seenCount, setSeenCount] = useState(0);
    useEffect(() => {
        setSeenCount(Number(localStorage.getItem(SEEN_KEY) || 0));
    }, []);

    // Unread = current count exceeds what the user last acknowledged.
    const unread = Math.max(0, count - seenCount) + noticeCount;
    const showBadge = unread > 0;
    const total = count + noticeCount;

    const markSeen = () => {
        localStorage.setItem(SEEN_KEY, String(count));
        setSeenCount(count);
    };

    const markAllRead = () => {
        markSeen();
        if (noticeCount > 0) {
            router.post(route('notifications.read'), {}, { preserveScroll: true, preserveState: true });
        }
    };

    const markRead = (id) => router.post(
        route('notifications.read'), { id }, { preserveScroll: true, preserveState: true },
    );

    return (
        <DropdownMenu.Root>
            <DropdownMenu.Trigger asChild>
                <button
                    className="relative rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    aria-label={`Notifications (${total})`}
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
                            <p className="font-display text-sm font-semibold text-ncat-navy">Notifications</p>
                            <p className="text-xs text-muted-foreground">{total} need attention</p>
                        </div>
                        {total > 0 && (
                            <button
                                onClick={markAllRead}
                                className="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                                <Check className="size-3.5" /> Mark read
                            </button>
                        )}
                    </div>

                    <div className="max-h-[70vh] overflow-y-auto p-1.5">
                        {groups.length === 0 && notices.length === 0 && (
                            <p className="px-2.5 py-8 text-center text-sm text-muted-foreground">All clear. Nothing needs attention.</p>
                        )}

                        {notices.length > 0 && (
                            <div className="mb-1.5">
                                <div className="flex items-center gap-2 px-2.5 py-1.5">
                                    <span className="size-1.5 rounded-full bg-primary" />
                                    <span className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        <Inbox className="size-3.5" /> Activity
                                    </span>
                                    <span className="ml-auto rounded-full bg-muted px-1.5 text-xs font-bold text-foreground">{noticeCount}</span>
                                </div>
                                <ul>
                                    {notices.map((n) => {
                                        const Icon = ICON[n.type] ?? Bell;
                                        return (
                                            <li key={n.id} className="group/notice flex items-start gap-1">
                                                <Link
                                                    href={n.href ?? '#'}
                                                    onClick={() => markRead(n.id)}
                                                    className="flex min-w-0 flex-1 gap-2 rounded-md px-3 py-2 transition-colors hover:bg-accent"
                                                >
                                                    <Icon className="mt-0.5 size-3.5 shrink-0 text-primary" />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium text-foreground">{n.title}</span>
                                                        <span className="block truncate text-xs text-muted-foreground">{n.message}</span>
                                                        <span className="block text-[0.7rem] text-muted-foreground">{n.at}</span>
                                                    </span>
                                                </Link>
                                                <button
                                                    onClick={() => markRead(n.id)}
                                                    aria-label="Mark as read"
                                                    title="Mark as read"
                                                    className="mt-2 rounded-md p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-accent hover:text-foreground focus:opacity-100 group-hover/notice:opacity-100"
                                                >
                                                    <Check className="size-3.5" />
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                                <Link
                                    href={route('notifications.index')}
                                    className="mx-2.5 mt-0.5 flex items-center gap-1 rounded-md px-1 py-1 text-xs font-semibold text-primary transition-colors hover:underline"
                                >
                                    View all activity <ArrowUpRight className="size-3" />
                                </Link>
                            </div>
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

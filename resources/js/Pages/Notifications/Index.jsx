import { Head, Link, router } from '@inertiajs/react';
import {
    Bell, Check, ClipboardCheck, Inbox, PackageMinus, Stamp, TrendingUp,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { cn } from '@/lib/utils';

const ICON = {
    requisition_awaiting_approval: Stamp,
    requisition_decided: ClipboardCheck,
    requisition_ready_for_issue: PackageMinus,
    low_stock: TrendingUp,
};

const TABS = [
    { key: 'unread', label: 'Unread' },
    { key: 'all', label: 'Everything' },
];

/**
 * The full activity feed for event notifications. Read rows stay visible under
 * "Everything" so a decision trail can be re-read after it has been cleared.
 */
export default function NotificationsIndex({ notifications, filters, unreadCount }) {
    const markRead = (id) => router.post(
        route('notifications.read'), { id }, { preserveScroll: true },
    );
    const markAllRead = () => router.post(route('notifications.read'), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Notifications" />
            <PageHeader
                eyebrow="Activity"
                title="Notifications"
                description="Approval decisions, issue alerts and stock notices addressed to you."
                icon={Bell}
                actions={unreadCount > 0 && (
                    <Button variant="outline" onClick={markAllRead}>
                        <Check className="size-4" /> Mark all read
                    </Button>
                )}
            />

            <div className="mb-6 flex items-center gap-1 border-b border-border">
                {TABS.map((t) => (
                    <Link
                        key={t.key}
                        href={route('notifications.index', { filter: t.key })}
                        className={cn(
                            'flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm font-medium transition-colors',
                            filters.filter === t.key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t.label}
                        {t.key === 'unread' && unreadCount > 0 && (
                            <Badge variant="default">{unreadCount}</Badge>
                        )}
                    </Link>
                ))}
            </div>

            {notifications.length === 0 ? (
                <EmptyState
                    icon={Inbox}
                    title={filters.filter === 'unread' ? 'Nothing unread' : 'No notifications yet'}
                    description="Approvals, rejections and issue alerts addressed to you will land here."
                />
            ) : (
                <div className="max-w-3xl space-y-2">
                    {notifications.map((n) => {
                        const Icon = ICON[n.type] ?? Bell;
                        return (
                            <Card
                                key={n.id}
                                className={cn('flex items-start gap-3 p-4', n.read && 'opacity-70')}
                            >
                                <span className={cn(
                                    'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg',
                                    n.read ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary',
                                )}>
                                    <Icon className="size-4" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-baseline gap-x-2">
                                        <p className="font-display text-sm font-semibold text-ncat-navy">{n.title}</p>
                                        {!n.read && <Badge variant="default">New</Badge>}
                                    </div>
                                    <p className="mt-0.5 text-sm text-muted-foreground">{n.message}</p>
                                    <p className="mt-1 text-xs text-muted-foreground" title={n.at_full}>{n.at}</p>
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {n.href && (
                                        <Button asChild variant="ghost" size="sm" onClick={() => !n.read && markRead(n.id)}>
                                            <Link href={n.href}>Open</Link>
                                        </Button>
                                    )}
                                    {!n.read && (
                                        <Button
                                            variant="ghost" size="icon" title="Mark as read"
                                            aria-label={`Mark "${n.title}" as read`}
                                            onClick={() => markRead(n.id)}
                                        >
                                            <Check className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        );
                    })}
                </div>
            )}
        </AppLayout>
    );
}

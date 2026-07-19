import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { Wordmark } from '@/Components/brand/Wordmark';
import { navGroups, navItems } from '@/Components/app/nav';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';

function isActive(routeName) {
    try {
        return typeof route !== 'undefined' && route().current(routeName);
    } catch {
        return false;
    }
}

/**
 * Sidebar — the dark-navy module rail. Renders identically inside the desktop
 * column and the mobile drawer. `collapsed` shrinks it to an icon rail;
 * `onNavigate` lets the mobile drawer close on selection.
 */
export function Sidebar({ collapsed = false, onToggleCollapse, onNavigate }) {
    const { can, canAny } = usePermissions();
    const badges = usePage().props.badges ?? {};

    const isVisible = (item) => {
        if (item.permission) return can(item.permission);
        if (item.permissionsAny) return canAny(item.permissionsAny);
        return true;
    };

    return (
        <div className="flex h-full flex-col bg-sidebar text-sidebar-foreground">
            {/* Brand + collapse control */}
            <div
                className={cn(
                    'flex h-16 shrink-0 items-center border-b border-sidebar-border',
                    collapsed ? 'justify-center px-2' : 'justify-between px-4',
                )}
            >
                <Link href={route('dashboard')} onClick={onNavigate} className="overflow-hidden">
                    {collapsed ? (
                        <img src="/brand/ncat-icon-256.png" alt="NCAT" className="size-9 rounded-lg" />
                    ) : (
                        <Wordmark tone="light" />
                    )}
                </Link>
                {onToggleCollapse && !collapsed && (
                    <button
                        onClick={onToggleCollapse}
                        className="hidden rounded-md p-1.5 text-sidebar-muted transition-colors hover:bg-white/5 hover:text-white lg:block"
                        aria-label="Collapse sidebar"
                    >
                        <PanelLeftClose className="size-5" />
                    </button>
                )}
            </div>

            {/* Nav */}
            <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                {navGroups.map((group) => {
                    const items = navItems.filter((i) => i.group === group.key && isVisible(i));
                    if (!items.length) return null;
                    return (
                        <div key={group.key}>
                            {group.label && !collapsed && (
                                <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-sidebar-muted/70">
                                    {group.label}
                                </p>
                            )}
                            <ul className="space-y-1">
                                {items.map((item) => {
                                    const active = isActive(item.routeName);
                                    const Icon = item.icon;
                                    const count = item.badge ? badges[item.badge] : 0;
                                    return (
                                        <li key={item.routeName}>
                                            <Link
                                                href={route(item.routeName)}
                                                onClick={onNavigate}
                                                title={collapsed ? item.label : undefined}
                                                className={cn(
                                                    'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                                    collapsed && 'justify-center px-0',
                                                    active
                                                        ? 'bg-white/10 text-white'
                                                        : 'text-sidebar-muted hover:bg-white/5 hover:text-white',
                                                )}
                                            >
                                                {active && (
                                                    <motion.span
                                                        layoutId="nav-active"
                                                        className="absolute inset-y-1.5 left-0 w-1 rounded-r-full bg-sidebar-accent"
                                                        transition={{ type: 'spring', stiffness: 400, damping: 32 }}
                                                    />
                                                )}
                                                <Icon
                                                    className={cn(
                                                        'size-[18px] shrink-0 transition-colors',
                                                        active ? 'text-sidebar-accent' : 'text-current',
                                                    )}
                                                />
                                                {!collapsed && <span className="truncate">{item.label}</span>}
                                                {!collapsed && count > 0 && (
                                                    <span className="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-sidebar-accent px-1.5 py-0.5 text-[11px] font-semibold text-white">
                                                        {count}
                                                    </span>
                                                )}
                                                {collapsed && count > 0 && (
                                                    <span className="absolute right-1 top-1 size-2 rounded-full bg-sidebar-accent" />
                                                )}
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    );
                })}
            </nav>

            {/* Footer */}
            <div className="shrink-0 border-t border-sidebar-border p-3">
                {collapsed ? (
                    onToggleCollapse && (
                        <button
                            onClick={onToggleCollapse}
                            className="hidden w-full items-center justify-center rounded-md p-2 text-sidebar-muted transition-colors hover:bg-white/5 hover:text-white lg:flex"
                            aria-label="Expand sidebar"
                        >
                            <PanelLeftOpen className="size-5" />
                        </button>
                    )
                ) : (
                    <div className="rounded-lg bg-white/[0.04] px-3 py-2.5">
                        <p className="text-xs font-semibold text-white">NCAT Fleet</p>
                        <p className="mt-0.5 text-[11px] text-sidebar-muted">26 aircraft · 6 types</p>
                    </div>
                )}
            </div>
        </div>
    );
}

import { Link, usePage } from '@inertiajs/react';
import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { ChevronDown, LogOut, Menu, Settings, UserRound } from 'lucide-react';
import { GlobalSearch } from '@/Components/app/GlobalSearch';
import { NotificationsBell } from '@/Components/app/NotificationsBell';
import { cn } from '@/lib/utils';

function initials(name = '') {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}

/**
 * Topbar — global search (placeholder for Phase 1), notifications bell
 * (placeholder), and the user menu. `onOpenSidebar` opens the mobile drawer.
 */
export function Topbar({ onOpenSidebar }) {
    const page = usePage().props;
    const user = page.auth?.user ?? { name: 'NCAT User', email: '' };

    return (
        <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur-md sm:px-6">
            {/* Mobile drawer toggle */}
            <button
                onClick={onOpenSidebar}
                className="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground lg:hidden"
                aria-label="Open navigation"
            >
                <Menu className="size-5" />
            </button>

            {/* Global typeahead */}
            <GlobalSearch />

            <div className="ml-auto flex items-center gap-1.5 sm:gap-2">
                {/* Alerts bell — live, grouped, permission-filtered */}
                <NotificationsBell />

                {/* User menu */}
                <DropdownMenu.Root>
                    <DropdownMenu.Trigger asChild>
                        <button className="flex items-center gap-2 rounded-lg p-1 pr-2 transition-colors hover:bg-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            <span className="flex size-9 items-center justify-center rounded-full bg-ncat-navy text-xs font-semibold text-white">
                                {initials(user.name) || <UserRound className="size-4" />}
                            </span>
                            <span className="hidden text-left leading-tight sm:block">
                                <span className="block max-w-[10rem] truncate text-sm font-semibold text-ncat-navy">
                                    {user.name}
                                </span>
                                <span className="block max-w-[10rem] truncate text-xs text-muted-foreground">
                                    {user.email}
                                </span>
                            </span>
                            <ChevronDown className="hidden size-4 text-muted-foreground sm:block" />
                        </button>
                    </DropdownMenu.Trigger>

                    <DropdownMenu.Portal>
                        <DropdownMenu.Content
                            align="end"
                            sideOffset={8}
                            className={cn(
                                'z-50 w-56 rounded-lg border border-border bg-popover p-1.5 text-popover-foreground shadow-glass-lg',
                                'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                            )}
                        >
                            <div className="px-2.5 py-2">
                                <p className="truncate text-sm font-semibold text-ncat-navy">{user.name}</p>
                                <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                            </div>
                            <DropdownMenu.Separator className="my-1 h-px bg-border" />
                            <DropdownMenu.Item asChild>
                                <Link
                                    href={route('profile.edit')}
                                    className="flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-2 text-sm outline-none transition-colors focus:bg-accent"
                                >
                                    <UserRound className="size-4 text-muted-foreground" />
                                    Profile
                                </Link>
                            </DropdownMenu.Item>
                            <DropdownMenu.Item asChild>
                                <Link
                                    href={route('admin.dashboard')}
                                    className="flex cursor-pointer items-center gap-2 rounded-md px-2.5 py-2 text-sm outline-none transition-colors focus:bg-accent"
                                >
                                    <Settings className="size-4 text-muted-foreground" />
                                    Administration
                                </Link>
                            </DropdownMenu.Item>
                            <DropdownMenu.Separator className="my-1 h-px bg-border" />
                            <DropdownMenu.Item asChild>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="flex w-full cursor-pointer items-center gap-2 rounded-md px-2.5 py-2 text-sm text-destructive outline-none transition-colors focus:bg-destructive/10"
                                >
                                    <LogOut className="size-4" />
                                    Sign out
                                </Link>
                            </DropdownMenu.Item>
                        </DropdownMenu.Content>
                    </DropdownMenu.Portal>
                </DropdownMenu.Root>
            </div>
        </header>
    );
}

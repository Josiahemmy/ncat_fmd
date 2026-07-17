import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { X } from 'lucide-react';
import { Sidebar } from '@/Components/app/Sidebar';
import { Topbar } from '@/Components/app/Topbar';
import { ToastProvider } from '@/Components/ui/Toast';
import { cn } from '@/lib/utils';

const COLLAPSE_KEY = 'ncat.sidebar.collapsed';

/**
 * AppLayout — the authenticated application shell.
 *  · Desktop: fixed collapsible dark-navy sidebar + sticky topbar.
 *  · Mobile : sidebar becomes an off-canvas drawer.
 *  · Page transitions via Framer Motion, keyed on the Inertia URL.
 *
 * Accepts optional `header` (rendered in a band above content) for
 * compatibility with pages that pass one; most module pages use <PageHeader/>.
 */
export default function AppLayout({ header, children }) {
    const { url } = usePage();
    const [collapsed, setCollapsed] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);

    // Restore collapse preference.
    useEffect(() => {
        try {
            setCollapsed(localStorage.getItem(COLLAPSE_KEY) === '1');
        } catch {
            /* no-op */
        }
    }, []);

    const toggleCollapse = () => {
        setCollapsed((prev) => {
            const next = !prev;
            try {
                localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0');
            } catch {
                /* no-op */
            }
            return next;
        });
    };

    // Close the mobile drawer on navigation.
    useEffect(() => setDrawerOpen(false), [url]);

    return (
        <ToastProvider>
            <div className="min-h-screen bg-background">
                {/* Desktop sidebar */}
                <aside
                    className={cn(
                        'fixed inset-y-0 left-0 z-40 hidden shrink-0 border-r border-sidebar-border transition-[width] duration-300 ease-in-out lg:block',
                        collapsed ? 'w-[76px]' : 'w-64',
                    )}
                >
                    <Sidebar collapsed={collapsed} onToggleCollapse={toggleCollapse} />
                </aside>

                {/* Mobile drawer */}
                <AnimatePresence>
                    {drawerOpen && (
                        <>
                            <motion.div
                                key="scrim"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                onClick={() => setDrawerOpen(false)}
                                className="fixed inset-0 z-40 bg-ncat-midnight/60 backdrop-blur-sm lg:hidden"
                            />
                            <motion.aside
                                key="drawer"
                                initial={{ x: '-100%' }}
                                animate={{ x: 0 }}
                                exit={{ x: '-100%' }}
                                transition={{ type: 'spring', stiffness: 360, damping: 38 }}
                                className="fixed inset-y-0 left-0 z-50 w-72 lg:hidden"
                            >
                                <button
                                    onClick={() => setDrawerOpen(false)}
                                    className="absolute -right-11 top-3 rounded-md bg-white/10 p-2 text-white backdrop-blur"
                                    aria-label="Close navigation"
                                >
                                    <X className="size-5" />
                                </button>
                                <Sidebar onNavigate={() => setDrawerOpen(false)} />
                            </motion.aside>
                        </>
                    )}
                </AnimatePresence>

                {/* Main column */}
                <div
                    className={cn(
                        'flex min-h-screen flex-col transition-[padding] duration-300 ease-in-out',
                        collapsed ? 'lg:pl-[76px]' : 'lg:pl-64',
                    )}
                >
                    <Topbar onOpenSidebar={() => setDrawerOpen(true)} />

                    {header && (
                        <div className="border-b border-border bg-card">
                            <div className="mx-auto w-full max-w-[1400px] px-4 py-5 sm:px-6 lg:px-8">
                                {header}
                            </div>
                        </div>
                    )}

                    <main className="flex-1">
                        <motion.div
                            key={url}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                            className="mx-auto w-full max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                        >
                            {children}
                        </motion.div>
                    </main>
                </div>
            </div>
        </ToastProvider>
    );
}

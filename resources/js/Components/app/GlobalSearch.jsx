import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { AnimatePresence, motion } from 'framer-motion';
import { Boxes, Clock, CornerDownLeft, Plane, PackageMinus, PackagePlus, ScrollText, Search, Wrench } from 'lucide-react';
import { cn } from '@/lib/utils';

const GROUP_ICON = {
    parts: Boxes,
    aircraft: Plane,
    work_orders: Wrench,
    requisitions: ScrollText,
    receiving: PackagePlus,
    issuing: PackageMinus,
};

const RECENT_KEY = 'ncat.recentSearches';

function loadRecent() {
    try {
        return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]').slice(0, 5);
    } catch {
        return [];
    }
}

/** Highlight the matched substring inside a label. */
function Highlight({ text = '', query = '' }) {
    if (!query) return text;
    const i = text.toLowerCase().indexOf(query.toLowerCase());
    if (i === -1) return text;
    return (
        <>
            {text.slice(0, i)}
            <mark className="rounded bg-ncat-gold/40 px-0.5 text-inherit">{text.slice(i, i + query.length)}</mark>
            {text.slice(i + query.length)}
        </>
    );
}

export function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [groups, setGroups] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const [recent, setRecent] = useState([]);
    const inputRef = useRef(null);
    const boxRef = useRef(null);
    const debounce = useRef(null);
    const controller = useRef(null);

    // Flat, keyboard-navigable list across all groups.
    const flat = groups.flatMap((g) => g.items.map((it) => ({ ...it, group: g.key })));

    useEffect(() => setRecent(loadRecent()), []);

    // Cmd/Ctrl+K focuses the search from anywhere.
    useEffect(() => {
        const onKey = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
                setOpen(true);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Close on outside click.
    useEffect(() => {
        const onClick = (e) => {
            if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    const runSearch = useCallback((q) => {
        if (q.trim().length < 2) {
            setGroups([]);
            setLoading(false);
            return;
        }
        setLoading(true);
        controller.current?.abort();
        controller.current = new AbortController();
        axios
            .get(route('search'), { params: { q }, signal: controller.current.signal })
            .then((res) => {
                setGroups(res.data.groups ?? []);
                setActive(0);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const onChange = (e) => {
        const q = e.target.value;
        setQuery(q);
        setOpen(true);
        clearTimeout(debounce.current);
        debounce.current = setTimeout(() => runSearch(q), 180);
    };

    const go = (item) => {
        if (!item) return;
        const next = [query, ...recent.filter((r) => r !== query)].filter((s) => s.trim().length >= 2).slice(0, 5);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
        setRecent(next);
        setOpen(false);
        setQuery('');
        setGroups([]);
        router.visit(item.href);
    };

    const onKeyDown = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, flat.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            go(flat[active]);
        } else if (e.key === 'Escape') {
            setOpen(false);
            inputRef.current?.blur();
        }
    };

    let runningIndex = -1;
    const showResults = query.trim().length >= 2;

    return (
        <div ref={boxRef} className="relative hidden max-w-md flex-1 sm:block">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <input
                ref={inputRef}
                type="search"
                value={query}
                onChange={onChange}
                onFocus={() => setOpen(true)}
                onKeyDown={onKeyDown}
                placeholder="Search parts, aircraft, vouchers…"
                aria-label="Global search"
                className="h-10 w-full rounded-lg border border-input bg-muted/40 pl-9 pr-16 text-sm text-foreground placeholder:text-muted-foreground/70 focus:border-primary/40 focus:bg-background focus:outline-none focus:ring-2 focus:ring-ring/40"
            />
            <kbd className="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 items-center gap-0.5 rounded border border-border bg-background px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground md:flex">
                ⌘K
            </kbd>

            <AnimatePresence>
                {open && (query.length > 0 || recent.length > 0) && (
                    <motion.div
                        initial={{ opacity: 0, y: 6, scale: 0.99 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 6, scale: 0.99 }}
                        transition={{ duration: 0.16 }}
                        className="absolute left-0 right-0 top-12 z-50 max-h-[70vh] overflow-y-auto rounded-xl border border-border bg-popover p-1.5 shadow-glass-lg"
                    >
                        {!showResults && recent.length > 0 && (
                            <div>
                                <p className="px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Recent</p>
                                {recent.map((r) => (
                                    <button
                                        key={r}
                                        onClick={() => { setQuery(r); runSearch(r); inputRef.current?.focus(); }}
                                        className="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm text-foreground transition-colors hover:bg-accent"
                                    >
                                        <Clock className="size-3.5 text-muted-foreground" />
                                        {r}
                                    </button>
                                ))}
                            </div>
                        )}

                        {showResults && loading && !groups.length && (
                            <p className="px-2.5 py-6 text-center text-sm text-muted-foreground">Searching…</p>
                        )}

                        {showResults && !loading && !groups.length && (
                            <div className="px-2.5 py-8 text-center">
                                <Search className="mx-auto mb-2 size-6 text-muted-foreground/50" />
                                <p className="text-sm font-medium text-foreground">No matches for “{query}”</p>
                                <p className="text-xs text-muted-foreground">Try a part number, registration or voucher number.</p>
                            </div>
                        )}

                        {showResults && groups.map((g) => {
                            const Icon = GROUP_ICON[g.key] ?? Search;
                            return (
                                <div key={g.key} className="mb-1">
                                    <p className="flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                        <Icon className="size-3.5" /> {g.label}
                                    </p>
                                    {g.items.map((item) => {
                                        runningIndex += 1;
                                        const idx = runningIndex;
                                        return (
                                            <button
                                                key={`${g.key}-${item.id}`}
                                                onMouseEnter={() => setActive(idx)}
                                                onClick={() => go({ ...item, group: g.key })}
                                                className={cn(
                                                    'flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left transition-colors',
                                                    active === idx ? 'bg-accent' : 'hover:bg-accent/60',
                                                )}
                                            >
                                                <span className="min-w-0">
                                                    <span className="block truncate text-sm font-medium text-foreground">
                                                        <Highlight text={item.title} query={query} />
                                                    </span>
                                                    {item.subtitle && (
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            <Highlight text={item.subtitle} query={query} />
                                                        </span>
                                                    )}
                                                </span>
                                                {active === idx && <CornerDownLeft className="size-3.5 shrink-0 text-muted-foreground" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

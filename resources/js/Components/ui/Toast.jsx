import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { CheckCircle2, Info, TriangleAlert, X, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

const ToastContext = createContext(null);

const toneMap = {
    success: { icon: CheckCircle2, bar: 'bg-success', tint: 'text-success' },
    error: { icon: XCircle, bar: 'bg-destructive', tint: 'text-destructive' },
    warning: { icon: TriangleAlert, bar: 'bg-warning', tint: 'text-[hsl(30_65%_32%)]' },
    info: { icon: Info, bar: 'bg-info', tint: 'text-info' },
};

/**
 * Wrap the app once with <ToastProvider>. Anywhere below, call
 * `const { toast } = useToast();  toast({ title, description, variant })`.
 */
export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);
    const idRef = useRef(0);

    const dismiss = useCallback((id) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const toast = useCallback(
        ({ title, description, variant = 'info', duration = 4500 }) => {
            const id = ++idRef.current;
            setToasts((prev) => [...prev, { id, title, description, variant }]);
            if (duration) setTimeout(() => dismiss(id), duration);
            return id;
        },
        [dismiss],
    );

    const value = useMemo(() => ({ toast, dismiss }), [toast, dismiss]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            <div className="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
                <AnimatePresence initial={false}>
                    {toasts.map((t) => {
                        const tone = toneMap[t.variant] ?? toneMap.info;
                        const Icon = tone.icon;
                        return (
                            <motion.div
                                key={t.id}
                                layout
                                initial={{ opacity: 0, x: 40, scale: 0.96 }}
                                animate={{ opacity: 1, x: 0, scale: 1 }}
                                exit={{ opacity: 0, x: 40, scale: 0.96 }}
                                transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
                                className="pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-lg border border-border bg-card p-4 shadow-glass-lg"
                                role="status"
                            >
                                <span className={cn('absolute inset-y-0 left-0 w-1', tone.bar)} />
                                <Icon className={cn('mt-0.5 size-5 shrink-0', tone.tint)} />
                                <div className="flex-1">
                                    {t.title && (
                                        <p className="text-sm font-semibold text-ncat-navy">{t.title}</p>
                                    )}
                                    {t.description && (
                                        <p className="mt-0.5 text-sm text-muted-foreground">{t.description}</p>
                                    )}
                                </div>
                                <button
                                    onClick={() => dismiss(t.id)}
                                    className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                                    aria-label="Dismiss notification"
                                >
                                    <X className="size-4" />
                                </button>
                            </motion.div>
                        );
                    })}
                </AnimatePresence>
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be used within a <ToastProvider>');
    return ctx;
}

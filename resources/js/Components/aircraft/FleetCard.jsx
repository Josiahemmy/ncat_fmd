import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Plane, Wrench } from 'lucide-react';
import { AircraftStatusChip } from './AircraftStatusChip';
import { cn } from '@/lib/utils';

/**
 * A premium fleet-type card. The aircraft art floats over a navy gradient +
 * faint grid; hovering lifts the card and gently scales the airframe. Tapping
 * the card toggles an accordion of that type's registrations — a click-driven
 * reveal (not hover) so it behaves identically on touch and pointer devices.
 */
export function FleetCard({ type, index = 0 }) {
    const [open, setOpen] = useState(false);
    const registrations = type.registrations ?? [];

    return (
        <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55, delay: index * 0.07, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-glass transition-all duration-300',
                'hover:-translate-y-1 hover:border-ncat-cyan/30 hover:shadow-glass-lg',
                open && '-translate-y-1 border-ncat-cyan/30 shadow-glass-lg',
            )}
        >
            {/* Art stage */}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                className="relative block w-full text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <div className="relative h-44 overflow-hidden bg-ncat-hero">
                    {/* accent glow */}
                    <div className="absolute -right-10 top-4 size-40 rounded-full bg-ncat-cyan/20 blur-3xl transition-opacity duration-500 group-hover:opacity-100" />
                    <div className="absolute left-4 top-4 z-10 flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
                        <Plane className="size-3.5" />
                        {type.fleet_count} in fleet
                    </div>
                    {type.open_wo > 0 && (
                        <div className="absolute right-4 top-4 z-10 flex items-center gap-1 rounded-full bg-ncat-gold/90 px-2.5 py-1 text-xs font-bold text-ncat-navy shadow">
                            <Wrench className="size-3.5" />
                            {type.open_wo} open
                        </div>
                    )}
                    <motion.img
                        src={type.image}
                        alt={type.name}
                        loading="lazy"
                        className="absolute inset-0 m-auto h-32 w-auto max-w-[80%] object-contain drop-shadow-[0_18px_28px_rgba(0,0,0,0.45)] transition-transform duration-500 group-hover:scale-[1.08]"
                    />
                </div>

                <div className="flex items-center justify-between px-5 py-3.5">
                    <div>
                        <h3 className="font-display text-lg font-bold tracking-tight text-ncat-navy">{type.name}</h3>
                        <p className="text-xs text-muted-foreground">
                            {open ? 'Hide registrations' : 'View registrations'}
                        </p>
                    </div>
                    <span className={cn('flex size-8 items-center justify-center rounded-full bg-muted text-muted-foreground transition-transform duration-300', open && 'rotate-180 bg-primary/10 text-primary')}>
                        <ChevronDown className="size-4" />
                    </span>
                </div>
            </button>

            {/* Registrations accordion */}
            <AnimatePresence initial={false}>
                {open && (
                    <motion.div
                        key="regs"
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
                        className="overflow-hidden border-t border-border"
                    >
                        <ul className="divide-y divide-border">
                            {registrations.map((r, i) => (
                                <motion.li
                                    key={r.id}
                                    initial={{ opacity: 0, x: -8 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    transition={{ duration: 0.25, delay: i * 0.03 }}
                                >
                                    <Link
                                        href={r.href}
                                        className="flex items-center justify-between gap-2 px-5 py-2.5 transition-colors hover:bg-accent"
                                    >
                                        <span className="flex items-center gap-2.5">
                                            <span className="font-mono text-sm font-semibold text-ncat-navy">{r.registration}</span>
                                            <AircraftStatusChip status={r.status} />
                                        </span>
                                        {r.open_wo > 0 && (
                                            <span className="flex items-center gap-1 text-xs font-semibold text-[hsl(30_65%_32%)]">
                                                <Wrench className="size-3.5" />
                                                {r.open_wo}
                                            </span>
                                        )}
                                    </Link>
                                </motion.li>
                            ))}
                            {!registrations.length && (
                                <li className="px-5 py-3 text-sm text-muted-foreground">No registrations for this type.</li>
                            )}
                        </ul>
                    </motion.div>
                )}
            </AnimatePresence>
        </motion.div>
    );
}

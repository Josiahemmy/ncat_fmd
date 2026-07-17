import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ShieldCheck } from 'lucide-react';
import { Wordmark } from '@/Components/brand/Wordmark';
import { AircraftSilhouette } from '@/Components/brand/AircraftSilhouette';

/**
 * AuthLayout — premium split-screen shell for all guest/auth pages.
 *  · Left  : Midnight→Deep-Navy gradient "flight deck" with a drifting aircraft,
 *            faint radar grid, contrails and the NCAT lockup.
 *  · Right : the form, entering with soft motion.
 *
 * Differentiation anchor: the animated aircraft tracing a contrail across a
 * radar-grid navy panel — an unmistakable aviation-operations signature that
 * a generic SaaS login never has.
 */
export default function AuthLayout({ title, subtitle, children }) {
    return (
        <div className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            {/* ---------------- Brand panel ---------------- */}
            <div className="relative hidden overflow-hidden bg-ncat-hero lg:block">
                {/* radar grid */}
                <div className="absolute inset-0 bg-grid-faint opacity-[0.12] [background-size:44px_44px]" />
                {/* aurora glows */}
                <div className="absolute -left-20 top-1/4 size-96 rounded-full bg-ncat-blue/25 blur-[100px]" />
                <div className="absolute -right-10 bottom-10 size-80 rounded-full bg-ncat-cyan/20 blur-[90px]" />

                {/* drifting aircraft + contrail */}
                <motion.div
                    className="absolute left-[12%] top-[18%] text-ncat-sky/25"
                    initial={{ y: 0, rotate: -6 }}
                    animate={{ y: [0, -18, 0] }}
                    transition={{ duration: 9, repeat: Infinity, ease: 'easeInOut' }}
                >
                    <AircraftSilhouette className="size-40 xl:size-52" />
                </motion.div>
                <svg className="absolute inset-0 size-full" preserveAspectRatio="none" aria-hidden="true">
                    <motion.line
                        x1="18%" y1="34%" x2="86%" y2="70%"
                        stroke="url(#contrail)" strokeWidth="2" strokeDasharray="2 10" strokeLinecap="round"
                        initial={{ pathLength: 0, opacity: 0 }}
                        animate={{ pathLength: 1, opacity: 0.5 }}
                        transition={{ duration: 2.4, ease: 'easeOut', delay: 0.4 }}
                    />
                    <defs>
                        <linearGradient id="contrail" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stopColor="#00C2FF" stopOpacity="0" />
                            <stop offset="100%" stopColor="#13B8F0" stopOpacity="0.9" />
                        </linearGradient>
                    </defs>
                </svg>

                {/* content */}
                <div className="relative flex h-full flex-col justify-between p-10 xl:p-14">
                    <Link href="/" className="w-fit">
                        <Wordmark tone="light" />
                    </Link>

                    <motion.div
                        initial={{ opacity: 0, y: 16 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
                        className="max-w-md"
                    >
                        <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur">
                            <ShieldCheck className="size-3.5 text-ncat-cyan" />
                            Flight Maintenance Department · Zaria
                        </p>
                        <h2 className="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">
                            Inventory &amp; Stores,
                            <br />
                            <span className="bg-gradient-to-r from-ncat-sky to-ncat-cyan bg-clip-text text-transparent">
                                built for the fleet.
                            </span>
                        </h2>
                        <p className="mt-4 text-sm leading-relaxed text-white/65">
                            Work orders, requisitions, receiving and issuing across 26 aircraft —
                            one auditable ledger, one command console.
                        </p>
                    </motion.div>

                    <div className="flex items-center gap-6 text-xs text-white/45">
                        <span>26 Aircraft</span>
                        <span className="size-1 rounded-full bg-white/30" />
                        <span>6 Types</span>
                        <span className="size-1 rounded-full bg-white/30" />
                        <span>Ledger-grade audit trail</span>
                    </div>
                </div>
            </div>

            {/* ---------------- Form panel ---------------- */}
            <div className="relative flex flex-col items-center justify-center bg-background px-6 py-12 sm:px-10">
                {/* mobile brand (panel hidden on small screens) */}
                <div className="mb-8 w-full max-w-md lg:hidden">
                    <Wordmark tone="dark" />
                </div>

                <motion.div
                    initial={{ opacity: 0, y: 14 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                    className="w-full max-w-md"
                >
                    {(title || subtitle) && (
                        <div className="mb-8">
                            {title && (
                                <h1 className="font-display text-2xl font-bold tracking-tight text-ncat-navy">
                                    {title}
                                </h1>
                            )}
                            {subtitle && (
                                <p className="mt-1.5 text-sm text-muted-foreground">{subtitle}</p>
                            )}
                        </div>
                    )}

                    {children}
                </motion.div>

                <p className="mt-10 text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} Nigerian College of Aviation Technology · FMD
                </p>
            </div>
        </div>
    );
}

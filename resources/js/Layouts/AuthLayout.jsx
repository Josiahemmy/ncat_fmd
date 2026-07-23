import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ShieldCheck } from 'lucide-react';
import { Wordmark } from '@/Components/brand/Wordmark';

/**
 * AuthLayout — premium split-screen shell for all guest/auth pages.
 *  · Desktop : the supplied landscape flight-deck artwork under a Deep-Navy
 *              gradient + radar grid + NCAT lockup (readable overlay).
 *  · Mobile  : a compact portrait artwork banner, then the form.
 *  · Right   : the form, entering with soft motion.
 *
 * Artwork is optimized WebP in /public/aircraft (login-landscape ~89KB,
 * login-portrait ~146KB); heavy source SVGs stay out of the repo.
 */
export default function AuthLayout({ title, subtitle, children }) {
    return (
        <div className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            {/* ---------------- Brand panel (desktop) ---------------- */}
            <div className="relative hidden overflow-hidden bg-ncat-midnight lg:block">
                <img
                    src="/aircraft/login-landscape.webp"
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full object-cover"
                />
                {/* Deep-navy legibility wash + brand tint */}
                <div className="absolute inset-0 bg-gradient-to-tr from-ncat-midnight via-ncat-midnight/85 to-ncat-navy/60" />
                <div className="absolute inset-0 bg-grid-faint opacity-[0.10] [background-size:44px_44px]" />
                <div className="absolute -left-20 top-1/4 size-96 rounded-full bg-ncat-blue/20 blur-[100px]" />
                <div className="absolute -right-10 bottom-10 size-80 rounded-full bg-ncat-cyan/15 blur-[90px]" />

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
                        <p className="mt-4 text-sm leading-relaxed text-white/70">
                            Work orders, requisitions, receiving and issuing across 26 aircraft
                            and four stores. One auditable ledger, one command console.
                        </p>
                    </motion.div>

                    <div className="flex items-center gap-6 text-xs text-white/55">
                        <span>26 Aircraft</span>
                        <span className="size-1 rounded-full bg-white/40" />
                        <span>6 Types</span>
                        <span className="size-1 rounded-full bg-white/40" />
                        <span>4 Stores</span>
                    </div>
                </div>
            </div>

            {/* ---------------- Form panel ---------------- */}
            <div className="relative flex flex-col bg-background">
                {/* Mobile brand banner (portrait art) */}
                <div className="relative h-40 overflow-hidden lg:hidden">
                    <img
                        src="/aircraft/login-portrait.webp"
                        alt=""
                        aria-hidden="true"
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-ncat-midnight via-ncat-midnight/70 to-ncat-navy/40" />
                    <div className="absolute inset-0 flex items-end p-6">
                        <Wordmark tone="light" />
                    </div>
                </div>

                <div className="flex flex-1 flex-col items-center justify-center px-6 py-10 sm:px-10">
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
        </div>
    );
}

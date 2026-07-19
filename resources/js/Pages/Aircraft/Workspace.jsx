import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowLeft, ClipboardCheck, Cpu, Wrench } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { AircraftStatusChip } from '@/Components/aircraft/AircraftStatusChip';
import { ActionStrip } from '@/Components/aircraft/ActionStrip';
import { WorkspaceTabs } from '@/Components/aircraft/WorkspaceTabs';

function HeroStat({ icon: Icon, label, value }) {
    return (
        <div className="flex items-center gap-2.5 rounded-lg bg-white/10 px-3.5 py-2.5 backdrop-blur">
            <span className="flex size-9 items-center justify-center rounded-lg bg-white/15 text-white">
                <Icon className="size-[18px]" />
            </span>
            <div>
                <p className="font-display text-xl font-bold leading-none text-white">{value}</p>
                <p className="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-white/70">{label}</p>
            </div>
        </div>
    );
}

export default function Workspace({ aircraft, stats, links, workOrders, requisitions, sivs, partsOnAircraft }) {
    return (
        <AppLayout>
            <Head title={`${aircraft.registration} · Workspace`} />

            <Link
                href={route('aircraft-types')}
                className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft className="size-4" /> Fleet
            </Link>

            {/* Hero header */}
            <motion.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
                className="relative mb-5 overflow-hidden rounded-2xl bg-ncat-hero p-6 shadow-glass-lg sm:p-8"
            >
                <div className="absolute inset-0 bg-[size:26px_26px] bg-grid-faint opacity-[0.12]" />
                <div className="absolute -right-16 -top-10 size-64 rounded-full bg-ncat-cyan/20 blur-3xl" />
                {aircraft.image && (
                    <motion.img
                        src={aircraft.image}
                        alt={aircraft.type}
                        initial={{ opacity: 0, scale: 0.9, x: 30 }}
                        animate={{ opacity: 1, scale: 1, x: 0 }}
                        transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
                        className="pointer-events-none absolute -right-2 top-1/2 hidden h-40 w-auto -translate-y-1/2 object-contain opacity-90 drop-shadow-[0_20px_30px_rgba(0,0,0,0.5)] lg:block"
                    />
                )}

                <div className="relative">
                    <div className="flex items-center gap-3">
                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-ncat-cyan">{aircraft.type}</p>
                        <AircraftStatusChip status={aircraft.status} className="bg-white/15 !text-white" />
                    </div>
                    <h1 className="mt-1.5 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        {aircraft.registration}
                    </h1>

                    <div className="mt-5 flex flex-wrap gap-3">
                        <HeroStat icon={Wrench} label="Open WOs" value={stats.open_work_orders} />
                        <HeroStat icon={ClipboardCheck} label="Pending Reqs" value={stats.pending_requisitions} />
                        <HeroStat icon={Cpu} label="Parts Installed" value={stats.parts_installed} />
                    </div>
                </div>
            </motion.div>

            {/* Action strip */}
            <div className="mb-5">
                <ActionStrip links={links} />
            </div>

            {/* Document tabs + parts on aircraft */}
            <WorkspaceTabs
                workOrders={workOrders}
                requisitions={requisitions}
                sivs={sivs}
                partsOnAircraft={partsOnAircraft}
            />
        </AppLayout>
    );
}

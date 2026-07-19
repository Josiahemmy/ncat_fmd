import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Cpu, ExternalLink, PackageMinus, ScrollText, Wrench } from 'lucide-react';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { WorkOrderStatusBadge, WorkTypeBadge } from '@/Components/documents/WorkOrderBadges';
import { RequisitionStatusBadge } from '@/Components/documents/RequisitionBadges';
import { cn } from '@/lib/utils';

function TableShell({ head, children }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[520px] text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {head.map((h) => (
                            <th key={h} className="px-3 py-2.5 font-semibold">{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">{children}</tbody>
            </table>
        </div>
    );
}

const rowClass = 'group transition-colors hover:bg-accent';

export function WorkspaceTabs({ workOrders = [], requisitions = [], sivs = [], partsOnAircraft = [] }) {
    const tabs = [
        { key: 'parts', label: 'Parts on Aircraft', icon: Cpu, count: partsOnAircraft.length },
        { key: 'work_orders', label: 'Work Orders', icon: Wrench, count: workOrders.length },
        { key: 'requisitions', label: 'Requisitions', icon: ScrollText, count: requisitions.length },
        { key: 'sivs', label: 'Issues (SIV)', icon: PackageMinus, count: sivs.length },
    ];
    const [active, setActive] = useState('parts');

    return (
        <div className="glass-panel rounded-2xl">
            {/* Tab bar */}
            <div className="flex gap-1 overflow-x-auto border-b border-border px-2 pt-2">
                {tabs.map((t) => {
                    const Icon = t.icon;
                    const on = active === t.key;
                    return (
                        <button
                            key={t.key}
                            onClick={() => setActive(t.key)}
                            className={cn(
                                'relative flex items-center gap-2 whitespace-nowrap rounded-t-lg px-3.5 py-2.5 text-sm font-medium transition-colors',
                                on ? 'text-ncat-navy' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                            {t.label}
                            <span className={cn('rounded-full px-1.5 text-xs font-semibold', on ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground')}>
                                {t.count}
                            </span>
                            {on && (
                                <motion.span
                                    layoutId="ws-tab"
                                    className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-ncat-accent"
                                />
                            )}
                        </button>
                    );
                })}
            </div>

            {/* Panels */}
            <div className="p-3 sm:p-4">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={active}
                        initial={{ opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -6 }}
                        transition={{ duration: 0.2 }}
                    >
                        {active === 'parts' && (
                            partsOnAircraft.length ? (
                                <TableShell head={['Part', 'Serial', 'Position / Zone', 'Installed', '']}>
                                    {partsOnAircraft.map((p) => (
                                        <tr key={p.serial_id} className={rowClass}>
                                            <td className="px-3 py-3">
                                                <p className="font-semibold text-ncat-navy">{p.part_number}</p>
                                                <p className="text-xs text-muted-foreground">{p.description}</p>
                                            </td>
                                            <td className="px-3 py-3 font-mono text-xs">{p.serial_number}</td>
                                            <td className="px-3 py-3">{p.position || <span className="text-muted-foreground">—</span>}</td>
                                            <td className="px-3 py-3 text-muted-foreground">{p.installed_at ?? '—'}</td>
                                            <td className="px-3 py-3 text-right">
                                                <Link href={p.href} className="inline-flex items-center gap-1 text-xs font-semibold text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                                    Part <ExternalLink className="size-3" />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </TableShell>
                            ) : (
                                <EmptyState icon={Cpu} title="No serials installed" description="Serialised parts installed on this airframe (via SIV issue) appear here with their position and install date." className="border-0 bg-transparent py-8" />
                            )
                        )}

                        {active === 'work_orders' && (
                            workOrders.length ? (
                                <TableShell head={['Ref', 'Title', 'Type', 'Status', 'Date']}>
                                    {workOrders.map((w) => (
                                        <tr key={w.id} className={rowClass}>
                                            <td className="px-3 py-3"><Link href={w.href} className="font-mono text-xs font-semibold text-primary hover:underline">{w.wo_ref}</Link></td>
                                            <td className="px-3 py-3 max-w-[240px] truncate">{w.title}</td>
                                            <td className="px-3 py-3"><WorkTypeBadge type={w.work_type} /></td>
                                            <td className="px-3 py-3"><WorkOrderStatusBadge status={w.status} /></td>
                                            <td className="px-3 py-3 text-muted-foreground">{w.date}</td>
                                        </tr>
                                    ))}
                                </TableShell>
                            ) : (
                                <EmptyState icon={Wrench} title="No work orders" description="Snags and scheduled inspections raised for this aircraft appear here." className="border-0 bg-transparent py-8" />
                            )
                        )}

                        {active === 'requisitions' && (
                            requisitions.length ? (
                                <TableShell head={['No.', 'Description', 'Part No.', 'Status']}>
                                    {requisitions.map((r) => (
                                        <tr key={r.id} className={rowClass}>
                                            <td className="px-3 py-3"><Link href={r.href} className="font-mono text-xs font-semibold text-primary hover:underline">{r.requisition_no}</Link></td>
                                            <td className="px-3 py-3 max-w-[260px] truncate">{r.description}</td>
                                            <td className="px-3 py-3 font-mono text-xs">{r.part_no}</td>
                                            <td className="px-3 py-3"><RequisitionStatusBadge status={r.status} /></td>
                                        </tr>
                                    ))}
                                </TableShell>
                            ) : (
                                <EmptyState icon={ScrollText} title="No requisitions" description="Spare-part requisitions raised for this aircraft appear here." className="border-0 bg-transparent py-8" />
                            )
                        )}

                        {active === 'sivs' && (
                            sivs.length ? (
                                <TableShell head={['SIV No.', 'For', 'Status']}>
                                    {sivs.map((s) => (
                                        <tr key={s.id} className={rowClass}>
                                            <td className="px-3 py-3"><Link href={s.href} className="font-mono text-xs font-semibold text-primary hover:underline">{s.siv_number}</Link></td>
                                            <td className="px-3 py-3 max-w-[280px] truncate">{s.requisition_for}</td>
                                            <td className="px-3 py-3"><Badge variant={s.status === 'posted' ? 'success' : 'neutral'}>{s.status}</Badge></td>
                                        </tr>
                                    ))}
                                </TableShell>
                            ) : (
                                <EmptyState icon={PackageMinus} title="No issues" description="Store Issue Vouchers whose lines reference this aircraft appear here." className="border-0 bg-transparent py-8" />
                            )
                        )}
                    </motion.div>
                </AnimatePresence>
            </div>
        </div>
    );
}

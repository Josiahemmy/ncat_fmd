import { motion } from 'framer-motion';
import { ArrowUp, CheckCircle2, CircleDashed, Clock, Download, FileImage, FileText, User } from 'lucide-react';

/**
 * The shipment timeline. Newest at top, because the operational question is
 * "where is it now", and the answer should be at eye level without scrolling.
 *
 * The connector between two entries carries the gap in days. That gap is the
 * information a plain list throws away: a consignment that has not moved in
 * three weeks is the thing the department needs to see, and it is visible here
 * as a run of stacked waiting labels rather than something you compute by
 * subtracting two dates in your head.
 *
 * When a shipment is overdue and has not arrived, the event that should have
 * happened is drawn as a dashed ghost at the top of the rail. The absence is
 * the problem, so the absence gets a marker.
 */

const dayGap = (later, earlier) => {
    if (!later || !earlier) return null;
    const ms = new Date(later).setHours(0, 0, 0, 0) - new Date(earlier).setHours(0, 0, 0, 0);
    return Math.round(ms / 86400000);
};

/**
 * How long the consignment sat between two events. The rail reads newest
 * first, so the arrow points up towards the later of the two: "took 12 days
 * to get from the entry below to the entry above".
 */
const gapLabel = (days) => {
    if (days === null) return null;
    if (days <= 0) return 'Same day';
    return `${days} ${days === 1 ? 'day' : 'days'}`;
};

const longDate = (iso) =>
    iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    }) : null;

function Rail({ children }) {
    return <div className="relative pl-9">{children}</div>;
}

/** A node on the rail, plus the segment of rail that runs below it. */
function Node({ tone, icon: Icon, last }) {
    const ring = {
        current: 'border-primary bg-primary text-white shadow-[0_0_0_4px_hsl(var(--primary)/0.14)]',
        arrival: 'border-ncat-success bg-ncat-success text-white shadow-[0_0_0_4px_rgba(22,138,85,0.16)]',
        past: 'border-ncat-silver bg-white text-ncat-steel',
        ghost: 'border-dashed border-ncat-warning bg-warning/10 text-ncat-warning',
    }[tone];

    return (
        <>
            <span
                aria-hidden="true"
                className={`absolute left-0 top-1 flex size-[1.375rem] items-center justify-center rounded-full border-2 ${ring}`}
            >
                {Icon && <Icon className="size-3" />}
            </span>
            {!last && (
                <span aria-hidden="true" className="absolute bottom-0 left-[0.625rem] top-7 w-px bg-ncat-silver" />
            )}
        </>
    );
}

function GapMarker({ label }) {
    return (
        <div className="relative pb-4">
            <span aria-hidden="true" className="absolute -left-9 bottom-0 top-0 w-px bg-ncat-silver" />
            <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[0.6875rem] font-medium uppercase tracking-wide text-muted-foreground">
                <ArrowUp className="size-3" aria-hidden="true" />
                <span className="sr-only">Elapsed before the entry above: </span>
                {label}
            </span>
        </div>
    );
}

export default function ShipmentTimeline({ events, shipment }) {
    // Stored order is chronological; the rail reads newest first.
    const ordered = [...events].reverse();
    const showGhost = shipment.is_overdue;

    if (!ordered.length && !showGhost) {
        return (
            <p className="rounded-lg border border-dashed border-border bg-muted/30 px-6 py-10 text-center text-sm text-muted-foreground">
                No events yet. Record the first one to start the timeline.
            </p>
        );
    }

    return (
        <Rail>
            {showGhost && (
                <motion.div
                    initial={{ opacity: 0, y: -6 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                    className="relative pb-1"
                >
                    <Node tone="ghost" icon={CircleDashed} last={ordered.length === 0} />
                    <p className="text-sm font-semibold text-ncat-warning">Expected by now</p>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        Due {longDate(shipment.expected_arrival_date)}, {shipment.days_overdue}{' '}
                        {shipment.days_overdue === 1 ? 'day' : 'days'} ago. No arrival recorded.
                    </p>
                </motion.div>
            )}

            {ordered.map((event, i) => {
                const isLatest = i === 0 && !showGhost;
                const newer = ordered[i - 1];
                // Time this entry waited before the entry above it happened.
                const gap = newer ? gapLabel(dayGap(newer.event_date, event.event_date)) : null;
                const tone = event.is_arrival ? 'arrival' : isLatest ? 'current' : 'past';

                return (
                    <div key={event.id}>
                        {gap && <GapMarker label={gap} />}
                        <motion.div
                            initial={{ opacity: 0, y: 6 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.3, delay: Math.min(i, 6) * 0.04, ease: [0.22, 1, 0.36, 1] }}
                            className="relative pb-6"
                        >
                            <Node
                                tone={tone}
                                icon={event.is_arrival ? CheckCircle2 : undefined}
                                last={i === ordered.length - 1}
                            />

                            <p className="font-mono text-xs uppercase tracking-wide text-muted-foreground tabular-nums">
                                {longDate(event.event_date)}
                            </p>
                            <p className={`mt-0.5 font-display text-base font-semibold ${
                                event.is_arrival ? 'text-ncat-success' : 'text-ncat-navy'
                            }`}>
                                {event.status}
                            </p>

                            {event.note && (
                                <p className="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-ncat-graphite">
                                    {event.note}
                                </p>
                            )}

                            {event.attachments?.length > 0 && (
                                <ul className="mt-2 flex flex-wrap gap-2">
                                    {event.attachments.map((file) => (
                                        <li key={file.id}>
                                            <a
                                                href={file.url}
                                                className="group inline-flex items-center gap-2 rounded-md border border-input bg-background px-2.5 py-1.5 text-xs transition-colors hover:border-ncat-navy/40 hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/40"
                                            >
                                                {file.is_image
                                                    ? <FileImage className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                                    : <FileText className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />}
                                                <span className="max-w-[16rem] truncate font-medium text-ncat-navy">{file.name}</span>
                                                <span className="shrink-0 text-muted-foreground">{file.kind} · {file.size}</span>
                                                <Download className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" aria-hidden="true" />
                                                <span className="sr-only">Download {file.name}</span>
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}

                            <p className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5">
                                    <User className="size-3" /> {event.recorded_by}
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <Clock className="size-3" /> Recorded {event.recorded_at}
                                </span>
                            </p>
                        </motion.div>
                    </div>
                );
            })}
        </Rail>
    );
}

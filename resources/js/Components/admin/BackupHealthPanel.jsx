import { AlertTriangle, Archive, CheckCircle2, Clock } from 'lucide-react';
import { Card } from '@/Components/ui/Card';

/**
 * What the backup set actually looks like on disk.
 *
 * The reason this is on screen at all: the size cap deletes the oldest copies
 * without saying so anywhere an administrator would look, and mail transport is
 * still deferred, so there is no other channel that would carry the warning.
 */
const TONES = {
    ok: {
        icon: CheckCircle2,
        chip: 'bg-success/10 text-success',
        label: 'Healthy',
    },
    stale: {
        icon: Clock,
        chip: 'bg-destructive/10 text-destructive',
        label: 'Out of date',
    },
    truncated: {
        icon: AlertTriangle,
        chip: 'bg-warning/15 text-warning-foreground',
        label: 'History being trimmed',
    },
    never_run: {
        icon: AlertTriangle,
        chip: 'bg-destructive/10 text-destructive',
        label: 'Never run',
    },
};

function Figure({ label, value, hint }) {
    return (
        <div>
            <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 font-display text-lg font-bold text-ncat-navy">{value}</dd>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

export function BackupHealthPanel({ health }) {
    if (!health) return null;

    const tone = TONES[health.status] ?? TONES.ok;
    const Icon = tone.icon;
    const lastAt = health.last ? new Date(health.last.at) : null;

    return (
        <Card variant="glass" className="mt-4 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Archive className="size-[18px]" />
                    </span>
                    <div>
                        <h2 className="font-display text-base font-bold text-ncat-navy">Backups</h2>
                        <p className="text-xs text-muted-foreground">
                            Database and application files, written to the server’s own disk.
                        </p>
                    </div>
                </div>
                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${tone.chip}`}>
                    <Icon className="size-3.5" aria-hidden="true" />
                    {tone.label}
                </span>
            </div>

            <dl className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Figure
                    label="Last successful"
                    value={lastAt ? lastAt.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                    hint={
                        health.last
                            ? `${lastAt.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })} · ${health.last.age_hours}h ago`
                            : 'no backup on disk'
                    }
                />
                <Figure label="Its size" value={health.last ? health.last.size_human : '—'} />
                <Figure
                    label="Copies retained"
                    value={health.copies}
                    hint={health.oldest_age_days !== null ? `oldest ${health.oldest_age_days}d old` : undefined}
                />
                <Figure
                    label="Space used"
                    value={health.total_human}
                    hint={
                        health.cap_megabytes
                            ? `${health.used_percent}% of the ${health.cap_megabytes} MB cap`
                            : 'no cap set'
                    }
                />
            </dl>

            {health.cap_megabytes ? (
                <div className="mt-4">
                    <div
                        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={health.used_percent}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={`Backup space used: ${health.used_percent} percent of the ${health.cap_megabytes} megabyte cap`}
                    >
                        <div
                            className={`h-full rounded-full ${health.used_percent >= 90 ? 'bg-destructive' : 'bg-primary'}`}
                            style={{ width: `${Math.min(100, health.used_percent)}%` }}
                        />
                    </div>
                </div>
            ) : null}

            {health.messages.length > 0 && (
                <ul className="mt-4 space-y-2">
                    {health.messages.map((m) => (
                        <li
                            key={m}
                            className="flex gap-2 rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-foreground"
                        >
                            <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-warning-foreground" aria-hidden="true" />
                            <span>{m}</span>
                        </li>
                    ))}
                </ul>
            )}

            <p className="mt-4 text-xs text-muted-foreground">
                Retention keeps every backup for {health.retention_days} days. Restoring needs the{' '}
                <code className="rounded bg-muted px-1 py-0.5 font-mono text-[11px]">.env</code> file, which is
                deliberately inside the archive.
            </p>
        </Card>
    );
}

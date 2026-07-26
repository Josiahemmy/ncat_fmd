import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * Branded Recharts primitives for the dashboard. All aggregation is done
 * server-side (DashboardService) — these receive small, pre-summarised series.
 *
 * Palette: aviation blue → cyan for "in/received", amber for "out/issued",
 * navy for structure. Consistent across all three charts so the dashboard
 * reads as one instrument panel.
 */
const C = {
    blue: '#009DE0',
    cyan: '#00C2FF',
    navy: '#101A62',
    amber: '#F59E0B',
    grid: 'rgba(16,26,98,0.08)',
    // Steel at 45% lightness, matching --muted-foreground. The 49% value this
    // replaces measured 4.08:1 on the page background, under the 4.5:1 floor
    // that axis ticks and legend labels need as small text.
    axis: '#68707D',
};

const axisProps = {
    tick: { fill: C.axis, fontSize: 11 },
    tickLine: false,
    axisLine: false,
};

function ChartTooltip({ active, payload, label }) {
    if (!active || !payload?.length) return null;
    return (
        <div className="rounded-lg border border-border bg-popover/95 px-3 py-2 text-xs shadow-glass-lg backdrop-blur">
            <p className="mb-1 font-display font-semibold text-ncat-navy">{label}</p>
            {payload.map((p) => (
                <p key={p.name} className="flex items-center gap-2 text-muted-foreground">
                    <span className="size-2 rounded-full" style={{ background: p.color || p.fill }} />
                    <span className="capitalize">{p.name}</span>
                    <span className="ml-auto font-semibold text-foreground">
                        {Number(p.value).toLocaleString()}
                    </span>
                </p>
            ))}
        </div>
    );
}

const legendStyle = { fontSize: 12, color: C.axis, paddingTop: 8 };

/**
 * Recharts colours each legend label with its own series colour by default,
 * which put 12px text in #009DE0 (2.91:1) and #F59E0B (2.05:1) on the page
 * background. The series colour still identifies the series, but it does that
 * in the swatch beside the label rather than in the label itself.
 */
const legendLabel = (value) => <span style={{ color: C.axis }}>{value}</span>;

/** Stock in vs out, last 12 weeks — layered gradient area. */
export function MovementsTrendChart({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={data} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                <defs>
                    <linearGradient id="gradIn" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={C.blue} stopOpacity={0.35} />
                        <stop offset="100%" stopColor={C.blue} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="gradOut" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={C.amber} stopOpacity={0.3} />
                        <stop offset="100%" stopColor={C.amber} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} vertical={false} />
                <XAxis dataKey="week" {...axisProps} />
                <YAxis {...axisProps} width={44} />
                <Tooltip content={<ChartTooltip />} cursor={{ stroke: C.grid }} />
                <Legend wrapperStyle={legendStyle} formatter={legendLabel} />
                <Area type="monotone" dataKey="in" name="Received" stroke={C.blue} strokeWidth={2.5} fill="url(#gradIn)" />
                <Area type="monotone" dataKey="out" name="Issued" stroke={C.amber} strokeWidth={2.5} fill="url(#gradOut)" />
            </AreaChart>
        </ResponsiveContainer>
    );
}

/** Consumption by aircraft type — horizontal-feeling bars in brand blue. */
export function ConsumptionByTypeChart({ data = [] }) {
    const top = data.slice(0, 6);
    return (
        <ResponsiveContainer width="100%" height={260}>
            <BarChart data={top} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                <defs>
                    <linearGradient id="gradBar" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={C.cyan} />
                        <stop offset="100%" stopColor={C.blue} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} vertical={false} />
                <XAxis dataKey="type" {...axisProps} interval={0} angle={-12} textAnchor="end" height={48} />
                <YAxis {...axisProps} width={44} />
                <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(0,157,224,0.06)' }} />
                <Bar dataKey="issued" name="Issued" radius={[6, 6, 0, 0]} maxBarSize={54}>
                    {top.map((_, i) => (
                        <Cell key={i} fill="url(#gradBar)" />
                    ))}
                </Bar>
            </BarChart>
        </ResponsiveContainer>
    );
}

/** Receiving vs issuing, last 12 months — grouped bars. */
export function ReceivingVsIssuingChart({ data = [] }) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <BarChart data={data} margin={{ top: 8, right: 8, left: -18, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke={C.grid} vertical={false} />
                <XAxis dataKey="month" {...axisProps} />
                <YAxis {...axisProps} width={44} />
                <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(0,157,224,0.06)' }} />
                <Legend wrapperStyle={legendStyle} formatter={legendLabel} />
                <Bar dataKey="received" name="Received" fill={C.blue} radius={[4, 4, 0, 0]} maxBarSize={22} />
                <Bar dataKey="issued" name="Issued" fill={C.amber} radius={[4, 4, 0, 0]} maxBarSize={22} />
            </BarChart>
        </ResponsiveContainer>
    );
}

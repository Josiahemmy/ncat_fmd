import { Badge } from '@/Components/ui/Badge';

const MAP = {
    ok: { variant: 'success', label: 'OK' },
    below_reorder: { variant: 'warning', label: 'Below reorder' },
    below_min: { variant: 'error', label: 'Below min' },
    above_max: { variant: 'info', label: 'Above max' },
    expiring: { variant: 'warning', label: 'Expiring' },
};

export function StockStateBadge({ state }) {
    const m = MAP[state] ?? MAP.ok;
    return <Badge variant={m.variant}>{m.label}</Badge>;
}

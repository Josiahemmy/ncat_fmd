import { Badge } from '@/Components/ui/Badge';

const STATUS = {
    open: { variant: 'info', label: 'Open' },
    in_progress: { variant: 'warning', label: 'In progress' },
    closed: { variant: 'success', label: 'Closed' },
};

const TYPE = {
    snag: { variant: 'error', label: 'Snag' },
    scheduled_inspection: { variant: 'info', label: 'Inspection' },
    other: { variant: 'neutral', label: 'Other' },
};

export function WorkOrderStatusBadge({ status }) {
    const s = STATUS[status] ?? { variant: 'neutral', label: status };
    return <Badge variant={s.variant}>{s.label}</Badge>;
}

export function WorkTypeBadge({ type }) {
    const t = TYPE[type] ?? { variant: 'neutral', label: type };
    return <Badge variant={t.variant}>{t.label}</Badge>;
}

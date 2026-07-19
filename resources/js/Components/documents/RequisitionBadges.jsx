import { Badge } from '@/Components/ui/Badge';

const STATUS = {
    draft: { variant: 'neutral', label: 'Draft' },
    submitted: { variant: 'info', label: 'Submitted' },
    approved: { variant: 'success', label: 'Approved' },
    rejected: { variant: 'error', label: 'Rejected' },
    issued: { variant: 'warning', label: 'Issued' },
    closed: { variant: 'neutral', label: 'Closed' },
};

export function RequisitionStatusBadge({ status }) {
    const s = STATUS[status] ?? { variant: 'neutral', label: status };
    return <Badge variant={s.variant}>{s.label}</Badge>;
}

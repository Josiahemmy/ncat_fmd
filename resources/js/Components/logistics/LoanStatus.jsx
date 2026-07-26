import { Badge } from '@/Components/ui/Badge';

/**
 * A loan's state as one badge. Overdue wins over "on loan" because it is the
 * only one of the four that needs somebody to do something.
 *
 * This lives here rather than in a page so the two loan screens can share it
 * without importing each other, which would take one of them out of Vite's
 * page manifest and 500 the route.
 */
export function LoanStatus({ loan }) {
    if (loan.is_overdue) return <Badge variant="destructive">Overdue by {loan.days_overdue}d</Badge>;
    if (loan.status === 'returned') return <Badge variant="success">Returned</Badge>;
    if (loan.status === 'written_off') return <Badge variant="neutral">Written off</Badge>;

    return <Badge variant="info">On loan</Badge>;
}

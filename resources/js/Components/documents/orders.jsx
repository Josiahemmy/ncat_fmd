import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/Badge';

/**
 * Shared furniture for the two order submodules. Both live under one "Orders"
 * sidebar entry, so every order screen carries the same segmented switch and
 * reads its status from one vocabulary.
 */

const STATUS_VARIANTS = {
    draft: 'neutral',
    issued: 'info',
    partially_received: 'warning',
    at_vendor: 'warning',
    received: 'success',
    returned: 'success',
    closed: 'navy',
    cancelled: 'error',
};

export function statusLabel(status) {
    return String(status ?? '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}

export function OrderStatus({ status }) {
    return <Badge variant={STATUS_VARIANTS[status] ?? 'neutral'}>{statusLabel(status)}</Badge>;
}

export function OrdersTabs({ active }) {
    const tabs = [
        { key: 'purchase', label: 'Purchase Orders', href: route('purchase-orders.index') },
        { key: 'repair', label: 'Repair Orders', href: route('repair-orders.index') },
    ];

    return (
        <div className="mb-6 inline-flex rounded-lg border border-border bg-muted/50 p-1">
            {tabs.map((t) => (
                <Link
                    key={t.key}
                    href={t.href}
                    className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${
                        active === t.key
                            ? 'bg-background text-ncat-navy shadow-sm'
                            : 'text-muted-foreground hover:text-ncat-navy'
                    }`}
                >
                    {t.label}
                </Link>
            ))}
        </div>
    );
}

/** The three priority checkboxes printed on both forms. Single-select. */
export function PriorityPicker({ value, onChange, disabled = false }) {
    const options = [
        { value: 'aog', label: 'A.O.G' },
        { value: 'very_urgent', label: 'Very Urgent' },
        { value: 'for_inventory', label: 'For inventory' },
    ];

    return (
        <div className="flex flex-wrap gap-5">
            {options.map((o) => (
                <label key={o.value} className={`flex items-center gap-2.5 ${disabled ? '' : 'cursor-pointer'}`}>
                    <input
                        type="radio" name="priority" value={o.value} disabled={disabled}
                        checked={value === o.value}
                        onChange={() => onChange(value === o.value ? null : o.value)}
                        onClick={() => value === o.value && onChange(null)}
                        className="size-4 border-input text-primary focus:ring-2 focus:ring-ring/40"
                    />
                    <span className="text-sm font-semibold">{o.label}</span>
                </label>
            ))}
        </div>
    );
}

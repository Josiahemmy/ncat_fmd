import { cn } from '@/lib/utils';

/**
 * Grouped permission toggles with a per-group "all" switch.
 * `groups` is config/permissions.php's `groups`; `value` is an array of
 * selected permission names; `onChange(next)` receives the updated array.
 */
export function PermissionMatrix({ groups, value = [], onChange, disabled = false }) {
    const set = new Set(value);

    const toggle = (name) => {
        const next = new Set(set);
        next.has(name) ? next.delete(name) : next.add(name);
        onChange([...next]);
    };

    const groupNames = (group) => Object.keys(group.permissions);
    const allOn = (group) => groupNames(group).every((n) => set.has(n));

    const toggleGroup = (group) => {
        const names = groupNames(group);
        const next = new Set(set);
        if (allOn(group)) names.forEach((n) => next.delete(n));
        else names.forEach((n) => next.add(n));
        onChange([...next]);
    };

    return (
        <div className="space-y-5">
            {Object.entries(groups).map(([key, group]) => (
                <div key={key} className="rounded-lg border border-border">
                    <div className="flex items-center justify-between border-b border-border bg-muted/40 px-4 py-2.5">
                        <span className="text-sm font-semibold text-ncat-navy">{group.label}</span>
                        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium text-muted-foreground">
                            <input
                                type="checkbox"
                                disabled={disabled}
                                checked={allOn(group)}
                                onChange={() => toggleGroup(group)}
                                className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                            />
                            Select all
                        </label>
                    </div>
                    <div className="grid grid-cols-1 gap-x-6 gap-y-2 p-4 sm:grid-cols-2">
                        {Object.entries(group.permissions).map(([name, label]) => (
                            <label
                                key={name}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2.5 text-sm',
                                    disabled && 'cursor-not-allowed opacity-60',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    disabled={disabled}
                                    checked={set.has(name)}
                                    onChange={() => toggle(name)}
                                    className="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring/40"
                                />
                                <span className="text-foreground">{label}</span>
                                <code className="ml-auto text-[10px] text-muted-foreground">{name}</code>
                            </label>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

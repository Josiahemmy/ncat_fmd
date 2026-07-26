import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Renders the Inertia error bag for an action that was refused server-side.
 *
 * Document-level refusals (StockException) flash to a toast and never reach
 * here. What lands here is ValidationException, whose keys are field paths:
 * a post-time rule keyed `items.2.batch_no` is about line 3, so it is shown
 * as such rather than as a raw path the clerk has to decode.
 */

/** `items.2.batch_no` → `Line 3`. Anything else keeps its own wording. */
function lineLabel(key) {
    const match = /^items\.(\d+)\./.exec(key);

    return match ? `Line ${Number(match[1]) + 1}` : null;
}

export function ActionErrors({ only, className }) {
    const { errors = {} } = usePage().props;

    const entries = Object.entries(errors)
        .filter(([key]) => (only ? only.some((p) => key === p || key.startsWith(`${p}.`)) : true))
        .map(([key, message]) => [key, Array.isArray(message) ? message[0] : message]);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            aria-live="assertive"
            className={cn(
                'rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800',
                'dark:border-red-900 dark:bg-red-950/50 dark:text-red-200',
                className,
            )}
        >
            <p className="flex items-center gap-2 font-medium">
                <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
                {entries.length === 1 ? 'This could not be done' : `${entries.length} things need fixing`}
            </p>
            <ul className={cn('mt-1.5 space-y-1', entries.length > 1 && 'list-disc pl-5')}>
                {entries.map(([key, message]) => {
                    const line = lineLabel(key);

                    return (
                        <li key={key}>
                            {line && <span className="font-medium">{line}: </span>}
                            {message}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

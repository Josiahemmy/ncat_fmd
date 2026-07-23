import { usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

/**
 * DemoBanner — a slim, elegant brand-gold bar announcing that the data on
 * screen is for presentation only. Rendered ONLY when the server shares
 * `demo_mode === true` on the Inertia page props.
 *
 * Mounted at the very top of AppLayout (above the sticky Topbar) so it is
 * visible on every authenticated page. It is sticky at the top of the scroll
 * container with a higher z-index than the topbar so both remain usable.
 */
export function DemoBanner() {
    const { props } = usePage();

    if (!props?.demo_mode) return null;

    return (
        <div
            role="status"
            aria-label="Demo data, for presentation only"
            className="sticky top-0 z-50 flex h-7 w-full items-center justify-center gap-2 bg-ncat-gold px-4 text-ncat-navy shadow-[0_1px_2px_rgba(16,26,98,0.12)]"
        >
            <FlaskConical className="size-3.5 shrink-0" aria-hidden="true" />
            <span className="font-display text-[11px] font-semibold uppercase leading-none tracking-[0.18em]">
                Demo data, for presentation only
            </span>
        </div>
    );
}

export default DemoBanner;

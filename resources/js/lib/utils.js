import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * cn — merge Tailwind class lists intelligently (shadcn convention).
 * Later classes win on conflict; falsy values are dropped.
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

import { cn } from '@/lib/utils';

/**
 * Skeleton — loading placeholder with an NCAT shimmer sweep.
 * Pair with content that fades in once data arrives.
 */
function Skeleton({ className, ...props }) {
    return (
        <div
            className={cn('skeleton-shimmer rounded-md', className)}
            aria-hidden="true"
            {...props}
        />
    );
}

export { Skeleton };

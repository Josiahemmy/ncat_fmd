import { cn } from '@/lib/utils';

/**
 * Wordmark — the NCAT icon mark (309 KB PNG) paired with a typographic
 * lockup. We use the icon + type rather than the full-logo PNGs (1.2–2.7 MB)
 * on-screen for performance; the full logos are reserved for print/PDF.
 *
 * `tone`: "light" for dark surfaces (sidebar / login panel),
 *         "dark"  for light surfaces (topbar).
 */
export function Wordmark({ tone = 'dark', showText = true, className, iconClassName }) {
    const isLight = tone === 'light';
    return (
        <span className={cn('flex items-center gap-3', className)}>
            <img
                src="/brand/ncat-icon-256.png"
                alt="NCAT"
                width={40}
                height={40}
                className={cn('size-9 shrink-0 rounded-lg object-contain', iconClassName)}
            />
            {showText && (
                <span className="flex flex-col leading-none">
                    <span
                        className={cn(
                            'font-display text-sm font-bold tracking-tight',
                            isLight ? 'text-white' : 'text-ncat-navy',
                        )}
                    >
                        NCAT&nbsp;FMD
                    </span>
                    <span
                        className={cn(
                            'mt-1 text-[11px] font-medium tracking-wide',
                            isLight ? 'text-white/60' : 'text-muted-foreground',
                        )}
                    >
                        Inventory&nbsp;&amp;&nbsp;Stores
                    </span>
                </span>
            )}
        </span>
    );
}

export default Wordmark;

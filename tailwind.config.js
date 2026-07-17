import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import animate from 'tailwindcss-animate';

/**
 * NCAT FMD design system — Tailwind v3.
 *
 * Two coordinated colour layers:
 *  1. `ncat.*`  — the raw brand palette (exact hex from NCAT_Color_Palette.json),
 *                 for deliberate brand moments (sidebar, gradients, achievements).
 *  2. semantic  — shadcn-style tokens driven by CSS variables in app.css, so
 *                 components (Button, Card, …) theme consistently and support
 *                 alpha modifiers (`bg-primary/10`).
 */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './resources/js/**/*.js',
    ],
    theme: {
        container: {
            center: true,
            padding: '2rem',
            screens: { '2xl': '1400px' },
        },
        extend: {
            fontFamily: {
                // Body / UI
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Headings / display
                display: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // ---- Raw NCAT brand palette (exact hex) ----
                ncat: {
                    blue: '#009DE0',      // Aviation Blue — primary
                    navy: '#101A62',      // Deep Navy — sidebar / headings
                    sky: '#13B8F0',       // Sky Blue — highlights / gradients
                    cyan: '#00C2FF',      // Aviation Cyan — interactive accents
                    gold: '#FFD600',      // Golden Yellow — achievements (sparingly)
                    'sun-gold': '#FFB800',// Sun Gold — warm emphasis (sparingly)
                    midnight: '#050A23',  // Midnight Blue — darkest surface
                    ink: '#111318',       // body copy
                    graphite: '#353A45',  // secondary text
                    steel: '#737B89',     // muted text
                    silver: '#D9DEE7',    // dividers / borders
                    mist: '#F3F7FB',      // light background
                    success: '#168A55',
                    warning: '#F59E0B',
                    error: '#D92D20',
                    info: '#1677C8',
                },
                // ---- Semantic tokens (CSS-variable driven) ----
                border: 'hsl(var(--border) / <alpha-value>)',
                input: 'hsl(var(--input) / <alpha-value>)',
                ring: 'hsl(var(--ring) / <alpha-value>)',
                background: 'hsl(var(--background) / <alpha-value>)',
                foreground: 'hsl(var(--foreground) / <alpha-value>)',
                primary: {
                    DEFAULT: 'hsl(var(--primary) / <alpha-value>)',
                    foreground: 'hsl(var(--primary-foreground) / <alpha-value>)',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary) / <alpha-value>)',
                    foreground: 'hsl(var(--secondary-foreground) / <alpha-value>)',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive) / <alpha-value>)',
                    foreground: 'hsl(var(--destructive-foreground) / <alpha-value>)',
                },
                success: {
                    DEFAULT: 'hsl(var(--success) / <alpha-value>)',
                    foreground: 'hsl(var(--success-foreground) / <alpha-value>)',
                },
                warning: {
                    DEFAULT: 'hsl(var(--warning) / <alpha-value>)',
                    foreground: 'hsl(var(--warning-foreground) / <alpha-value>)',
                },
                info: {
                    DEFAULT: 'hsl(var(--info) / <alpha-value>)',
                    foreground: 'hsl(var(--info-foreground) / <alpha-value>)',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted) / <alpha-value>)',
                    foreground: 'hsl(var(--muted-foreground) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent) / <alpha-value>)',
                    foreground: 'hsl(var(--accent-foreground) / <alpha-value>)',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover) / <alpha-value>)',
                    foreground: 'hsl(var(--popover-foreground) / <alpha-value>)',
                },
                card: {
                    DEFAULT: 'hsl(var(--card) / <alpha-value>)',
                    foreground: 'hsl(var(--card-foreground) / <alpha-value>)',
                },
                // Dark-navy sidebar surface tokens
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar) / <alpha-value>)',
                    foreground: 'hsl(var(--sidebar-foreground) / <alpha-value>)',
                    muted: 'hsl(var(--sidebar-muted) / <alpha-value>)',
                    accent: 'hsl(var(--sidebar-accent) / <alpha-value>)',
                    border: 'hsl(var(--sidebar-border) / <alpha-value>)',
                },
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            backgroundImage: {
                'ncat-hero': 'linear-gradient(135deg, #050A23 0%, #101A62 55%, #0B2E6B 100%)',
                'ncat-accent': 'linear-gradient(120deg, #009DE0 0%, #13B8F0 50%, #00C2FF 100%)',
                'ncat-gold': 'linear-gradient(120deg, #FFB800 0%, #FFD600 100%)',
                'grid-faint':
                    'linear-gradient(hsl(var(--border) / 0.6) 1px, transparent 1px), linear-gradient(90deg, hsl(var(--border) / 0.6) 1px, transparent 1px)',
            },
            boxShadow: {
                glass: '0 8px 30px -12px rgba(16, 26, 98, 0.25), 0 2px 8px -4px rgba(16, 26, 98, 0.12)',
                'glass-lg': '0 24px 60px -20px rgba(5, 10, 35, 0.35), 0 8px 24px -12px rgba(16, 26, 98, 0.18)',
                glow: '0 0 0 1px rgba(0, 157, 224, 0.25), 0 8px 28px -8px rgba(0, 194, 255, 0.45)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                shimmer: {
                    '100%': { transform: 'translateX(100%)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
                'accordion-down': {
                    from: { height: '0' },
                    to: { height: 'var(--radix-accordion-content-height)' },
                },
                'accordion-up': {
                    from: { height: 'var(--radix-accordion-content-height)' },
                    to: { height: '0' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.5s cubic-bezier(0.22, 1, 0.36, 1) both',
                shimmer: 'shimmer 2s infinite',
                float: 'float 8s ease-in-out infinite',
                'accordion-down': 'accordion-down 0.2s ease-out',
                'accordion-up': 'accordion-up 0.2s ease-out',
            },
        },
    },
    plugins: [forms, animate],
};

/**
 * NCAT brand tokens (single source of truth for JS).
 * Mirrors NCAT_Brand_Assets/NCAT_Color_Palette.json and the CSS variables
 * in resources/css/app.css. Import this anywhere colour is needed in JS
 * (Recharts series, canvas, inline SVG tints) instead of hard-coding hex.
 */
export const palette = {
    primary: {
        blue: '#008BC7', // Aviation Blue, the 3:1 brand field (was #009DE0)
        navy: '#101A62', // Deep Navy — outlines, headings, premium contrast
        sky: '#13B8F0', // Sky Blue — highlights, gradients
    },
    accent: {
        cyan: '#00C2FF', // Aviation Cyan — interactive highlights
        gold: '#FFD600', // Golden Yellow — recognition / achievement (sparingly)
        sunGold: '#FFB800', // Sun Gold — warm emphasis (sparingly)
    },
    dark: {
        midnight: '#050A23', // darkest surface
    },
    neutrals: {
        ink: '#111318',
        graphite: '#353A45',
        steel: '#737B89',
        silver: '#D9DEE7',
        mist: '#F3F7FB',
        white: '#FFFFFF',
    },
    semantic: {
        success: '#168A55',
        warning: '#F59E0B',
        error: '#D92D20',
        info: '#1677C8',
    },
};

/** Ordered series colours for charts (Recharts, etc.). */
export const chartSeries = [
    palette.primary.blue,
    palette.primary.navy,
    palette.accent.cyan,
    palette.primary.sky,
    palette.semantic.info,
    palette.accent.sunGold,
];

/** Semantic status → colour, for badges/alerts rendered from JS. */
export const statusColor = {
    success: palette.semantic.success,
    warning: palette.semantic.warning,
    error: palette.semantic.error,
    info: palette.semantic.info,
};

/** Brand gradients (CSS strings). */
export const gradients = {
    hero: 'linear-gradient(135deg, #050A23 0%, #101A62 55%, #0B2E6B 100%)',
    accent: 'linear-gradient(120deg, #009DE0 0%, #13B8F0 50%, #00C2FF 100%)',
    gold: 'linear-gradient(120deg, #FFB800 0%, #FFD600 100%)',
};

export default palette;

/**
 * AircraftSilhouette — a lightweight, hand-drawn vector planform of a
 * single-engine aircraft (NCAT flies small training types: DA40, TB9, Baron…).
 *
 * Deliberately NOT the brand's supplied aircraft SVGs — those are ~2 MB each
 * (high-res PNGs embedded in an SVG shell) and unsuitable for an animated
 * decorative element. This is a few hundred bytes, tintable via `currentColor`,
 * and CSP-safe (no external refs).
 */
export function AircraftSilhouette({ className, ...props }) {
    return (
        <svg
            viewBox="0 0 240 240"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={className}
            {...props}
        >
            <g fill="currentColor">
                <path d="M120 26c5 0 8 6 9 14l3 60 22 10c4 2 6 5 6 9v6l-31-5 1 40 14 9c3 2 4 4 4 7v4l-21-4-2 14c0 4-2 7-5 7s-5-3-5-7l-2-14-21 4v-4c0-3 1-5 4-7l14-9 1-40-31 5v-6c0-4 2-7 6-9l22-10 3-60c1-8 4-14 9-14z" />
            </g>
        </svg>
    );
}

export default AircraftSilhouette;

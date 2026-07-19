# Builder Prompt #5 — Phase 4: Aircraft Experience, Analytics Dashboard & Global Search

**From:** CTO
**Spec:** v1.1 §7 (modules 1, 2, 9-Parts-on-Aircraft), §5 rule 9 (alerts). CAMP reference: `forms_reference/campsystems.com_inventory.png` (alert panel inspiration — adopt the *information design*, never the visual style; ours is NCAT-branded and far more premium).
**Baseline:** Phase 3 committed + CI green on MySQL (Project Lead verified). Standing rule: confirm latest deploy green + smoke-test before starting.
**Deliverable back:** diff report summary.

This is the showcase phase — the two screens everyone sees first (dashboard) and remembers (aircraft fleet). This is where the "$1M agency" bar is actually judged. Invoke the design skills for real, and browser-verify at desktop, tablet, and mobile widths.

## Skills to invoke
`task-observer` at start (apply OPEN observations — incl. the subagent-delegation and TDD-sequencing insights from Phase 3). `frontend-design` / `ui-ux-pro-max` / `impeccable` for everything visual. TDD for query services and search.

## Task

### 1. Aircraft Type module (the flagship experience)
- **Fleet grid**: the 6 aircraft as premium cards using the optimized WebP art — staggered entrance animation, hover lift/glow with the aircraft subtly scaling, type name, fleet count badge, open-WO count. Responsive grid (3×2 desktop → 2×3 → 1-col).
- **Type → registrations**: click/hover reveals that type's registrations (animated expansion or elegant flyout — builder's design call; must work on touch), each showing status chip (active/maintenance/retired) and open-WO indicator.
- **Aircraft Workspace** (click a registration): hero header (type art, registration, status, key stats: open WOs, pending requisitions, parts installed) + the **animated horizontal action strip**: Work Orders · Requisitions · Receiving · Issuing · Tally — each card animated (Framer Motion, spring/stagger), navigating to that module pre-filtered to this aircraft (use query-param filters the Phase 3 registers already support; add the filter param where missing).
- **Aircraft documents tabs** (the Phase 3 deferral): within the workspace — WOs, Requisitions, SIVs for this aircraft, plus **Parts on Aircraft**: serials currently installed (part, serial, position/zone, installed date from movement history) with link back to part detail. CAMP-inspired, NCAT-designed.

### 2. Analytics Dashboard (replaces the Phase 0 placeholder)
- **Alert panel** (CAMP information design): compact cards with live counts, each navigating to a pre-filtered list — Below Reorder · Below Min · Above Max · Expired · Expiring ≤90d · Quarantine awaiting certification (with aging) · Requisitions pending approval · Open Work Orders. Only show cards the user has permission to act on.
- **KPI tiles** (real data now): total distinct parts, total stock value (₦, where prices known), open WOs, fuel level (litres, mini-gauge).
- **Charts** (Recharts, branded): stock movements trend (in vs out, last 12 weeks), consumption by aircraft type (top parts issued per type), receiving-vs-issuing monthly comparison. Server-side aggregation — no shipping raw ledgers to the client.
- **Recent activity feed**: humanized latest actions (from activity log), permission-filtered.
- **Performance requirement:** one consolidated `DashboardService` powering the page via efficient aggregate queries; the Inertia shared-alerts computation from Phase 2 gets centralized/cached (e.g., 60s cache, busted on posting) so the per-request cost stops growing. Dashboard TTFB target <500ms with realistic data volumes (test with a seeded dev dataset of ~10k movements).

### 3. Global search (activate the Phase 0 topbar placeholder)
- One endpoint, debounced typeahead, grouped results: Parts (number/description/stock code), Aircraft (registration), Work Orders (ref/description), Requisitions/SRV/SIV (number), with keyboard navigation (↑↓ Enter, Cmd/Ctrl+K to focus). Permission-filtered per group. Recent-searches local memory. Premium dropdown treatment (grouped headers, highlighted matches, empty state).

### 4. Notifications bell polish
- Bell dropdown gets the same design pass: grouped by alert type, humanized lines, mark-as-seen, "view all" → relevant filtered lists. Keep the live-computed source of truth from Phase 2 (now served by the cached DashboardService path).

### 5. Tests & verification
- Feature: DashboardService aggregates correct against a seeded movement history; alert counts match engine scopes; search returns permission-filtered grouped results; parts-on-aircraft reflects serial installs/removals (drive one through the Phase 3 flow in the test); workspace filter params produce correctly filtered registers.
- Browser-verify (ephemeral sqlite + seeded demo data): fleet grid → type → registration → workspace strip → each pre-filtered module; dashboard alert card → filtered list; global search keyboard flow; all at 1440px, 768px, 390px widths. Zero console errors.
- Performance sanity: dashboard page with 10k seeded movements renders without N+1 (log query count in the report).
- Full suite green (sqlite + MySQL CI) before push.

## Definition of done
Live: the dashboard is a real command center (alerts, KPIs, charts, activity), the Aircraft Type module delivers the animated fleet → registration → workspace experience end-to-end with pre-filtered module navigation and Parts-on-Aircraft, and global search works from the topbar — all premium, responsive, permission-aware, with dashboard queries consolidated and cached.

## Report back
Diff report: files by group, dashboard query design (+ measured query counts/timings), design decisions on the fleet interaction pattern, screens at the three breakpoints, deviations, blockers.

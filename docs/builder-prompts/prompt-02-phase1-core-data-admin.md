# Builder Prompt #2 — Phase 1: Core Data, Stores, Administration Module & RBAC UI

**From:** CTO — REVISED 2026-07-18 (v2). Supersedes any earlier Prompt #2 draft.
**Spec:** `docs/superpowers/specs/2026-07-17-ncat-fmd-inventory-design.md` — now **v1.1**. Re-read it fully; §3–§7 changed materially (multi-store model, paper-form fidelity).
**Ground truth:** `forms_reference/` — view every image (Requisition Sheet, Store Issue Voucher, Store Receipt Voucher, Tally Card, CAMP screenshot) and the Work Order Ledger xlsx before designing anything. Where spec text and forms disagree, forms win — flag the discrepancy in your report.
**Baseline:** Phase 0 live; `main` = deploy. Keep it releasable; full test suite before every push.
**Deliverable back:** diff report summary (Phase 0 format).

## Skills to invoke
`task-observer` at start (apply OPEN observations in `skill-observations/log.md` — Windows composer recovery, ephemeral-sqlite browser verification, tar-over-SSH notes). TDD for backend logic. `frontend-design` / `ui-ux-pro-max` / `impeccable` for all UI.

## Task

### 1. Reference data — migrations, models, seeders (all idempotent)
- `aircraft_types` (6 types, per spec §4) and `aircraft` (26 registrations, status enum, soft deletes).
- `ata_chapters` — standard ATA 100 list (00, 05–92 as commonly used in GA maintenance).
- **`stores`** — seed the four: Quarantine (type `quarantine`), Bonded (`bonded`), Dope (`dope`), Fuel Dump (`fuel`). Fields per spec §4. These drive everything in Phase 2 — get the enum/types right now.
- **`document_counters`** — per-series running numbers (work_order_serial, requisition, srv, siv) with admin-editable next-value; seed provisional values (WO 1339, Req 1002, SIV 294, SRV 202) marked "to confirm with department".
- Document production seeding procedure in the README (how seeders run against live MySQL).

### 2. Aircraft & login artwork (Phase 0 refinement)
- `aircrafts_svgs/` now contains **lightweight** aircraft SVGs — generate optimized web derivatives into `public/aircraft/` (SVGO; target <150 KB each, ideally far less). These replace any Phase 0 placeholders.
- Replace the hand-drawn login silhouette: use `aircrafts_svgs/Login Page Image - Landscape.svg` for the desktop split-panel and `- Portrait.svg` for tall/mobile breakpoints. Optimize both (they're 0.8–1.7 MB raw — compress/simplify aggressively; if they contain embedded rasters, export optimized raster derivatives instead and keep masters out of the repo, per the Phase 0 pattern). Keep the premium overlay treatment (gradient, radar grid, NCAT lockup) working with the new art.

### 3. Permissions & roles (server side)
- `PermissionSeeder` with the full dot-notation catalogue from spec §6 — including Phase 2–5 permissions now: `requisitions.view/create/approve`, `receiving.post`, `issues.post`, `stock.adjust`, `stock.transfer`, `quarantine.certify`, `fuel.post`, `stores.manage`, `parts.manage`, `aircraft.manage`, `users.manage`, `roles.manage`, `reports.view`, `audit.view` (extend sensibly where a module needs view/create/edit granularity).
- Starter roles: Super Admin (Gate::before bypass exists), Stores Officer, Storekeeper, Engineer/Technician, Viewer. **Segregation of duties:** `quarantine.certify` goes to Stores Officer by default, NOT Storekeeper.
- Policies for User, Role, Aircraft, AircraftType, Store, AtaChapter, DocumentCounter; sidebar and page actions permission-checked (hide + enforce).

### 4. Administration module (UI)
Premium NCAT-skinned `/admin` section, visible only with the relevant permissions:
- **Users**: searchable paginated table (name, email, role badges, status, last login); create with auto-generated one-time temp password (displayed once) + role assignment + `password_change_required`; edit roles & per-user permission overrides (grouped toggles); activate/deactivate (deactivated cannot log in); admin password reset. Registration stays disabled.
- **Roles & Permissions**: role list with user counts; create/rename/delete (block delete-with-users without reassignment; Super Admin immutable); permission matrix grouped by module with group-toggles.
- **Aircraft & Types**: aircraft CRUD (registration, type, status, soft delete); types view with fleet counts and image preview (name/image editable, no delete).
- **Stores**: list/edit the four stores (name, description, active); type is fixed per store; adding new stores allowed (`general` type).
- **ATA Chapters**: CRUD (number + title).
- **Document Counters**: view series, edit next-number (permission-gated, audit-logged) — the department will set exact continuation values before Phase 3.
- **Activity Log**: filterable viewer (user, model, event, date range), humanized descriptions, before/after diffs.

### 5. First-login password enforcement
- Middleware forcing `password_change_required` users to a branded set-new-password screen before anything else; clears flag; audit-logged; applies to seeded Super Admin. Password rule object (min 10, mixed) on all set/reset paths.

### 6. Activity logging
- LogsActivity on User, Aircraft, AircraftType, Store, AtaChapter, DocumentCounter; role/permission changes logged with causer + properties; login/logout events logged.

### 7. Tests & verification
- Feature tests: seeders (6 types / 26 aircraft / 4 stores / ATA chapters / counters); permission enforcement (403s + sidebar filtering); user lifecycle incl. deactivation blocks login; forced password change; role permission edits take effect; counter edit audit-logged.
- Browser-verify (ephemeral sqlite): create user → login as them → forced password change → dashboard; login page renders the new artwork on desktop and mobile viewports with zero console errors.
- `npm run build` + full suite green before push.

## Definition of done
Live site: new login artwork; Super Admin can manage users, roles/permissions, fleet, stores, ATA chapters, and document counters; forced password change works; everything permission-gated and audit-logged.

## Report back
Diff report: files by group, schema summary, final permission catalogue, artwork optimization results (before/after sizes), production seeding steps, decisions/deviations (esp. any spec-vs-forms discrepancies found), blockers.

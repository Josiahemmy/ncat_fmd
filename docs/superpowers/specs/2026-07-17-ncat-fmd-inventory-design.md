# NCAT Flight Maintenance Department — Inventory System Design Spec

**Date:** 2026-07-17
**Project:** Inventory & Stores Management Dashboard for the Flight Maintenance Department (FMD), Nigerian College of Aviation Technology, Zaria
**Live target:** https://office.ncatfmd.com.ng
**Repo:** https://github.com/Josiahemmy/ncat_fmd
**Roles:** User = Project Lead · Claude (this session) = CTO (prompts, scope, review) · Builder = implementation agent

---

## 1. Purpose

A web-based inventory and stores management system for FMD covering Work Orders, Requisitions, Receiving (SRV), Issuing (SIV), and Tally Cards, organized around the department's fleet of 26 aircraft across 6 types, with a permission-managed multi-user dashboard and analytics.

## 2. Tech Stack (decided)

- **Backend:** Laravel 12 (PHP 8.2+), MySQL (existing cPanel DB `almadin1_ncat`)
- **Frontend:** Inertia.js + React 19 + Tailwind CSS + shadcn/ui + Framer Motion (+ Magic UI components where fitting)
- **Auth/RBAC:** Laravel Breeze (Inertia/React variant); spatie/laravel-permission with admin UI for dynamic roles/permissions; spatie/activitylog for audit trail
- **Charts:** Recharts (dashboard analytics)
- **PDF:** barryvdh/laravel-dompdf (or spatie/browsershot fallback) for branded vouchers
- **Hosting:** cPanel shared hosting, subdomain office.ncatfmd.com.ng; SSH + FTP available
- **CI/CD:** GitHub Actions on push to `main` → build assets (Node in CI only) → SSH deploy → `php artisan migrate --force` → cache config/routes/views → health check

## 3. Architecture Principles

1. **Single Laravel codebase** — no separate API; Inertia bridges Laravel and React.
2. **Ledger-first inventory** — `stock_movements` is an immutable append-only ledger. Stock on hand, tally cards, and analytics are all derived from it. No stored quantity may be edited directly; corrections are posted as adjustment movements.
3. **Server-side authorization** — every action gated by Laravel policies mapped to spatie permissions. UI hides what policies enforce.
4. **Auditability** — all creates/updates/approvals/deletions logged (user, timestamp, before/after) via activitylog. Government-institution grade traceability.
5. **Aircraft-centric UX layered over a type-centric stock model** — stock belongs to an aircraft *type* (nullable for cross-type consumables); every transaction is tagged to a specific aircraft *registration* where applicable.

## 4. Data Model

### Reference data
- `aircraft_types` — name, slug, svg asset path. Seeded: BARON-58, DA-40NG, DA-42NG, TB-9, TB-20, TBM-850.
- `aircraft` — registration (unique), aircraft_type_id, status. Seeded with the 26 registrations:
  - DA40NG: 5N-CZB, 5N-BZK, 5N-BZG, 5N-BZJ, 5N-BZH, 5N-BZF, 5N-BZI
  - DA42NG: 5N-BZE, 5N-CZA
  - TB-9: 5N-CAK, 5N-CBA, 5N-CBD, 5N-CAM, 5N-CBK, 5N-CAQ, 5N-CAL, 5N-CAJ
  - TB-20: 5N-CBT, 5N-CBU, 5N-CBQ, 5N-CBS, 5N-CBR
  - TBM-850: 5N-BZA
  - BARON-58: 5N-CAZ, 5N-CAT, 5N-CAG
- `ata_chapters` — chapter number + title (standard ATA 100 list, seeded).

### Inventory
- `parts` — part_number, description, ata_chapter_id, aircraft_type_id (nullable = cross-type consumable), unit_of_measure, min_stock_level, is_serialized (bool), has_shelf_life (bool), location/bin (optional), notes.
- `part_batches` — part_id, batch_number, expiry_date, qty_received. For shelf-life items.
- `part_serials` — part_id, serial_number, status enum (in_store, installed, scrapped), current_aircraft_id (nullable).
- `stock_movements` — part_id, part_batch_id (nullable), part_serial_id (nullable), direction (in/out), quantity, balance_after, movement_type (receiving, issue, adjustment, return), reference document (polymorphic: receiving/issue/adjustment), aircraft_id (nullable), user_id, timestamp. **Append-only.**

### Documents
- `work_orders` — wo_number (auto, prefix WO-YYYY-NNNN), aircraft_id, title/description, status (open, in_progress, closed), opened_by, opened_at, closed_at, remarks.
- `requisitions` — req_number (auto), aircraft_id, work_order_id (nullable), requested_by, status (draft, submitted, approved, rejected, partially_issued, issued, closed), approval fields (approved_by, approved_at, rejection_reason). Line items: `requisition_items` (part_id, qty_requested, qty_issued, remarks).
- `receivings` — srv_number (auto), supplier/source, received_by, received_at, remarks, optional document scan upload. Line items: `receiving_items` (part_id, qty, unit_cost optional, batch fields, serial numbers). Posting a receiving creates IN movements.
- `issues` — siv_number (auto), requisition_id, aircraft_id, issued_by, issued_to, issued_at. Line items: `issue_items` (requisition_item_id, part_id, batch/serial selection, qty). Posting creates OUT movements; supports partial issue (requisition status becomes partially_issued until fulfilled or closed).

### System
- `users` (admin-created only; no self-registration), spatie `roles`/`permissions` tables, `activity_log`, `notifications` (low stock, expiring batches, pending approvals).

## 5. Workflow Rules

1. Work Order opened for an aircraft → Requisitions may link to it (link optional; standalone requisitions allowed).
2. Requisition: draft → submitted → **approval required** (approve/reject with remarks) → issuing against approved requisitions only. Partial issues allowed.
3. Receiving records incoming stock (purchases/supplies); capture batch + expiry for shelf-life parts and serials for serialized parts at receipt time.
4. Tally card = per-part chronological ledger view (date, document ref, in, out, balance) derived from `stock_movements`. Filterable by date range; printable.
5. Stock adjustments (stock-take corrections) require a dedicated permission and a reason; posted as adjustment movements.
6. Alerts: dashboard + notification bell for (a) stock at/below min level, (b) batches expiring within a configurable window (default 90 days), (c) requisitions pending approval (for approvers).

## 6. RBAC

Dynamic — the department defines its own hierarchy later. Ship with:
- **Permissions** (granular): view/create/edit each document type, approve-requisitions, post-receiving, post-issue, adjust-stock, manage-parts, manage-aircraft, manage-users, manage-roles, view-reports, view-audit-log.
- **Seeded starter roles:** Super Admin (all), Stores Officer, Storekeeper, Engineer, Viewer — all editable/deletable from the Administration UI.
- Admin UI: create roles, assign permissions via grouped toggles, assign roles to users, per-user permission overrides.

## 7. UX & Design System

**Brand:** From NCAT_Brand_Assets — Aviation Blue #009DE0 (primary), Deep Navy #101A62 (sidebar/headings), Sky Blue #13B8F0 & Aviation Cyan #00C2FF (accents/gradients), Golden Yellow #FFD600 / Sun Gold #FFB800 (highlights, achievements — used sparingly), Midnight Blue #050A23 (dark surfaces), neutrals Ink/Graphite/Steel/Silver/Mist, semantic Success #168A55, Warning #F59E0B, Error #D92D20, Info #1677C8. NCAT logo + favicons integrated.

**Feel:** Premium agency-grade — glassmorphism cards, layered depth, Framer Motion page transitions and micro-interactions, skeleton loaders, empty states with illustrations, dark-navy sidebar. Fully responsive (desktop-first, tablet/mobile capable). Builder invokes frontend-design / ui-ux-pro-max / impeccable skills for all UI work.

**Layout:** Collapsible sidebar + topbar (global search across parts/vouchers/WOs, notification bell, user menu).

**Sidebar modules (order):**
1. **Dashboard** — KPI cards (total parts, stock value if costs captured, open WOs, pending approvals), low-stock & expiry alert lists, movement trend chart, per-aircraft-type consumption chart, recent activity feed.
2. **Aircraft Type** — 6 SVG aircraft cards in responsive grid; hover/click reveals that type's registrations; clicking a registration opens the **Aircraft Workspace** with an animated horizontal action strip: Work Orders · Requisitions · Receiving · Issuing · Tally — each pre-filtered to that aircraft.
3. **Work Orders**
4. **Requisitions** (incl. approval queue)
5. **Receiving (SRV)**
6. **Issuing (SIV)**
7. **Tally Cards**
8. **Parts Catalogue** (ATA chapter filter, batch/serial drill-down)
9. **Reports** — stock summary, movement register, expiry report, per-aircraft consumption; PDF/Excel export
10. **Administration** — users, roles & permissions, aircraft & types, ATA chapters, activity log, settings

All lists: server-side search, filter, sort, pagination. All vouchers: printable branded PDFs (layouts to be matched to the department's paper forms — **pending: Project Lead to supply scans in `forms_reference/`**; until then, standard aviation stores layouts are used).

## 8. Security

- No self-registration; admins create accounts. Password policy + rate limiting on login.
- Policies enforce every permission server-side. CSRF, validated FormRequests everywhere, mass-assignment guarded.
- `.env` only on server; `docs.txt` (contains cPanel/DB credentials) **must be gitignored**; DB password to be rotated by Project Lead after setup.
- HTTPS enforced on the subdomain.

## 9. Build Phases

| Phase | Scope | Definition of done |
|---|---|---|
| 0 | Scaffold: Laravel+Inertia+React, brand design system/tokens, auth, layout shell, CI/CD pipeline | Login + empty branded dashboard live on office.ncatfmd.com.ng via GitHub Actions deploy |
| 1 | Core data: migrations, models, seeders (types, 26 aircraft, ATA chapters), Administration module, roles/permissions UI | Admin can manage users/roles/permissions/aircraft on live site |
| 2 | Parts & stock engine: catalogue, batches/serials, movement ledger, tally views, adjustments | Parts CRUD + tally card rendering from seeded test movements |
| 3 | Documents: Work Orders, Requisitions + approval flow, Receiving, Issuing with ledger posting | Full chain WO→Req→Approve→Issue and Receive→stock works end-to-end |
| 4 | Aircraft experience + analytics dashboard | Animated Aircraft Type module, workspace strip, live dashboard KPIs/charts |
| 5 | Reports, branded PDF vouchers, exports, notifications, responsive/a11y/performance polish | Printable vouchers matching dept forms; Lighthouse ≥90; UAT-ready |

Each phase is delivered through CTO-drafted builder prompts; builder returns a diff report per prompt; CTO reviews before next phase.

## 10. Out of Scope (for now)

- Procurement/purchase ordering & supplier management (beyond a supplier name on receiving)
- Aircraft flight-hours/maintenance-scheduling (this is stores/inventory, not CAMO software)
- Multi-store/multi-location warehousing
- Email/SMS notifications (in-app only initially)
- Mobile native apps

## 11. Open Items

1. Paper form scans → `forms_reference/` (Project Lead)
2. Confirm PHP version on server is ≥8.2 (Project Lead checks cPanel)
3. Rotate DB password after go-live of Phase 0 (Project Lead)
4. Department to later define real role names/permissions via the admin UI

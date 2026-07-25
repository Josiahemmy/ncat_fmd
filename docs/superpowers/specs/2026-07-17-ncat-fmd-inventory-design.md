# NCAT Flight Maintenance Department — Inventory System Design Spec

**Version:** 1.2 (2026-07-25) — scope extension after the management demo. New §12 covers the approval workflow engine, Vendors, Orders (PO/RO), Shipping, Loaners, and store-page actions. §10 exclusions for vendors/POs/loaners/shipping are lifted.
**Prior:** 1.1 (2026-07-18) — revised after paper-forms review, HOD input (multi-store + CAMP reference), and Phase 0 delivery. v1.0 sections amended: §3, §4, §5, §6, §7, §9, §10, §11.
**Project:** Inventory & Stores Management Dashboard for the Flight Maintenance Department (FMD), Nigerian College of Aviation Technology, Zaria
**Live target:** https://office.ncatfmd.com.ng
**Repo:** https://github.com/Josiahemmy/ncat_fmd
**Roles:** User = Project Lead · Claude = CTO (prompts, scope, review) · Builder = implementation agent
**Ground truth:** the department's real forms in `forms_reference/` (Requisition Sheet, SIV, SRV, Tally Card AD38, Work Order Ledger xlsx, CAMP dashboard screenshot). Where this spec and the forms disagree, the forms win.

---

## 1. Purpose

A web-based inventory and stores management system for FMD covering Work Orders, Requisitions (aircraft-spares, one unit per voucher), Receiving (SRV), Issuing (SIV), Tally Cards, and the department's four physical stores (Quarantine, Bonded, Dope, Fuel Dump) — organized around the fleet of 26 aircraft across 6 types, with dynamic RBAC and analytics.

## 2. Tech Stack (decided, live since Phase 0)

- **Backend:** Laravel 12 (PHP 8.2+), MySQL (cPanel DB `almadin1_ncat`)
- **Frontend:** Inertia.js + React + Tailwind (v3.4) + shadcn-style component kit + Framer Motion; Recharts for charts
- **Auth/RBAC:** Breeze (Inertia/React); spatie/laravel-permission (+ admin UI); spatie/activitylog
- **PDF:** barryvdh/laravel-dompdf for branded vouchers
- **Hosting/CI:** cPanel (SSH), GitHub Actions → build → test-gate → tar-over-SSH deploy → migrate → cache → health check

## 3. Architecture Principles

1. **Single Laravel codebase** — Inertia bridges Laravel and React.
2. **Ledger-first inventory** — `stock_movements` is an immutable append-only ledger; stock on hand, tally cards, and analytics are derived. Corrections are adjustment movements, never edits.
3. **Every movement happens in a store.** Stock balances exist per part **per store**. Transfers between stores are paired movements (out of A, into B) posted atomically.
4. **Server-side authorization** via policies mapped to spatie permissions; UI hides what policies enforce.
5. **Auditability** — spatie/activitylog on all mutations; login/logout logged.
6. **Aircraft-centric UX over a type-centric stock model** — stock belongs to an aircraft *type* (nullable = cross-type consumable); transactions tag a specific *registration* where applicable.
7. **Paper-form fidelity** — digital documents mirror the department's forms field-for-field; printable PDFs reproduce the paper layouts (with NCAT branding).

## 4. Data Model

### Stores (NEW in v1.1)
- `stores` — name, slug, type enum (`quarantine`, `bonded`, `dope`, `fuel`, `general`), description, is_active. Seeded: **Quarantine Store** (transit — newly arrived parts awaiting certification), **Bonded Store** (main serviceable store), **Dope Store** (flammables), **Fuel Dump** (aviation fuel). Extensible via admin UI.
- Store routing rules: receiving lands in **Quarantine** by default; certification releases stock to **Bonded**, or **Dope** if the part is flammable; fuel is received directly into **Fuel Dump**.

### Reference data
- `aircraft_types` — name, slug, image path, sort_order. Seeded: BARON-58, DA-40NG, DA-42NG, TB-9, TB-20, TBM-850 (light SVGs now available in `aircrafts_svgs/`).
- `aircraft` — registration (unique), aircraft_type_id, status (active/maintenance/retired), soft deletes. Seeded with the 26 registrations (list per v1.0 §4 — unchanged).
- `ata_chapters` — chapter_number, title (ATA 100 standard, seeded).

### Inventory
- `parts` — part_number, description, ata_chapter_id, aircraft_type_id (nullable), stock_code, ledger_folio, unit_of_issue, unit_price (₦, nullable), bin_location, **min_level, max_level, reorder_level** (per Tally Card AD38), is_serialized, has_shelf_life, **is_flammable** (routes certification to Dope), is_fuel (bulk liquid, litres), notes.
- `part_batches` — part_id, batch_number, batch_year, expiry_date, qty_received.
- `part_serials` — part_id, serial_number, status (in_store, installed, removed_unserviceable, at_repair, scrapped), current_store_id (nullable), current_aircraft_id (nullable), position/zone when installed. Powers a CAMP-style **Parts on Aircraft** view.
- `stock_movements` — part_id, **store_id**, batch/serial refs (nullable), direction, quantity, balance_after (per part per store), movement_type (`receiving`, `certification_transfer`, `transfer`, `issue`, `fuel_issue`, `adjustment`, `return`), polymorphic source document, aircraft_id (nullable), user_id, timestamp. Append-only.

### Documents (field-for-field from the paper forms)
- `work_orders` — mirrors the Work Order Ledger: wo_ref auto-generated as **`FMD/{TYPE}/{MM}/{YY}/{serial}`** (global running serial, seedable starting number to continue their sequence, currently ~1338), aircraft_id, work_type (`snag` | `scheduled_inspection` | `other`), title/description (the snag text or inspection name e.g. "100 HRS INSPECTION"), status (open, in_progress, closed), raised_by, qc_checked_by, records_updated_by, filed_by, dates, remarks.
- `requisitions` — mirrors the Aircraft Spare Parts Requisition Sheet: **one part/unit per voucher**. req_number (running serial, e.g. 1001+, with prefix boxes), date, required_for, aircraft_id, engine_serial_no, position, authorised_by, supply_source, part_id (+ full description, part_no), stock_code, serial_number, batch_no + year, bin/bal_line_no, work_order_id (nullable link), status (draft → submitted → approved/rejected → issued → closed), serviceable_unit_issued_by (storeman), unit_serviced_by + date. **Removal section** (technician): serial_no_removed, zone, unit_changed_by, reason_for_removal, signature/date, repair_facility_workshop, date_sent, repair_order_ref. Distribution note (Stores/Progress/Shop/Comp. Planning/Chief Inspector) on the printed copy.
- `receivings` (SRV) — srv_number, date, **destination store** ("receive the following items into the ___ store" — default Quarantine; Fuel Dump for fuel), lpo_or_petty_cash_ref, supplier, head_of_receiving_dept, storekeeper, posted_by. Items: qty (figures — words auto-generated on the PDF), supplier & material details, part_id, fol_no, rate, amount (₦/K), invoice_no, acct_code, batch/serial/expiry capture. Posting creates IN movements in the destination store.
- `certifications` (NEW) — quarantine release: receiving_item(s) or quarantined stock, certified_by (authorized member, permission-gated), decision (release_to_bonded, release_to_dope, reject/return), date, remarks. Posting creates paired transfer movements out of Quarantine.
- `issues` (SIV) — siv_number, requisition_for, ordered_by (signature/name/date), school_section, approved_by + date, entered_by + date, issued_by/date, received_by/date, remark. **Multi-line items**: part_id, description, qty_required (figures; words auto-generated), qty_issued, stores_folio, rate, amount, charging_code. Lines may reference approved requisitions (bundling several) or be standalone consumable issues. Posting creates OUT movements from the issuing store (Bonded/Dope).
- `fuel_transactions` — Fuel Dump operations: receipt (into store) and issue (to aircraft_id, litres, issued_by, received_by, purpose). Implemented on the same movement ledger (movement_type `fuel_issue`), with a dedicated simplified UI.

### System
- `users`, spatie roles/permissions, `activity_log`, `notifications`, `document_counters` (per-series running numbers: WO serial, requisition, SRV, SIV — admin-adjustable starting values to continue the paper sequences).

## 5. Workflow Rules

1. **Work Orders**: opened per ledger practice (snag or scheduled inspection) with the FMD/{TYPE}/{MM}/{YY}/{serial} reference; requisitions may link to a WO.
2. **Requisition** (one unit per voucher): draft → submitted → **approval** → storeman issues serviceable unit (posts OUT movement from Bonded/Dope via SIV or direct issue reference) → technician completes removal section (captures removed serial → `part_serials` status becomes removed_unserviceable / at_repair with repair facility fields) → closed.
3. **Receiving**: SRV posts stock into **Quarantine** (or Fuel Dump for fuel). Quarantined stock is not issuable.
4. **Certification**: an authorized member reviews quarantined receipts → release to Bonded (default) or Dope (flammable) → paired transfer movements; rejection returns to supplier (documented). Only certified stock is issuable.
5. **Issuing (SIV)**: against approved requisitions and/or standalone lines; issues only from Bonded/Dope; batch selection prefers earliest expiry (FEFO) for shelf-life parts.
6. **Fuel Dump**: fuel received and issued in litres to aircraft; same ledger, simplified UI; no certification step.
7. **Tally card** = per part **per store** (and consolidated) ledger view with B/F and C/F totals, voucher no per line, MAX/MIN/REORDER levels shown; printable in the AD38 layout.
8. **Adjustments** require permission + reason; posted as adjustment movements.
9. **Alerts** (CAMP-style): at/below min or reorder level, above max, expired/expiring shelf-life (window configurable, default 90 days), **items sitting in Quarantine awaiting certification**, requisitions pending approval.

## 6. RBAC

Dynamic (unchanged). Permission set now also includes: `stores.manage`, `quarantine.certify`, `stock.transfer`, `fuel.post`, plus the v1.0 set (`requisitions.*`, `receiving.post`, `issues.post`, `stock.adjust`, `parts.manage`, `aircraft.manage`, `users.manage`, `roles.manage`, `reports.view`, `audit.view`). Starter roles: Super Admin, Stores Officer, Storekeeper, Engineer/Technician, Viewer — certification permission deliberately NOT in Storekeeper's default set (segregation of duties: receiver ≠ certifier).

## 7. UX & Design System

Brand, layout shell, and premium treatment: as v1.0 §7 (live since Phase 0), plus:

- **Login page art**: replace the hand-drawn silhouette with the supplied `Login Page Image - Landscape.svg` / `- Portrait.svg` (optimized derivatives; landscape for desktop split-panel, portrait for tall/mobile breakpoints).
- **Aircraft images**: use the new lightweight SVGs in `aircrafts_svgs/` (optimized web derivatives in `public/aircraft/`).

**Sidebar modules (revised):**
1. **Dashboard** — CAMP-inspired alert panel (min/reorder breaches, expired & expiring shelf-life, quarantine awaiting certification, pending approvals), KPI tiles, movement trends, per-type consumption, recent activity.
2. **Aircraft Type** — unchanged (6 SVG cards → registrations → Aircraft Workspace strip: Work Orders · Requisitions · Receiving · Issuing · Tally).
3. **Stores** (NEW) — the four stores as rich cards (Quarantine, Bonded, Dope, Fuel Dump) → per-store stock list, quarantine certification queue, transfer action, fuel dump receive/issue UI.
4. **Work Orders** — ledger-style register matching their xlsx columns.
5. **Requisitions** — single-unit vouchers incl. removal section; approval queue.
6. **Receiving (SRV)**
7. **Issuing (SIV)**
8. **Tally Cards** — per part per store, AD38 layout, printable.
9. **Parts Catalogue** — ATA filter, per-store balances, batch/serial drill-down, **Parts on Aircraft** view.
10. **Reports** — stock summary (per store), movement register, expiry report, per-aircraft consumption, quarantine aging; PDF/Excel.
11. **Administration** — users, roles/permissions, aircraft & types, **stores**, ATA chapters, **document counters**, activity log.

All vouchers printable as branded PDFs matching the paper layouts (Requisition Sheet, SIV, SRV, Tally AD38).

## 8. Security

Unchanged from v1.0 (no self-registration, policies, audit, `.env`-only credentials, HTTPS) + first-login forced password change (Phase 1) + segregation of duties on certification (§6).

## 9. Build Phases (revised)

| Phase | Scope | Definition of done |
|---|---|---|
| 0 ✅ | Scaffold, design system, auth, layout shell, CI/CD | Live (delivered 2026-07-17) |
| 1 | Core data (types, 26 aircraft, ATA, **stores**), Administration (users/roles/permissions/aircraft/stores/counters), first-login password change, **login-page art swap + new aircraft SVG derivatives** | Admin fully operational on live site with new art |
| 2 | Parts & stock engine: catalogue (full AD38 fields), batches/serials, per-store movement ledger, tally views, transfers, adjustments, **quarantine certification flow**, **fuel dump** | Stock engine works across all four stores |
| 3 | Documents: Work Orders (FMD numbering), Requisitions (paper-exact + removal), Receiving→Quarantine, SIV issuing, document counters | Full paper workflow digital end-to-end |
| 4 | Aircraft experience + CAMP-style dashboard + Parts on Aircraft | Animated fleet UX + live analytics |
| 5 | Printable PDF vouchers (paper-exact), reports/exports, notifications, polish (a11y/perf/responsive) | UAT-ready |

## 10. Out of Scope (for now)

- Procurement/purchase orders & vendor management (SRV records the LPO *reference* only) — CAMP's Orders/Vendors/RFQ modules explicitly deferred
- CAMP features not adopted: cores, loaners, warranty, shipping, tool calibration
- Aircraft flight-hours/maintenance scheduling (CAMP Maintenance/Scheduling tabs)
- Email/SMS notifications (in-app only initially)
- Native mobile apps

*(Removed from v1.0 exclusions: multi-store warehousing — now core scope per HOD.)*

## 11. Open Items

1. ~~Paper form scans~~ ✅ received in `forms_reference/`
2. Confirm PHP ≥8.2 on server — believed satisfied (Phase 0 deployed); verify `php -v` once
3. **Set MySQL password + change seeded Super Admin password** (Project Lead — outstanding from Phase 0)
4. Department later defines real roles via admin UI
5. Starting values for document counters (WO serial ~1338, Req ~1001, SIV ~0293, SRV ~0201 per the samples) — confirm exact next numbers with the department before Phase 3 go-live
6. ~~Confirm with HOD which CAMP features beyond the adopted set they expect~~ Resolved by the management demo: vendors, orders, shipping, and loaners are now in scope (§12).

## 12. v1.2 Scope Extension (post-management-demo, 2026-07-25)

Delivery order: Phase 6 (approval engine + refinements) → Phase 7 (Vendors + Orders) → Phase 8 (Shipping + Loaners). Forms ground truth extended: `forms_reference/Purchase Order.png`, `forms_reference/Repair Order.png`.

### 12.1 Configurable approval workflow (Phase 6)
- `approval_workflows` per document type (requisitions first): ordered levels, each level bound to a permission or role. Admin UI to add/remove/reorder levels. Seeded default: one level bound to `requisitions.approve`, matching current behavior exactly.
- A requisition is issuable only after the final level approves. Each decision records who/when/remarks. Rejection at any level ends the flow with the reason.
- Notifications (database channel + bell): pending approvers notified when a requisition reaches their level; the requester is notified on every decision. The SIV picker only lists fully approved requisitions (unchanged rule, new engine underneath).
- Approve/Reject actions sit on the requisition detail page directly above the removal block, visible only to users who can act on the current level; approver ≠ requester still holds per level.

### 12.2 SIV form refinements (Phase 6)
- "Requisition for" is a live picker of fully approved, not-yet-issued requisitions.
- Selecting one auto-fills "Ordered by" with the requester's full name and the request date, shown greyed and read-only. Server ignores client tampering of these fields (they derive from the requisition, never from input).

### 12.3 Store-page actions (Phase 6)
- On each store page: per-part "view tally" plus "raise requisition" and "raise issue" actions pre-scoped to that store. Hidden/disabled on Quarantine (view remains).

### 12.4 Vendors (Phase 7)
- `vendors`: name, type (supplier / repair organization / both), address block (multi-line, as printed on PO/RO), country, email, phone, contact person, notes, is_active. Add-new form above a filterable list (filter: type, country, active). Permission group `vendors.*`. Vendor detail shows linked POs/ROs/shipments/loans.

### 12.5 Orders module (Phase 7): Purchase Orders + Repair Orders
Two submodules under one "Orders" sidebar entry. Both mirror their paper forms:
- **Purchase Order** — ref series `NCAT/FMD/PO/TS/{D}/{M}/{serial}` (new counter; serial continues from the department's current number, provisional until confirmed, sample shows 307). Vendor (from Vendors), aircraft type header, lines: description, part number (part picker with free-text fallback), qty to order, status per line (NEW/…), timeline (month/year). Priority checkboxes: A.O.G / Very Urgent / For Inventory. NCAT contacts block (defaults seeded from the sample, editable in settings). Prepared by: Head, Materials and Stores. Statuses: draft → issued → partially received → received → closed/cancelled. Paper-exact PDF.
- **Repair Order** — ref series `NCAT/FMD/RO/TS/{MM}/{serial}` (sample 298). Vendor must be a repair organization. Lines: description, part number, **serial no** (picker over at_repair/removed_unserviceable serials, free-text fallback), qty, action (OVERHAUL/REPAIR/TEST/…). Ties into the Phase 3 removal flow: creating an RO line from a removed serial updates that serial's state and back-links the requisition's repair-order field. Statuses: draft → issued → at vendor → returned → closed/cancelled. Paper-exact PDF.
- SRV gains an optional PO reference (next to the free-text LPO field); receiving against a PO updates its received quantities and status.

### 12.6 Shipping (Phase 8)
- Tracks inbound shipments. A shipment references a PO or RO (or standalone), vendor, description, expected date. Manual status timeline: an ordered, append-only event log (e.g. Shipped → Arrived at local port → Picked up by courier → Arrived at NCAT), each event with date + note, rendered as a premium vertical timeline. Admin-manageable set of suggested statuses; free-text allowed. Arrival at NCAT prompts "create SRV from this shipment" (pre-filled, lands in Quarantine as usual). Alert for shipments past expected date.

### 12.7 Loaners (Phase 8) — both directions
- **Outbound**: an external party (vendors list or standalone borrower record) borrows stock from NCAT. Issue posts loan-out movements (new movement_type `loan_out`, from Bonded/Dope only); duration recorded; overdue alert when past due and unreturned; return posts `loan_return` movements (with condition note) or is written off via adjustment.
- **Inbound**: NCAT borrows an item from another organization: tracked record (lender, item, serials, due date, overdue alert, returned flag). Inbound loaned stock is flagged and excluded from NCAT-owned stock value; issuing it is allowed but visibly marked as loaned property.
- Dashboard/bell alerts: overdue outbound and inbound loans.

### 12.8 Permissions (new groups)
`vendors.view/manage`, `orders.view/create/edit/close` (covers PO+RO; split later if the dept asks), `shipping.view/manage`, `loans.view/manage`, `approvals.manage` (admin: configure workflow levels). Starter-role defaults: Stores Officer gets orders/shipping/loans manage; Storekeeper gets view + shipping status updates; assignments editable in admin as always.

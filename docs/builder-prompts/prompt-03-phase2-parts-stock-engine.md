# Builder Prompt #3 — Phase 2: Parts Catalogue & the Stock Engine

**From:** CTO
**Spec:** `docs/superpowers/specs/2026-07-17-ncat-fmd-inventory-design.md` v1.1 — re-read §3 (principles 2–3), §4 (Inventory), §5 (rules 3–9), §7 (modules 3, 8, 9). Forms ground truth: `forms_reference/Tally.png` (AD38) governs the tally view and part fields.
**Baseline:** Phase 1 merged (7900c36). **Before writing any code: confirm the Phase 1 deploy went green and smoke-test the live site** (login page art, /up, admin loads). If the deploy failed, fix that first and report it.
**Deliverable back:** diff report summary (same format).

This phase is the heart of the system — the ledger everything else posts to. Correctness beats speed; TDD is mandatory here, not optional.

## Skills to invoke
`task-observer` at start (apply OPEN observations). `superpowers:test-driven-development` for the entire stock engine. `frontend-design` / `ui-ux-pro-max` / `impeccable` for UI.

## Task

### 1. Parts catalogue
- Migrations/models: `parts` with the full spec §4 field set (part_number, description, ata_chapter_id, aircraft_type_id nullable, stock_code, ledger_folio, unit_of_issue, unit_price nullable, bin_location, min_level/max_level/reorder_level, is_serialized, has_shelf_life, is_flammable, is_fuel, notes; soft deletes). `part_batches` and `part_serials` per spec (serial status enum incl. removed_unserviceable/at_repair; current_store_id/current_aircraft_id/position).
- UI: searchable/filterable catalogue (ATA chapter, aircraft type, store, stock state: OK/below-reorder/below-min/above-max/expiring), per-store balance columns, part detail page with tabs: Overview (levels, identifiers), Batches (expiry badges), Serials (status + location), Tally (see §5), Movements. CRUD gated by `parts.manage`.
- Seed a small realistic demo set ONLY in local/dev seeders (never production): a few parts per category incl. one serialized, one shelf-life, one flammable, one fuel.

### 2. Stock engine (the core)
Build a single `StockService` (or equivalent action classes) as the ONLY write-path to `stock_movements`. No controller may insert movements directly.
- Posting API: `receive`, `certify` (quarantine → bonded/dope, paired transfer movements), `transfer` (store→store), `issue` (from bonded/dope only), `fuelReceive`/`fuelIssue` (litres, aircraft tag), `adjust` (requires reason), `openingBalance`.
- Invariants (enforce in code + DB where possible, and test each):
  a. Movements are append-only — no update/delete route or model method exists; corrections are counter-movements.
  b. `balance_after` is per part **per store** and always ≥ 0 — an issue/transfer exceeding available store balance is rejected atomically.
  c. Serialized parts move as individual serials (qty 1 per serial); a serial can only be in one store/state at a time.
  d. Batch-tracked issues must specify batch(es); suggest FEFO (earliest expiry first) and warn on overriding it; block issuing expired batches without an explicit `stock.adjust`-gated override flag (logged).
  e. Quarantine stock is not issuable/transferable except via `certify` (or rejection/return).
  f. All postings wrapped in DB transactions with row-level locking (`lockForUpdate` on the balance read) — write a concurrency test proving two simultaneous issues can't oversell.
- Movement queries: efficient per-part/per-store balance lookup (latest movement or a maintained `stock_balances` summary table updated inside the same transaction — builder's choice, justify in report; balances must never be computable as wrong even mid-transaction).

### 3. Opening balances
The department has existing physical stock recorded on paper tally cards. Provide:
- An **Opening Balance entry UI** (per store): pick/create part → qty, unit price, batch/expiry or serials as applicable → posts `openingBalance` movements. Gated by `stock.adjust`.
- A **CSV import** (template downloadable from the UI: part_number, description, ata_chapter, store, qty, unit_price, batch_no, expiry, serials pipe-separated) with dry-run preview (row-level validation errors shown) before commit. This is how the department will digitize their tally cards.

### 4. Stores module (sidebar entry #3)
- Landing page: the four stores as rich cards (name, item count, total value where prices known, alert badges — quarantine shows awaiting-certification count).
- Per-store stock list (searchable, same filters as catalogue).
- **Quarantine: Certification queue** — pending received/quarantined stock; certifier (`quarantine.certify`) reviews line → Release to Bonded / Release to Dope (auto-suggested when `is_flammable`) / Reject-return (documented reason). Bulk certify supported. Show received date + aging.
- **Transfers** — permission `stock.transfer`, from/to store, part/batch/serial pickers limited to available stock, posts paired movements.
- **Adjustments** — `stock.adjust`, signed qty with mandatory reason.
- **Fuel Dump** — dedicated simplified screen: current fuel level(s) (large gauge/stat treatment), Receive Fuel (supplier, litres, price) and Issue Fuel (aircraft registration picker, litres, issued_by/received_by, purpose). Posts through the same engine.

### 5. Tally card views
- Per part per store (and a consolidated all-stores view): AD38-style on screen — header block (description, part no, location, unit of issue, ledger folio, batch, unit price, MAX/MIN/REORDER) + ledger table (date, voucher/source ref, particulars, received, issued, balance, user, remarks), **B/F row** when date-filtered and **C/F totals**. Date-range filter, print-friendly CSS now (PDF export lands Phase 5).
- Tally Cards sidebar module: part search → card. Also reachable from part detail tab and store stock lists.

### 6. Alert computations (engine only — dashboard visualization is Phase 4)
- Queryable scopes/services: below-reorder, below-min, above-max, expiring within N days (default 90), expired, quarantine-aging (> N days). Wire a lightweight notifications write (database channel) when thresholds are crossed at posting time. Bell shows count + list (simple for now).

### 7. Tests & verification
- Unit/feature: every invariant in §2 (incl. the concurrency oversell test), FEFO suggestion & expired-batch block, certification SoD (Storekeeper 403 on certify), opening-balance CSV dry-run + commit, tally B/F//C/F math against a seeded movement history, fuel issue tagged to aircraft, negative-balance rejection, quarantine isolation (issue attempt from quarantine 403/422).
- Browser-verify (ephemeral sqlite): CSV-import a few parts → certify a quarantined item to Bonded → transfer one to Dope → issue with FEFO → read the tally card and check the balance math on screen → fuel receive + issue. Zero console errors.
- Full suite + build green before push. Seed demo data must NOT ship to production seeders.

## Definition of done
Live: full parts catalogue; stock enters via opening balance/CSV; quarantine certification queue works with SoD; transfers, adjustments, and fuel dump operational; tally cards render AD38-faithful with correct B/F//C/F math; every §2 invariant covered by a test.

## Report back
Diff report: files by group, engine design decisions (balance strategy, locking approach), invariant test list, CSV import behavior, UI screens delivered, deviations, blockers. Confirm Phase 1 deploy verification result at the top.

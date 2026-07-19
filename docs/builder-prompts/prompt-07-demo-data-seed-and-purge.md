# Builder Prompt #7 — Management Demo: Full-Workflow Seed + Clean Purge

**From:** CTO
**Context:** All 5 phases live. Reference data (fleet, types, ATA, stores, counters, roles) is real; every transactional table is empty. The Project Lead will demo the full workflow to NCAT management, after which ALL demo data must be removable before real operation begins.
**Key premise (design around it):** production use has NOT started — therefore purge = truncate all transactional tables. No row-tagging needed. This premise must be stated in the command's own warnings, because it stops being true the day real data enters.
**Baseline:** confirm latest deploy green + live smoke-test first (standing rule).
**Deliverable back:** diff report + demo-runbook.

## Skills to invoke
`task-observer` at start. TDD for the purge command (it's destructive — test it hardest).

## Task

### 1. `demo:seed` artisan command
Idempotent-guarded (refuses to run twice without `demo:purge` first; refuses if any transactional table already has data unless `--force`). Seeds a coherent story with **backdated timestamps spread over the last ~10 weeks** so charts, tally B/F math, and quarantine-aging all look alive:

- **Parts (~40–60, realistic GA aviation)** across ATA chapters and the fleet types: tyres (DA-40/TB-9 mains, nose wheels), Concorde/Gill batteries, oil & fuel filters, spark plugs/glow plugs, brake pads/discs, ECU/avionics units (serialized: GTN-650, G1000 display, transponder), Aeroshell W100/15W-50 (shelf-life batches), sealants/dope & MEK (flammable → Dope Store), Jet-A1 and AVGAS 100LL (fuel). Real-looking part numbers, units, prices in ₦, min/max/reorder levels.
- **Stock history that exercises every mechanism**: opening balances → several SRVs (some certified to Bonded, flammables to Dope, one still **sitting in Quarantine 12+ days** for the aging alert), transfers, adjustments with reasons, fuel receipts + issues to specific aircraft.
- **Documents telling one clear demo narrative** (mirroring their real ledger style):
  - ~10 Work Orders across aircraft: snags ("R/H main wheel tyre worn out" on 5N-CAK, "ECU A failed after start" on 5N-CZB…) and scheduled inspections (100 HRS on 5N-BZH, ANNUAL on 5N-BZE) — mix of closed, in-progress, open.
  - Requisitions in **every status**: draft, submitted (2 pending approval → badge lights up), approved awaiting issue, rejected-with-remarks, issued with completed removal section (serial → at_repair with repair facility), closed.
  - SIVs: one bundling multiple approved requisitions, one standalone consumables issue, one partial issue; SRVs with LPO refs, invoice numbers, batch/serial capture.
- **Alert coverage**: at least one part below min, one below reorder, one above max, one expired batch, one expiring ≤90 days — so the dashboard alert panel is fully lit.
- **Demo users** (email domain `@demo.ncatfmd.local`, flagged `is_demo`): one per starter role (Stores Officer, Storekeeper, Engineer/Technician, Viewer) with a documented shared demo password — so the Project Lead can role-switch live in front of management. Real admin accounts untouched.
- Counters advance naturally through the seeded documents from their current values.

### 2. Demo-mode banner
- A `demo_mode` flag (settings table or cache-backed flag file) set by `demo:seed`, cleared by `demo:purge`: while set, a slim, elegant NCAT-gold banner across the app: "DEMO DATA — for presentation only". Include it in the topbar region, visible on every page incl. PDFs (small "DEMO" watermark on generated vouchers while flag is set — vouchers printed during demo must never be filed as real).

### 3. `demo:purge` artisan command
The destructive one — build it defensively:
- Requires `--i-understand-this-deletes-all-transactional-data` AND interactive typed confirmation of the app name (skippable only with an additional `--no-interaction-confirmed` for documented use).
- **Step 1: automatic DB backup** (spatie backup:run --only-db) — abort purge if backup fails.
- **Step 2: truncate in FK-safe order**: notifications, activity_log, stock_movements, stock_balances, siv_items/sivs, srv_items/srvs, requisitions, work_orders, part_serials, part_batches, parts, fuel transactions, plus any demo uploads; delete `is_demo` users; clear demo_mode flag.
- **Preserves**: real users, roles/permissions, aircraft, aircraft_types, ata_chapters, stores, and resets document_counters to their pre-demo values (snapshot them in a `demo_state` record at seed time; restore + mark unconfirmed for the department's real values).
- **Step 3: verification report** printed after purge: row counts of every transactional table (must all be 0), preserved-table counts, counter values, backup file path. Command exits non-zero if any transactional table is non-empty.
- Add both commands to the go-live checklist: "If a demo was run: execute demo:purge, verify the zero-count report, then enter real opening balances."

### 4. Demo runbook — `docs/DEMO_RUNBOOK.md`
A presentation script for the Project Lead: suggested 15–20 min flow through the seeded narrative — dashboard alerts → fleet → 5N-CAK workspace (open snag WO) → its requisition → approve as Stores Officer (role-switch) → issue SIV with FEFO → tally card showing the movement → quarantine queue certification → fuel dump issue → reports + PDF print. Notes which demo user to be logged in as at each step, and ends with the purge procedure.

### 5. Tests & verification
- Seed test: command populates every transactional table; alert scopes all return ≥1; every requisition status present; runs green on sqlite + MySQL CI.
- **Purge tests (the critical ones)**: after seed→purge, every transactional table is 0 rows; real users/roles/reference data untouched (count + spot-check assertions); counters restored; demo users gone; flag cleared; purge without the safety flags refuses; purge aborts when backup fails (fake a failing backup).
- Seed→purge→seed cycle works (rerunnable for multiple demo sessions).
- Browser-verify seeded state: dashboard fully lit, banner visible, one workspace flow, a demo-watermarked PDF. Then purge locally and verify the app renders cleanly empty (no orphan-data errors on dashboard/fleet/registers).

## Definition of done
Live site seeded with the full demo narrative and banner after `demo:seed`; DEMO_RUNBOOK ready; `demo:purge` proven by tests and a local cycle to return the system to a clean pre-launch state (backup taken, counters restored, zero-count verification printed).

## Report back
Diff report + the runbook summary, the demo credentials list, exact purge invocation for the go-live day, and confirmation of the seed→purge→seed cycle test.

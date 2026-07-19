# Builder Prompt #4 — Phase 3: The Paper Documents — Work Orders, Requisitions, SRV, SIV

**From:** CTO
**Spec:** v1.1 §4 (Documents), §5 (rules 1–5), §7 (modules 4–7). **Ground truth: the four forms in `forms_reference/` — re-view all of them before building each document.** Field-for-field fidelity; forms win over spec text; flag discrepancies.
**Baseline:** Phase 2 live. Confirm the latest deploy is green + smoke-test before starting (standing rule).
**Deliverable back:** diff report summary.

Everything in this phase posts through `StockService` — no new write-paths to the ledger.

## Skills to invoke
`task-observer` at start (apply OPEN observations). TDD for numbering, status flows, and all ledger-posting paths. `frontend-design` / `ui-ux-pro-max` / `impeccable` for UI.

## Task

### 0. CI hardening (do first, small)
Add a MySQL 8 service container to the GitHub Actions test job and run the suite against it (keep sqlite for local dev). This makes the Phase 2 oversell/concurrency test exercise real `lockForUpdate` row locking in CI. If any test behaves differently on MySQL, fix before proceeding — that's the point.

### 1. Document numbering
- All four series draw from `document_counters` via the row-locked `DocumentCounter::reserve()`.
- Work Orders: `FMD/{wo_code}/{MM}/{YY}/{serial}` using the aircraft type's canonical `wo_code`, current month/2-digit year, global running serial. Number reserved at creation (not draft-preview) so no gaps from abandoned forms; test that concurrent creations produce no duplicate/skipped serials (MySQL CI makes this real).
- Requisition / SRV / SIV: plain padded serials per their counters.
- Counters remain admin-editable; surface "provisional — confirm with department" flag in admin until confirmed.

### 2. Work Orders (mirrors the ledger xlsx)
- Model exists conceptually per spec §4: wo_ref, aircraft_id, work_type (`snag` | `scheduled_inspection` | `other`), title/description, status (open → in_progress → closed), raised_by (free-text name + optional user link — ledger shows non-system names), qc_checked_by, records_updated_by, filed_by, dates, remarks.
- UI: **register view matching their xlsx columns** (S/NO, date, A/C reg, type of inspection/description, WO ref, raised by, QC check by, records updated by, filed by) — searchable, filterable (aircraft, type, status, date range), premium table treatment. Detail page shows linked requisitions. Create/edit forms with aircraft picker and work-type-aware fields (snag text vs inspection preset list: 50/100/200/1000 HRS, ANNUAL, plus free text).
- Closing a WO warns (not blocks) if it has requisitions not yet closed.

### 3. Requisitions (paper-exact: ONE unit per voucher)
- Full Requisition Sheet field set incl. header (required_for, aircraft reg, engine_serial_no, position, authorised_by, supply_source), part identification block (full description, part no via part picker with free-text fallback, stock_code, serial_number, batch_no + year, bin/bal_line_no), optional work_order_id link.
- Status flow: draft → submitted → approved / rejected (with remarks; `requisitions.approve`, approver ≠ requester — enforce and test) → issued → closed. Approval queue screen for approvers (badge count in sidebar).
- **Removal section** (post-issue, technician): serial_no_removed, zone, unit_changed_by, reason_for_removal, date, repair_facility_workshop, date_sent, repair_order_ref. Completing it transitions the removed serial in `part_serials` → removed_unserviceable / at_repair (with store/aircraft cleared appropriately) — through StockService/serial state machine, tested.
- Requisition detail renders as the paper sheet layout on screen (print CSS; PDF in Phase 5), including the distribution footer.

### 4. Receiving — SRV
- Full SRV field set: srv_number, date, destination store (default Quarantine; Fuel Dump auto-selected for `is_fuel` parts and locked), lpo_or_petty_cash_ref, supplier, head_of_receiving_dept, storekeeper, posted_by. Items: qty, supplier/material details, part picker (create-part inline for new parts, `parts.manage`), fol_no, rate, amount, invoice_no, acct_code, batch/expiry/serials capture (required when part flags demand it).
- Draft → **posted** (irreversible; posts `receive` movements into destination store via StockService; corrections are adjustments/returns). Posted SRV renders read-only in the paper layout.
- Quarantined receipts appear automatically in the Phase 2 certification queue (verify end-to-end).

### 5. Issuing — SIV
- Full SIV field set: siv_number, requisition_for, ordered_by (name/date), school_section, approved_by + date, entered_by + date, issued_by/received_by + dates, remark. Multi-line items: part, description, qty_required, qty_issued, stores_folio, rate, amount, charging_code.
- Lines can be: (a) pulled from **approved requisitions** (picker showing approved-not-yet-issued; line links requisition_id; issuing flips the requisition to issued and feeds qty_issued back), or (b) standalone consumable lines. Mixed SIVs allowed.
- Posting: `issue` movements from Bonded/Dope only, batch FEFO picker per Phase 2 behavior, serialized lines pick specific serials (status → installed with aircraft when the linked requisition has one). Partial issue supported (qty_issued < required); requisition stays approved until fully issued or manually closed.
- Posted SIV read-only in the paper layout (qty-in-words auto-generated for display/PDF).

### 6. Aircraft & dashboard touchpoints (light — full experience is Phase 4)
- Part detail and aircraft pages gain "documents" tabs (WOs/requisitions/SIVs for that aircraft). Sidebar badge counts: pending approvals (approvers), quarantine queue (certifiers).

### 7. Tests & verification
- Numbering: format correctness (incl. wo_code + month/year), concurrent reservation uniqueness (MySQL CI), counter continuation after admin edit.
- Requisition flow: full happy path; approver ≠ requester; rejection; removal section drives serial state; permission 403s per action.
- SRV: posts to Quarantine; fuel auto-routes to Fuel Dump; batch/serial capture validation; posted immutability (edit attempt fails).
- SIV: issue against approved requisition (full + partial); standalone line; FEFO respected; serialized issue → installed on aircraft; bonded/dope-only enforcement; posted immutability.
- End-to-end feature test: WO → requisition → approve → SIV issue → removal section → serial at_repair; and SRV → certify → available → issued.
- Browser-verify (ephemeral sqlite) the same end-to-end chain in the UI, zero console errors. Full suite green on sqlite AND MySQL CI before push.

## Definition of done
Live: the department's complete paper workflow runs digitally end-to-end — WO register matching their ledger, one-unit requisitions with approval + removal tracking, SRVs landing in Quarantine, SIVs issuing through FEFO with requisition feedback — all posting through StockService, all numbered from the counters, all permission-gated and audit-logged, suite green on real MySQL in CI.

## Report back
Diff report: files by group, numbering implementation notes, any MySQL-vs-sqlite test differences found in step 0 (important!), form-fidelity deviations, screens delivered, blockers.

# Builder Prompt #10 — Phase 8: Shipping + Loaners (closes the v1.2 extension)

**From:** CTO
**Spec:** v1.2 §12.6, §12.7, §12.8. This is the last phase of the management-requested scope.
**Baseline:** CI split merged; Phase 7 merged after a green PR test run and live-verified. Standing rule: confirm latest deploy green + smoke-test before starting. Work on a branch and open a PR so the test job runs before any deploy — that is now the normal flow, not an exception.
**Deliverable back:** diff report summary + updated handover docs.

## Skills to invoke
`task-observer` at start (apply OPEN observations, including #12 on `validated()` ordering: shipment timelines and loan lines are both multi-row). TDD for the loan ledger postings and ownership accounting. `frontend-design` / `impeccable` for the timeline UI, which is the visual centrepiece of this phase.

## Task

### 1. Shipping module
- `shipments`: reference (auto series `SHP-{YY}-{serial}`, own counter), vendor_id, source document (nullable link to a PO **or** RO; standalone allowed), description, carrier/awb reference (nullable), expected_arrival_date (nullable), current_status (denormalised from the latest event for list/filter performance), closed_at.
- `shipment_events`: **append-only** ordered log (shipment_id, status label, event_date, note, recorded_by, created_at). No edit or delete of a posted event; a mistake is corrected by adding a superseding event with a note. Same discipline as the stock ledger, and for the same reason.
- Admin-managed suggested status list (`app_settings` or a small table, builder's call): seeded with Shipped, Arrived at local port, Cleared customs, Picked up by local courier, In transit to NCAT, Arrived at NCAT. Free text is always allowed; the picker suggests, it does not constrain.
- **Arrival handoff:** when an event marks arrival at NCAT, the shipment detail offers "Create SRV from this shipment", pre-filling vendor, PO link (when the shipment references a PO), and lines from the PO's outstanding quantities. The SRV then follows the existing Quarantine flow untouched. A shipment records which SRV(s) fulfilled it.
- UI: list (filter by vendor, status, overdue, source order) + detail page whose centrepiece is a **premium vertical timeline** of events, newest at top or chronological (builder's design call), each entry showing status, date, who recorded it, and note. Add-event form inline. Overdue shipments (past expected_arrival_date, not arrived) are visibly marked.
- Alert: overdue shipments join the dashboard alert panel and bell, using the existing alert-service pattern.
- Permissions `shipping.view/manage` per §12.8.

### 2. Loaners module, both directions
One sidebar entry, two clearly separated views (Outbound / Inbound), since the mechanics differ.

**Outbound (external party borrows from NCAT):**
- `loans`: direction `out`, borrower (vendor_id or free-text organisation + contact), part/serial/batch references, quantity, from store (Bonded or Dope only, same rule as issuing), loaned_at, due_date, status (on_loan / returned / written_off / overdue is derived not stored), return_condition note, returned_at.
- Posting: issuing a loan posts `loan_out` movements through **StockService** (new movement_type, no new write path). Return posts `loan_return` movements back into the originating store. Write-off routes through the existing adjustment path with a mandatory reason and `stock.adjust` permission, so unreturned stock leaves the ledger honestly rather than lingering.
- Serialized loans move specific serials; the serial's location reflects being on loan (new state or a location marker, builder's call, must not read as "in store" while out).

**Inbound (NCAT borrows from another organisation):**
- `loans` with direction `in`: lender, item description, part link (nullable, may not be catalogued), serials, quantity, received_at, due_date, returned_at, status.
- **Ownership accounting, the part that matters:** inbound loaned stock must never inflate NCAT-owned stock value. Either keep it off the owned-stock ledger entirely with its own tracked location, or carry an ownership flag that every value and stock-summary query filters on. Whichever you choose, prove it with a test asserting stock value and stock summary reports are unchanged by an inbound loan. If an inbound item is issued to an aircraft, the issue is allowed but the document and the parts-on-aircraft view mark it visibly as loaned property.

**Both directions:** overdue alert when past due_date and not returned, surfaced on dashboard + bell; a "record return" action; loan history on the vendor detail page (fills the Phase 7 tab shell).
- Permissions `loans.view/manage` per §12.8.

### 3. Reports coverage
Add two reports to the existing module, same lazy-cursor + streamed-CSV pattern: **Outstanding Loans** (both directions, with days overdue) and **Shipments In Transit** (with days since last event and overdue flag).

### 4. Demo and purge, final extension
- Purger: add shipments, shipment_events, loans, and the new counter series to truncation and the zero-count report; demo vendors already handled. The seed→purge→seed cycle test must stay green and the report must fail if any new table is non-empty.
- Seeder: extend the narrative with one shipment mid-timeline against the existing demo PO (so the timeline renders with several events), one arrived shipment that produced an SRV, one overdue outbound loan (lights the alert), and one inbound loaned item currently installed on an aircraft (so the loaned-property marking is visible in the demo).

### 5. Handover docs, final pass
- `docs/USER_GUIDE.md`: add role-oriented sections for Approval Workflow configuration (Phase 6), Vendors and Orders (Phase 7), Shipping and Loaners (this phase).
- `docs/ADMIN_GO_LIVE_CHECKLIST.md`: add confirmation of the PO/RO counter values, the letterhead contact names **and the email address** (flagged as likely malformed on the paper samples), the suggested shipment status list, and a reminder that `approvals.manage` should be granted to whoever owns process configuration.
- `docs/DEMO_RUNBOOK.md`: extend the script with the shipment timeline and the overdue loan alert.

### 6. Tests & verification
- Shipping: event append-only (no update/delete route or model path); denormalised current_status stays consistent with the latest event; SRV-from-shipment pre-fill and PO linkage; overdue detection; timeline ordering under same-day events.
- Loans outbound: ledger postings via StockService, store-type restriction, serial location correctness while on loan, return restores balance, write-off path requires permission and reason, overdue derivation.
- Loans inbound: **stock value and stock summary unchanged** (the key test), loaned-property marking on issue and parts-on-aircraft.
- Reports: both new reports honour filters and permissions.
- Demo: seed→purge→seed green with new tables in the zero-count report.
- Multi-row form ordering regression (observation #12 class) on shipment events and loan lines.
- Browser-verify (ephemeral sqlite): shipment created against the demo PO → three timeline events added → arrival → create SRV from shipment → lands in Quarantine; outbound loan issued from Bonded → balance drops → marked overdue → returned → balance restored; inbound loan received → stock value unchanged on the dashboard. Zero console errors.
- Full suite green on the PR (sqlite + MySQL) before merge.

## Definition of done
Live: Shipping tracks inbound orders on an append-only timeline that hands off cleanly into the existing SRV/Quarantine flow; Loaners tracks both directions with real ledger postings, overdue alerts, and inbound stock that provably never inflates NCAT's stock value; reports and demo/purge cover the new modules; handover docs describe everything management asked for.

## Report back
Diff report: files by group, timeline and append-only design, loan ledger posting notes, the ownership-accounting approach and its proof, demo/purge extension, docs updated, deviations, blockers. This closes the v1.2 scope, so also list anything you would recommend for a future phase based on what you saw building it.

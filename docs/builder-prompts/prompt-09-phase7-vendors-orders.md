# Builder Prompt #9 — Phase 7: Vendors + Orders (Purchase Orders & Repair Orders)

**From:** CTO
**Spec:** v1.2 §12.4, §12.5, §12.8. Forms ground truth: `forms_reference/Purchase Order.png` and `forms_reference/Repair Order.png`. View both before designing; field-for-field fidelity; forms win over spec text; flag discrepancies.
**Baseline:** Phase 6 merged, MySQL CI green (Project Lead verified). Standing rule: confirm latest deploy green + live smoke-test before starting.
**Deliverable back:** diff report summary.

## Skills to invoke
`task-observer` at start (apply OPEN observations, including #12 on `validated()` array ordering: both order forms are multi-line and hit the same class of bug). TDD for numbering, status transitions, and the RO/serial linkage. `frontend-design` / `impeccable` for UI and the two PDFs.

## Task

### 0. Carry-over from Phase 6 (small, do first)
Admin Approval Workflow screen: for each level, resolve and display how many active users its binding currently matches. Warn inline when the count is zero ("no one can currently act on this level") and show the count when it is one. Include role-bound Super Admin nuance in the warning copy (Super Admins do not match role bindings). Test the zero-holder warning.

### 1. Vendors module
- `vendors`: name, type (`supplier` / `repair_organization` / `both`), address (multi-line text as printed on the forms), country, email, phone, contact_person, notes, is_active, soft deletes. Permission group `vendors.view/manage`.
- Sidebar entry "Vendors": Add-new form above a filterable, searchable list (filters: type, country, active), per management's requested layout. Vendor detail page: info card + tabs for linked Purchase Orders and Repair Orders (Shipments and Loans tabs arrive in Phase 8; build the tab shell to accept them).
- Vendors are referenced by orders; block hard-deleting a vendor with orders (soft-deactivate instead).

### 2. Orders module: shared foundation
- Sidebar entry "Orders" with two submodules (tabbed or segmented index): Purchase Orders, Repair Orders.
- Two new counter series in `document_counters` (provisional, admin-editable, unconfirmed): PO serial (sample 307 → seed next 308), RO serial (sample 298 → seed next 299).
- Reference formats from the samples: PO `NCAT/FMD/PO/TS/{D}/{M}/{serial}` (day/month of issue date), RO `NCAT/FMD/RO/TS/{MM}/{serial}`. Generate via DocumentNumberService at issue time (draft orders carry no number; number reserved when moving draft → issued, consistent with no-gaps practice).
- Shared fields per the forms: vendor (address block rendered from the vendor record), aircraft type header (nullable), priority checkboxes (A.O.G / Very Urgent / For Inventory; single-select), NCAT contacts block (two name+email pairs; seed defaults from the samples into a settings record, editable in Administration), prepared_by label ("Head, Materials and Stores."), the printed NOTE text per form type.
- `orders.view/create/edit/close` permissions per §12.8; starter-role defaults per spec; policies + audit logging as everywhere else.

### 3. Purchase Orders
- Lines: description, part number (part picker with free-text fallback: POs often order parts not yet in the catalogue), qty_to_order, per-line status (NEW / etc., free-ish enum per sample), timeline (month + year).
- Header statuses: draft → issued → partially_received → received → closed / cancelled (cancel requires reason; issued+ orders are immutable in their commercial fields, corrections via cancel-and-recreate or a new revision line, builder's call, justify).
- **SRV linkage:** SRV gains optional `purchase_order_id` next to the free-text LPO field. When receiving against a PO: line picker pre-fills from PO lines, received quantities accumulate on the PO, status auto-advances to partially_received/received. The existing SRV → Quarantine flow is untouched.
- Paper-exact PDF matching the sample (letterhead with NCAT crest + aerodrome address block + emails, boxed PURCHASE ORDER title, ref + date line, vendor address, aircraft type line, the table, NOTE box, contacts, priority checkboxes with the selected one ticked, prepared-by signature line).

### 4. Repair Orders
- Vendor must be `repair_organization` or `both` (validated).
- Lines: description, part number, **serial_no** (picker over serials in `removed_unserviceable` / `at_repair` states, with free-text fallback), qty (1 for serialized lines), action (OVERHAUL / REPAIR / TEST / INSPECT / free text per sample).
- **Removal-flow linkage (the important one):** creating an RO line from a tracked serial sets that serial to `at_repair`, stamps the RO reference into the originating requisition's `repair_order_ref` (back-link, shown on the requisition detail), and the RO line links back to serial + requisition. When the RO is marked returned: per-line disposition — serviceable (serial → in_store via a StockService return/receive posting into Quarantine for re-certification, consistent with §5 rule 3) or scrapped (serial → scrapped). Test the full circle: removal → RO → return → re-certification → issuable again.
- Header statuses: draft → issued → at_vendor → returned → closed / cancelled.
- Paper-exact PDF matching the sample.

### 5. Demo & purge coverage (keep the guarantee intact)
- Extend `DemoPurger`'s truncation list and zero-count report with the new transactional tables (purchase orders + lines, repair orders + lines) and demo vendors (seed vendors with an `is_demo`-style marker or via name-space; builder's call, must be provably removed). Extend `DemoSeeder` minimally: 2 vendors (one supplier, one repair org), 1 PO mid-life, 1 RO tied to the existing at-repair serial in the demo narrative. Purge tests updated; seed→purge→seed cycle stays green.

### 6. Tests & verification
- Numbering: formats match the samples (PO day/month parts, RO month zero-padded), reserved at issue not draft, concurrent issue uniqueness (MySQL CI).
- PO: full lifecycle; SRV-against-PO accumulation and status advance; over-receipt rejected (cannot receive more than ordered without an explicit flagged tolerance).
- RO: vendor-type validation; serial linkage circle test above; requisition back-link.
- Vendors: filter/search, delete-block with orders.
- PDFs render for both with all sample regions present (assert key strings).
- Multi-line create/edit for both order types: explicit test for line ordering integrity under partial rows (observation #12 regression class).
- Demo: seed→purge→seed green with the new tables in the zero-count report.
- Browser-verify (ephemeral sqlite): create vendor → PO with 3 lines → issue (number minted) → SRV against it (partial) → PO shows partially_received; RO from the demo at-repair serial → returned serviceable → appears in Quarantine certification queue. Both PDFs downloaded. Zero console errors.
- Full suite green (sqlite + MySQL CI) before push.

## Definition of done
Live: Vendors module per management's layout; Orders module with paper-exact PO and RO documents and PDFs, numbered in their real series; receiving flows against POs; repair orders close the removal loop back through Quarantine; approval-level holder warnings in admin; demo purge still provably total.

## Report back
Diff report: files by group, numbering/format notes, PO receiving design, RO serial-circle evidence, PDF fidelity notes, demo/purge extension, deviations, blockers.

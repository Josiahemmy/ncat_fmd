# Builder Prompt #11 — Phase 9: Punch List and UAT Readiness

**From:** CTO
**Context:** the v1.2 management scope is complete. This phase fixes what building it exposed, and closes the remaining pre-UAT gaps. No new modules.
**Baseline:** Phase 8 merged after a green PR test run. Standing rule applies: confirm the deploy is green and smoke-test before starting.
**Deliverable back:** diff report + a short UAT readiness statement.

## Skills to invoke
`task-observer` at start. `superpowers:systematic-debugging` for item 0, since the reported symptom and the code disagree. `frontend-design` / `impeccable` for the error-surfacing and attachment UI.

## 0. Reconcile the order PDF finding first (investigate before fixing)

Your Phase 8 report states PO and RO PDFs return HTTP 204 and are unimplemented. The code disagrees, and so does your own Phase 7 test. Established facts:

- `routes/orders.php` registers both PDF routes against `PdfController`, gated on `can:orders.view`.
- `PdfController::purchaseOrder` and `::repairOrder` are implemented and stream via dompdf. `resources/views/pdf/orders/{layout,purchase-order,repair-order}.blade.php` all exist.
- `PurchaseOrderTest::test_the_pdf_renders_every_region_of_the_sample` requests the real route and asserts `content-type: application/pdf`.
- There is no `204` or `noContent` in `PdfController`.

Do not reimplement anything. Diagnose first and report what you find. The leading hypothesis is the front-end trigger: if the button issues an Inertia visit (`router.get`, `<Link>`) rather than a plain `<a href target="_blank">` or `window.open`, the client requests an Inertia response and receives a PDF, which fails client-side and can read as an empty response. Compare against how the Phase 5 voucher PDF buttons (requisition, SIV, SRV, tally) are wired, since those were verified working.

Report the actual HTTP status, content-type and byte length from a direct request, both locally and against production. Fix whatever is genuinely broken, and if the endpoints were fine all along, say so plainly and correct the record.

## 1. Surface post-time validation errors (the clerk-facing bug)

`Srv/Show.jsx` swallows post-time validation failures: posting an SRV whose shelf-life line has no batch number returns a 302 and leaves the voucher in draft with nothing on screen. The refusal is correct, the silence is not.

- Fix the SRV case, then **audit every other action that posts, issues, approves, certifies, transfers, adjusts or cancels** for the same pattern. Any action that can fail validation server-side must surface the reason. Where the failure is about a specific line, point at the line.
- Add a feature test per action class asserting the error is returned in a form the page renders, not merely that the action was refused.

## 2. Attachments on shipment events

An airway bill, a customs release note and an agent's invoice are what timeline entries actually reference. Add one or more files per shipment event.

- Storage on cPanel shared hosting: keep files under `storage/app` (not `public/`), served through a permission-gated controller route, never a direct URL. Validate type (pdf, jpg, png, webp) and size (cap at 5 MB per file, justify if you choose otherwise). Filenames sanitised and stored with generated names.
- **Report the backup implication**: `spatie/laravel-backup` currently dumps the database only. State whether attachments should join the backup set and what that does to backup size over a year at realistic volumes. Do not change the backup scope without saying what it costs.
- Attachments appear inline on the timeline entry with type icon and size, downloadable by users with `shipping.view`.
- Purge: attachment rows and their files must be removed by `demo:purge`, with orphan-file cleanup verified by test.

## 3. Loans: header and lines (confirm before building)

A real loan is often several items to one borrower under one agreement, and you are right that restructuring is far cheaper before production data exists. **The Project Lead is confirming with the department whether multi-item loans occur.**

- If confirmed: split `loans` into a header (borrower, direction, agreement reference, dates, status) with `loan_lines` (part, serial, batch, quantity, per-line return state), so a partial return is representable. Migrate existing single-line loans into the new shape. Overdue derives from the header; returned derives from all lines being returned.
- If not confirmed by the time you reach this item: skip it, and note in your report that the door is still open but closes once real loans exist.

## 4. Lighthouse, finally

Never run, outstanding since Phase 5. Run it (or PageSpeed Insights for the public login page) across login, dashboard, fleet, a register, a voucher show page and a shipment timeline. Report the four scores per page. Fix anything cheap. For anything expensive, describe the cost rather than silently absorbing it.

## 5. Final UAT sweep

- Walk `docs/ADMIN_GO_LIVE_CHECKLIST.md` end to end against the live site and report any step that no longer matches reality.
- Confirm `demo:purge` still zeroes every transactional table now that Phases 6 through 8 added tables, and that the zero-count report lists them all. This is the guarantee the department is relying on to start clean.
- Confirm the two loan holding stores read as system locations on the Stores page and do not offer raise-requisition or raise-issue actions.

## Explicitly deferred, with reasons

Do not build these. Recorded so the decision is visible rather than forgotten:

- **Overdue digest emails.** Needs a mail transport decision on cPanel and a call about who owns chasing. Worth doing, but it is a policy decision before it is a code change.
- **Period-close balance snapshots.** Correct eventually, premature now. Every total is computed from full history, which is right for correctness, and the volumes do not yet justify the complexity or the reconciliation burden a snapshot introduces. Revisit when a dashboard or report measurably slows, and bring the measurement.

## Tests and verification
Full suite green on the PR (sqlite and MySQL) before merge. Browser-verify the error surfacing on at least three different failing actions, an attachment upload and download, and the purge zero-count report.

## Report back
Diff report plus: the PDF diagnosis and what was actually wrong, the list of actions audited for error surfacing and which were broken, the attachment backup implication with numbers, whether the loan restructure ran, Lighthouse scores per page, checklist drift found, and a one-paragraph UAT readiness statement.

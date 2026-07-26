# Builder Prompt #13 — Phase 11: The UAT Gate

**From:** CTO
**Context:** Phase 10 is green on PR #5 and merging. This is a short, finite list standing between the department and UAT. Four items. Nothing else.
**Baseline:** verify rather than assume, as you have been. If anything here contradicts the code, the code wins and the report says so.
**Deliverable back:** short diff report. No readiness essay needed this time; a one-line verdict will do.

## 1. Freeze closed shipments (decision made)

Ruling: freeze on close, with an explicit re-open. This is not a department question, because the correction path stays open and the behaviour matches what `ShipmentService::close()`'s own docblock already promises.

- `addEvent()` throws a `DomainRefusal` when `closed_at` is set. The "Record an event" form hides on a closed shipment and says why.
- Add a re-open action gated on `shipping.manage`, audit-logged, which clears `closed_at`. Re-open, add the event, close again is the correction path. Nothing is deleted, consistent with the append-only timeline.
- Rationale for the record: posted SRVs and SIVs are immutable, issued orders freeze, the stock ledger is append-only. A closed shipment that anyone can silently mutate was the only finalised document in the system without a lock.
- Repair the data already on production: SHP-26-0002's `current_status` regressed to "Shipped" after arrival. Correct it via the re-open path once it exists, so the fix is exercised on the case that exposed the bug.

## 2. Sweep the docblocks

Two for two now. `StockException` claimed an HTTP 422 mapping that did not exist and shipped six actions returning 500. `ShipmentService::close()` claims reversibility by admin re-open, which does not exist either.

Grep the application layer for docblocks and comments that assert runtime behaviour: HTTP status mappings, immutability, "only", "never", "always", "reversible", "enforced", "guarded". For each, either confirm the behaviour exists or fix the mismatch. Where the comment is aspirational and the behaviour is not wanted, delete the comment rather than leaving a claim nobody honours.

Report the list you checked and what you found. This is a one-off audit, not a permanent chore.

## 3. Brand cyan on the page background

Take your own fix, with your own reasoning about margin applied to it.

You are right that my ruling was inaccurate: `#009DE0` clears 3:1 on a white card at 3.04:1 but misses it at 2.91:1 on `#F8FAFC`. Fix it so the claim is true on every surface we actually render.

Do not ship `#009ADB` at 3.02:1. You chose 30% over 31% on the darkened cyan precisely so a later tint adjustment could not silently drop it under the bar, and that reasoning applies here with more force, because this value is the brand primary and will be touched again. Pick a value with real margin, confirm it is still visually indistinguishable from the brand colour at render size, and report the measured ratio on every surface it appears on.

## 4. Purge output wording

"Preserved reference data" showing `vendors: 0` reads as a contradiction to anyone who has not read the code, and the person reading that output during go-live will be doing it once, under pressure, to confirm the system is clean. Reword so the categories are unambiguous: what was truncated, what was never touched, and what had demo rows removed from it while real rows stayed. The users row going 5 to 1 should be legible as exactly that.

## Not in scope

The asset custody gap is the Project Lead's to resolve, not a code change. The HTTP/2 and TTFB tickets stay in `OPEN_TICKETS.md`. The loans restructure remains unconfirmed.

## Verification
Hand-drive the shipment freeze and re-open in a browser, since that is the item touching live data. Full suite green on the PR, SQLite and MySQL. Push and open the PR without waiting; do not merge without the Project Lead's go-ahead.

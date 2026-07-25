# Builder Prompt #8 — Phase 6: Configurable Approval Workflow + SIV & Store-Page Refinements

**From:** CTO
**Spec:** now v1.2. Read §12.1, §12.2, §12.3 closely; they are the whole phase. §12.4 through §12.8 are Phases 7 and 8, do not build them yet (but design the notification and permission pieces so they can plug in).
**Baseline:** demo branch merged and live. Standing rule: confirm the latest deploy is green and smoke-test before starting. If demo data is still seeded on live, that's fine; this phase must work with and without it.
**Deliverable back:** diff report summary.

Caution: this phase modifies the live requisition flow, the most process-critical path in the system. The existing behavior (single approval via `requisitions.approve`) must remain the exact out-of-the-box behavior after migration. Treat backwards compatibility as a test target, not an aspiration.

## Skills to invoke
`task-observer` at start (apply OPEN observations). TDD for the workflow engine and the SIV stamping rules. `frontend-design` / `impeccable` for the admin workflow UI and notification surfaces.

## Task

### 1. Approval workflow engine
- Schema: `approval_workflows` (document_type, active) → `approval_levels` (workflow_id, sequence, name e.g. "HOD Approval", bound to a permission OR a role, one of the two) → `requisition_approvals` (requisition_id, level_id, decision approve/reject, decided_by, decided_at, remarks).
- Migration seeds the default workflow: one level named "Approval", bound to `requisitions.approve`. Existing approved/rejected requisitions are backfilled as decided at that level so history renders correctly.
- Engine rules (test each):
  - A submitted requisition sits at the lowest undecided level; only users matching that level's permission/role can act; approver ≠ requester at every level.
  - Approve advances to the next level; approving the final level makes the requisition issuable (status `approved`); reject at any level ends the flow (`rejected`, reason mandatory).
  - Deactivating/reordering levels mid-flight: in-flight requisitions keep the workflow snapshot they started with (store level ids on the approval records; resolve pending level against the snapshot). New submissions use the current config.
  - The SIV picker continues to list only fully approved requisitions.
- Admin UI (`approvals.manage`, new permission): under Administration, "Approval Workflow" screen: ordered level list, add/rename/remove/reorder (drag or up/down), each level bound to a permission or role via picker. Plain warning copy when editing while requisitions are in flight.

### 2. Notifications
- Database-channel notifications + bell integration:
  - When a requisition reaches a level: notify users who can act on that level (cap the audience: users with the bound role/permission).
  - On every decision: notify the requester (approved at level X / rejected with reason / fully approved, ready for issue).
  - On full approval: also notify users holding `issues.post` (the store officer who will raise the SIV; this is the "for issue" alert management asked for).
- Bell dropdown and notifications list render these with humanized lines and deep links to the requisition. Mark-as-read works. Keep the existing live-computed stock alerts untouched; these are event notifications, the other path stays as is.

### 3. Requisition detail page
- Approve / Reject buttons placed directly above the removal block (management's stated placement), visible and enabled only for users who can act on the current pending level; shows which level is pending and the decision trail so far (level name, decider, date, remarks) as a compact stepper. Rejected shows the reason prominently.

### 4. SIV create refinements
- "Requisition for" becomes a searchable picker of fully approved, not-yet-issued requisitions (this largely exists; make it the primary path and label it per the paper form).
- On selection, "Ordered by" and the request date auto-fill from the requisition's requester (full name) and submitted_at: rendered greyed out, read-only. Server-side: these values are always derived from the linked requisition and never accepted from request input (test tampering).
- Standalone SIV lines (no requisition) keep manual ordered-by entry, unchanged.

### 5. Store-page actions
- On each store page (except Quarantine): per-part row actions "Tally" (jump to that part's tally card for this store) plus page-level "Raise requisition" and "Raise issue" buttons that open the existing create forms pre-scoped to the store (issue lines restricted to that store's stock; requisition tagged with originating store context). Quarantine keeps view-only (tally view allowed, no requisition/issue).
- Permission-gated as the underlying actions already are.

### 6. Tests & verification
- Engine: default single-level behaves identically to pre-migration (dedicated regression test against the old feature tests' expectations); multi-level advance; reject mid-chain; approver ≠ requester per level; in-flight snapshot isolation when admin edits levels; issuable only after final level.
- Notifications: level-reached audience, requester decision notices, issues.post alert on full approval; no notification duplication on repeat views.
- SIV: auto-stamp correctness, tamper rejection, standalone path unchanged.
- Store actions: scoping correctness, Quarantine restrictions.
- Backfill: legacy approved/rejected requisitions render their history without error.
- Browser-verify (ephemeral sqlite): configure a 2-level workflow in admin → submit → approve level 1 as one user, level 2 as another → requester's bell shows the trail → SIV picker offers it → auto-stamped ordered-by → post. And the store-page action flow. Zero console errors.
- Full suite green (sqlite + MySQL CI) before push.

## Definition of done
Live: approval levels configurable in admin with the seeded default matching today's behavior; approve/reject sits above the removal block, gated per level; requester and issuing officers get bell notifications; SIV auto-stamps ordered-by from the picked requisition, read-only; store pages launch scoped tally/requisition/issue everywhere except Quarantine.

## Report back
Diff report: files by group, engine design notes (snapshot approach), notification audience queries, backfill result, regression-test evidence that default behavior is unchanged, deviations, blockers.

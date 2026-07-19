# Builder Prompt #6 — Phase 5 (final): Paper-Exact PDFs, Reports, Hardening & UAT Readiness

**From:** CTO
**Spec:** v1.1 §7 (module 10-Reports, printable vouchers), §9 Phase 5 row, §10/§11. Ground truth for every PDF: the actual form images in `forms_reference/` — pixel-level fidelity to the paper layouts, with NCAT branding (use the reserved high-res logo PNGs from `NCAT_Brand_Assets/`, per the Phase 0 decision).
**Baseline:** Phase 4 merged and live (Project Lead has verified CI green + smoke-tested). Standing rule applies.
**Deliverable back:** final diff report + UAT handover summary.

This phase closes the project to UAT-ready. Scope discipline: finish and harden — no new modules.

## Skills to invoke
`task-observer` at start (apply OPEN observations). `frontend-design` / `impeccable` for print layouts and polish. Run `laravel-security-audit` (or equivalent security review) in step 5 and report findings.

## Task

### 1. Paper-exact PDF vouchers (dompdf)
- **Requisition Sheet** — the full 20-field layout incl. header boxes, numbered sections, removal block, distribution footer ("THIS COPY TO: STORES/PROGRESS/SHOP STORE/CONP.PLANNING AND CHIEF INSPECTOR"), NCAT crest.
- **SIV** — multi-line table with qty in figures AND auto-generated words, ₦/K amount columns, approval/issue/receive signature blocks.
- **SRV** — "receive the following items into the ___ store" phrasing with the actual destination store, LPO/petty-cash cross-reference line, certification statement, storekeeper/posted-by blocks.
- **Tally Card (AD38)** — header block (description, part no, location, unit of issue, ledger folio, batch, unit price, MAX/MIN/REORDER) + ledger rows with B/F and TOTALS C/F, honoring the active date filter.
- Download/print buttons on each document Show page + tally card. Signature areas render as blank lines (wet-ink signing of printouts remains their practice). Voucher numbers, dates, and all data positions match the paper forms so staff can file printouts alongside legacy paper. Test: PDF generation succeeds for each type incl. edge cases (long descriptions, many SIV lines paginating correctly).

### 2. Reports module (sidebar entry, `reports.view`)
Filterable screens, each with PDF + Excel/CSV export (maatwebsite/excel or native CSV — builder's call, justify):
- **Stock Summary** — per store / consolidated: part, balances, levels, value; stock-state filter.
- **Movement Register** — the full ledger, filterable by store/part/type/date/user.
- **Expiry Report** — batches expired / expiring ≤N days, by store.
- **Per-Aircraft Consumption** — issues by registration (and rollup by type), date-ranged.
- **Quarantine Aging** — items awaiting certification with days-in-quarantine.
Exports are permission-gated, stream (no memory blowups on full ledgers), and match on-screen filters. Report headers carry NCAT branding + generated-by/date.

### 3. Tracked debt (close it out)
- **Fuel receipts source-morph**: give `fuelReceive` a proper polymorphic source link (SRV), migrating the Phase 3 by-number cross-reference; movement drill-down from fuel screens now navigates to the SRV like every other movement.
- **Consolidated all-stores tally view** (deferred from Phase 2): per-part combined ledger across stores, clearly labeled non-AD38.
- **Deploy stale-file cleanup** (tar-over-SSH lacks `--delete`): add a safe remote cleanup step — e.g., prune `public/build/assets/*` not in the current manifest after extraction. Never touch `storage/` or `.env`.

### 4. Polish pass
- **Accessibility**: keyboard navigability of all interactive flows (fleet accordion, action strip, modals, search), focus states, aria labels on icon buttons, contrast check on badge/chip colors (fix any AA failures — gold-on-white is the usual suspect).
- **Branded error pages**: 403, 404, 419, 500 in the NCAT design language.
- **Empty/loading states** sweep: every list and dashboard card has a designed empty state; skeletons wherever data loads.
- **Lighthouse** on login, dashboard, fleet, a register, a voucher Show: Performance/Accessibility/Best-Practices ≥90 each (report scores; fix what's cheap, flag what isn't).

### 5. Security & ops hardening
- Run a security review (laravel-security-audit skill): auth/session config, rate limiting (login + search + posting endpoints), security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy; CSP if feasible with Vite), HTTPS enforcement, mass-assignment audit on the Phase 3/4 controllers, upload validation. Fix findings; report anything deferred with risk assessment.
- **Backups**: install spatie/laravel-backup — nightly DB dump to local `storage/backups` (cron via cPanel, document the cron line) with 14-day retention; document the restore procedure and recommend the Project Lead also enable cPanel-level weekly backups.
- Verify production error handling: `APP_DEBUG=false`, errors logged not displayed, log rotation sane.

### 6. UAT handover package
- `docs/USER_GUIDE.md` — role-oriented walkthrough (Storekeeper: receive→certify→issue; Engineer: WO→requisition; Approver: queue; Admin: users/roles/counters), with screenshots.
- `docs/ADMIN_GO_LIVE_CHECKLIST.md` — ordered: set real document-counter values (department confirmation), create real users/assign roles, review role permissions, enter opening balances (CSV), change any remaining default credentials, enable cron for backups, confirm HTTPS.
- README updated to final state.

### 7. Tests & verification
- Feature tests: each PDF renders (status 200, correct content-type, key strings present); each report + export honors filters and permissions; fuel source-morph migration keeps historical references intact; consolidated tally math.
- Browser-verify: print/download each voucher type from a real flow; run one report of each type with filters + export; error pages; keyboard-only pass through the main flows. Zero console errors.
- Full suite green (sqlite + MySQL CI). Lighthouse scores captured.

## Definition of done
Live: every voucher prints paper-exact; the Reports module exports cleanly; all tracked debt closed; security review run with findings fixed; backups scheduled and documented; UAT docs delivered — the department can go live using the ADMIN_GO_LIVE_CHECKLIST alone.

## Report back
Final diff report + handover summary: files by group, PDF fidelity notes (any layout compromises), report/export design, security findings (fixed vs deferred + risk), Lighthouse scores, backup/cron setup, remaining known limitations, and the go-live checklist status.

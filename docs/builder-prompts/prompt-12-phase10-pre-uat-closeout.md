# Builder Prompt #12 — Phase 10: Pre-UAT Closeout

**From:** CTO
**Context:** Phase 9 is live. This is the last phase before the department begins UAT. It is short and finite by design. No new modules, no refactors of working code.
**Baseline:** verify before assuming. Phase 9 is reported live and confirmed by the Project Lead; check it yourself rather than taking this line as fact. Three premises in the Phase 9 brief were wrong, and you caught all three by checking. Keep doing that. If anything below contradicts what you find in the code, the code wins and the report says so.
**Deliverable back:** diff report + a final UAT readiness statement.

## Skills to invoke
`task-observer` at start. `frontend-design` / `impeccable` for the contrast work.

## 1. The verification you flagged as missing (do this first)

Your Phase 9 report was straight about what had not been driven by hand. Close it before anything else, because everything after this is cosmetic by comparison.

- **Attachment upload and download**, clicked. Upload a real PDF and a real image to a shipment event, confirm they render inline on the timeline with icon and size, download both, and confirm a user without `shipping.view` cannot reach the file route.
- **Three refusals watched failing in a browser**, one from each class you identified: a silent one (SRV post with a shelf-life line missing its batch number), one of the six that used to 500 (a purchase order action), and a line-specific one (confirm `items.2.batch_no` really does render against "Line 3" on screen, not just in the response).
- **Purge zero-count report driven by hand**, reading the output rather than asserting it.

Report what you saw, not what the tests say. If any of it behaves differently in a browser than under test, that difference is the finding.

## 2. Brand cyan contrast (decision made)

The Project Lead has ruled: restrict the cyan, do not accept the exception.

- `#009DE0` stays for icons, borders, chart series, focus rings, and large display type, where 3:1 is the correct bar and it passes.
- For **button fills and any small text**, move to Deep Navy `#101A62` or a darkened cyan variant that measures at least 4.5:1 against white. Choose whichever holds the brand better and say which you chose and why. Measure and report the resulting ratios rather than asserting they pass.
- Sweep for other uses of cyan as small text or as a fill behind white text. Report anything you change so the Project Lead can show NCAT's brand owner exactly what moved.

## 3. Backup hygiene

Real shipment volume is still unknown, so size-independent fixes only. Do not change retention or the cap.

- **Strip the dead weight** from the backup file set: `.git`, `NCAT_Brand_Assets`, `public/build`, and anything else reproducible from the repository or a build. Report the before and after size of one backup run. Everything excluded must be genuinely recoverable without the backup, and say how for each.
- **Make silent truncation impossible to miss.** When the cap is reached, `delete_oldest_backups_when_using_more_megabytes_than` discards history with no signal. Mail transport is still deferred, so surface it in the app instead: a backup health panel on the Administration dashboard showing last successful backup, its size, the number of copies retained, and total space used against the cap, with a plain warning state when the oldest copy is newer than the retention period implies. Gate it on an admin permission.
- `.env` stays in the backup set. A restore needs it, and it is no more exposed there than the application itself is on the same host. Note it in the restore documentation rather than engineering around it.

## 4. Order PDF weight

2.4 MB per PDF, almost all of it the uncompressed crest. These are emailed to vendors in Austria and England, so the weight is a real irritation at the receiving end even though nothing is broken. Compress or downsample the embedded crest and report the resulting file sizes. Confirm print quality is unaffected at A4.

## 5. CI hygiene

- Bump `actions/checkout`, `actions/setup-node` and `actions/cache` off the deprecated Node 20 runtime to their current majors. Confirm both jobs still pass on a PR before merging.
- The Project Lead is enabling branch protection requiring the test check. If the setting is visible to you after they do it, confirm it is active; do not attempt to configure it yourself.

## 6. Documentation, final

- Fix the `DemoSeeder::DEMO_PASSWORD` reference in `DEMO_RUNBOOK.md` (it is a service class, not a database seeder).
- `ADMIN_GO_LIVE_CHECKLIST.md`: add the backup health panel to the post-go-live checks, and add a line for confirming shipment volume once the department answers, since it determines whether backup retention needs resizing.
- Record in the docs that multi-item loans remain unconfirmed and that the header/lines restructure gets more expensive once real loans exist.

## Explicitly not in scope

- TTFB of 1,890 ms on the production login document and the 115 requests not served over HTTP/2. Both are real, both are shared-hosting and server-configuration questions rather than application defects, and neither blocks UAT. Record them as tickets with what you know. If you can tell from the response headers whether OPcache is off or HTTP/2 is unavailable at the host level, say so, because that determines who can fix it.
- The loans header and lines restructure, still awaiting departmental confirmation.
- Anything the department has not asked for.

## Tests and verification
Full suite green on the PR, SQLite and MySQL. Push the branch and open the PR without waiting for authorisation, per the standing arrangement. Do not merge to `main` without the Project Lead's go-ahead.

## Report back
Diff report plus: what the hand-driven verification actually showed, the contrast ratios measured before and after with which colour you chose, backup size before and after with the recoverability justification per exclusion, PDF sizes after compression, and a final UAT readiness statement. If you believe the system is not ready, say so and say what would make it ready.

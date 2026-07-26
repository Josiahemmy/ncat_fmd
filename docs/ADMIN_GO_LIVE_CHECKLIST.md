# NCAT FMD Inventory — Go-Live Checklist (Project Lead)

An ordered checklist the Project Lead can follow **alone** to take the FMD
Inventory & Stores system live at https://office.ncatfmd.com.ng.

Work top to bottom — later phases assume earlier ones are done. Each phase has a
short **why**. Tick each box as you complete it.

> Prerequisites: the app is already deployed (CI/CD is green) and you can sign in
> as the seeded Super Admin (`superadmin@ncatfmd.com.ng`). If not, complete the
> server bootstrap in `README.md` first.

---

## Phase 0 — If a demo was run (do this FIRST)

_Why: the management demo (`php artisan demo:seed`, see `DEMO_RUNBOOK.md`) fills the
system with disposable, backdated data and demo users. All of it must be wiped
**before** any real data is entered — a full truncate is only safe while real
operation has not started. Skip this phase only if no demo was ever seeded._

- [ ] Purge the demo data (interactive — type the `APP_NAME` when prompted):
      ```
      php artisan demo:purge --i-understand-this-deletes-all-transactional-data
      ```
      (Scripted/non-interactive variant:
      `php artisan demo:purge --i-understand-this-deletes-all-transactional-data --no-interaction-confirmed`.)
- [ ] **Verify the zero-count report** — every transactional table must read **0**.
      If the command reports *"Purge verification FAILED"* (or exits non-zero), stop
      and investigate; do not continue to go-live.
- [ ] Confirm the **preserved reference data** table still shows users, roles,
      aircraft, stores, ATA chapters and document counters intact.
- [ ] Sign in and confirm the gold **"Demo data — for presentation only"** banner is
      **gone** (demo mode is OFF).
- [ ] **Re-confirm the real document-counter start values with the department** —
      purge restores the counters but marks them **unconfirmed** (see Phase 4). Do
      this before entering any real numbers.
- [ ] Only now proceed to enter **real opening balances** (Phase 7) — never on top of
      demo data.

---

## Phase 1 — Secure the administrator account

_Why: the seeded Super Admin ships with a **documented default password**. Nothing
else matters until that account is locked down._

- [ ] Sign in as `superadmin@ncatfmd.com.ng` (seeded password `ChangeMe!NCAT2026`).
- [ ] Complete the **forced password change** prompt with a strong, unique
      password.
- [ ] Store the new Super Admin credentials in your password manager (not in the
      repo, not in `docs.txt`).

---

## Phase 2 — Confirm the environment is production-hardened

_Why: debug mode and insecure cookies leak information; the app must run as
`production` over HTTPS before real data goes in._

- [ ] On the server, edit `.env` and confirm:
  - [ ] `APP_ENV=production`
  - [ ] `APP_DEBUG=false`
  - [ ] `APP_URL=https://office.ncatfmd.com.ng`
  - [ ] `SESSION_SECURE_COOKIE=true`
  - [ ] `APP_KEY` is set (generated during bootstrap).
- [ ] Confirm the subdomain is served over **HTTPS** with a valid certificate
      (cPanel → SSL/TLS / AutoSSL) and that `http://` redirects to `https://`.
- [ ] After editing `.env`, re-cache config: `php artisan config:cache`.
- [ ] Hit the health check: open **`https://office.ncatfmd.com.ng/up`** — it must
      return healthy (HTTP 200).

---

## Phase 3 — Rotate the database credential

_Why: the DB password used during setup should not be the long-term production
secret._

- [ ] In cPanel → **MySQL Databases**, change the password for the
      `almadin1_ncat` DB user.
- [ ] Update `DB_PASSWORD` in the server `.env` to match.
- [ ] `php artisan config:cache`, then reload the site and confirm it still works.

---

## Phase 4 — Set the document-counter starting values

_Why: Work Order / Requisition / SIV / SRV numbers must **continue the existing
paper sequences** — a wrong start value creates duplicate or out-of-order voucher
numbers that break the audit trail._

- [ ] **Confirm the exact next numbers with the department** (Stores/Records).
      The seeded provisional next-values (from the ground-truth forms) are:
  - Work Order → **1344** (paper ledger latest was 1343)
  - Requisition → **1002**
  - SIV → **0294**
  - SRV → **0202**
  - Purchase Order → **308** (paper sample was NCAT/FMD/PO/TS/30/6/**307**)
  - Repair Order → **299** (paper sample was NCAT/FMD/RO/TS/03/**298**)
- [ ] **Confirm the PO and RO next numbers specifically.** Both were read off a
      single sample form each, so they are the least-corroborated values in the
      list. Ask Stores for the most recent order raised on paper in each series.
- [ ] Go to **Administration → Document counters** (`/admin/counters`).
- [ ] Set each counter's next value to the department-confirmed number and save.
- [ ] (Counters seed as provisional/unconfirmed; saving here marks them
      confirmed.)
- [ ] **Shipment** starts at **SHP-{YY}-0001** and needs no confirmation: it is a
      new series with no paper predecessor, so it ships confirmed.

---

## Phase 4a — Confirm the order-document letterhead and contacts

_Why: these blocks print on every Purchase and Repair Order sent to a vendor. A
wrong name or address goes out on NCAT letterhead._

- [ ] Go to **Administration → Order documents** (`/admin/order-documents`).
- [ ] Confirm the two named contacts against the department's current staff.
      Transcribed from the sample forms as:
  - **IBRAHIM M. HIRSE** — hquality@ncat.gov.ng
  - **GAMMANIEL M. DANBATURE** — hfmd@ncat.gov.ng
- [ ] **Confirm the letterhead email addresses.** The first line was transcribed
      verbatim from the paper as `rector@ncat.ng.info@ncat.gov.ng`, which reads
      as two addresses run together, that is, a typo on the printed form. It was
      reproduced as printed rather than silently corrected. Get the department to
      state the correct address and enter it here.
- [ ] Confirm the prepared-by lines. The two forms do not agree with each other:
      the Purchase Order signs off "Head, Materials and Stores." and the Repair
      Order signs off "Materials and Stores." Both are reproduced as printed and
      are separately editable, so the department can settle it here.
- [ ] Confirm the NOTE paragraph on each form.

---

## Phase 4b — Confirm the suggested shipment statuses

_Why: the list drives the shipment timeline's status picker, and one entry on it
is what marks a consignment as having arrived at NCAT._

- [ ] Go to **Administration → Shipment statuses** (`/admin/shipment-statuses`).
- [ ] Review the seeded list against how the department actually describes a
      consignment's progress: Shipped, Arrived at local port, Cleared customs,
      Picked up by local courier, In transit to NCAT, Arrived at NCAT.
- [ ] Add, remove or reorder to match. Free text is always accepted on the event
      form, so this list is a convenience, not a constraint.
- [ ] Confirm the **arrival status** selection. It pre-ticks the "arrived at
      NCAT" box on the event form. The tick on the event itself is what closes
      the shipment, so renaming the label is safe.

---

## Phase 5 — Review each role's permissions

_Why: the seeded roles are sensible defaults, but the department should confirm
who can do what **before** real users are attached — especially segregation of
duties (the receiver must not be the certifier)._

- [ ] Go to **Administration → Roles & permissions** (`/admin/roles`).
- [ ] Review each starter role against the department's intent:
  - [ ] **Stores Officer** — certifies quarantine, approves requisitions, posts
        SIV, transfers/adjusts stock, posts fuel, manages parts and vendors,
        raises and issues orders, tracks shipments and loans.
  - [ ] **Storekeeper** — posts SRV and SIV, posts fuel, tracks shipments and
        loans; **must NOT** hold `quarantine.certify` (segregation of duties).
  - [ ] **Engineer/Technician** — raises Work Orders and Requisitions only.
  - [ ] **Viewer** — read-only.
  - [ ] **Super Admin** — leave as-is (bypasses all checks).
- [ ] **Grant `approvals.manage` to whoever owns process configuration**, and to
      nobody else. It controls who can sign off requisitions, so it belongs with
      the person accountable for the process, not with every approver.
- [ ] **Check `stock.adjust` before anyone starts lending stock out.** Writing
      off an unreturned loan posts a real ledger adjustment and is gated on
      `stock.adjust`, not on `loans.manage`. Confirm the right people hold it.
- [ ] Create any additional real roles the department needs.

---

## Phase 5a — Configure the approval workflow

_Why: a level bound to a role with no active users blocks requisition approvals
outright, and the Super Admin cannot step in on a role binding._

- [ ] Go to **Administration → Approval workflow** (`/admin/approval-workflow`).
- [ ] Confirm the ordered levels match the department's real sign-off sequence.
- [ ] For each level, confirm the binding resolves to at least one **active**
      user. The screen flags empty bindings and single-user bindings; do not go
      live with either unresolved.
- [ ] Do this **after** Phase 6 (real users created), then come back and confirm
      the counts again.

---

## Phase 6 — Create the real users and assign roles

_Why: everyone needs their own account so the audit log attributes every action to
a real person. No shared logins._

- [ ] Go to **Administration → Users** (`/admin/users`).
- [ ] Create an account for each real staff member (name + email).
- [ ] Assign the correct role to each.
- [ ] Confirm each account is flagged for a **forced password change on first
      login** (default for new users).
- [ ] Hand each person their temporary password securely; they set their own on
      first sign-in.
- [ ] Verify a sample user of each role sees only the expected sidebar modules.

---

## Phase 7 — Enter opening balances (CSV import)

_Why: the ledger must start from the real on-hand quantities so tally cards,
alerts and stock value are correct from day one._

- [ ] Digitise the current paper tally cards into the CSV template.
- [ ] Go to **Opening Balances** (`/opening-balances`, requires `stock.adjust`).
- [ ] Download the **template**, fill in each part's opening quantity per store.
- [ ] **Preview** the file — fix any validation errors it reports.
- [ ] **Import**. Confirm a few parts show the expected balances on their tally
      cards and in the Parts catalogue.

---

## Phase 8 — Enable the nightly database backup

_Why: an internal system of record needs an automated, retained backup so a bad
day is recoverable._

- [ ] Ensure the backup package is installed on the server
      (`spatie/laravel-backup`; if absent, `composer require spatie/laravel-backup`
      and publish its config — nightly dump to `storage/backups`, 14-day
      retention).
- [ ] In **cPanel → Cron Jobs**, add a nightly job that runs the scheduler (which
      triggers the backup). Example, running the Laravel scheduler every minute
      (it fires the nightly backup at its scheduled time):
      ```
      * * * * * cd /home2/almadin1/public_html/ncat_fmd_app && php artisan schedule:run >> /dev/null 2>&1
      ```
      Adjust the path to your `DEPLOY_PATH`. Alternatively run the backup directly
      once a night:
      ```
      0 1 * * * cd /home2/almadin1/public_html/ncat_fmd_app && php artisan backup:run >> /dev/null 2>&1
      ```
- [ ] Run `php artisan backup:run` once by hand and confirm a dump appears in
      `storage/backups`.
- [ ] Also enable **cPanel-level weekly account backups** as a second line of
      defence, and note the restore procedure.

---

## Phase 9 — Final go-live smoke test

_Why: prove the four real workflows end-to-end before announcing go-live._

- [ ] Confirm `/up` health check is green and the site is HTTPS-only.
- [ ] **Storekeeper:** post a test SRV into Quarantine → **Stores Officer:**
      certify it into Bonded → **Storekeeper:** issue it on an SIV. Confirm the
      tally card reflects all three movements.
- [ ] **Engineer:** raise a Work Order → raise a Requisition → submit it.
- [ ] **Stores Officer:** approve the requisition from the queue.
- [ ] Print a **paper-exact PDF** of each voucher (SRV, SIV, Requisition, AD38
      tally) and check it against the original forms.
- [ ] Run each of the 5 **Reports** and export one to CSV and one to PDF.
- [ ] Confirm the **activity log** recorded the test actions against the right
      users.
- [ ] Delete/adjust any throwaway test data (use adjustment movements — the ledger
      is append-only, never edited).

**Go live.** ✅

# NCAT FMD Inventory — Go-Live Checklist (Project Lead)

An ordered checklist the Project Lead can follow **alone** to take the FMD
Inventory & Stores system live at https://office.ncatfmd.com.ng.

Work top to bottom — later phases assume earlier ones are done. Each phase has a
short **why**. Tick each box as you complete it.

> Prerequisites: the app is already deployed (CI/CD is green) and you can sign in
> as the seeded Super Admin (`superadmin@ncatfmd.com.ng`). If not, complete the
> server bootstrap in `README.md` first.

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
- [ ] Go to **Administration → Document counters** (`/admin/counters`).
- [ ] Set each counter's next value to the department-confirmed number and save.
- [ ] (Counters seed as provisional/unconfirmed; saving here marks them
      confirmed.)

---

## Phase 5 — Review each role's permissions

_Why: the seeded roles are sensible defaults, but the department should confirm
who can do what **before** real users are attached — especially segregation of
duties (the receiver must not be the certifier)._

- [ ] Go to **Administration → Roles & permissions** (`/admin/roles`).
- [ ] Review each starter role against the department's intent:
  - [ ] **Stores Officer** — certifies quarantine, approves requisitions, posts
        SIV, transfers/adjusts stock, posts fuel, manages parts.
  - [ ] **Storekeeper** — posts SRV and SIV, posts fuel; **must NOT** hold
        `quarantine.certify` (segregation of duties).
  - [ ] **Engineer/Technician** — raises Work Orders and Requisitions only.
  - [ ] **Viewer** — read-only.
  - [ ] **Super Admin** — leave as-is (bypasses all checks).
- [ ] Create any additional real roles the department needs.

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

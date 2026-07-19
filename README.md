# NCAT FMD — Inventory & Stores Management System

Web-based inventory and stores management for the **Flight Maintenance Department (FMD)**,
Nigerian College of Aviation Technology, Zaria. It digitises the department's paper workflow
end to end — Work Orders, Requisitions (one unit per voucher), Receiving (SRV), Issuing (SIV),
quarantine certification, fuel dump, and AD38 Tally Cards — across the fleet of **26 aircraft /
6 types** and the four physical stores (Quarantine, Bonded, Dope, Fuel Dump). It ships a
permission-managed multi-user dashboard, an immutable stock-movement ledger from which all
balances and analytics are derived, paper-exact printable PDFs, and CAMP-style alerts. **All
five build phases are delivered — the system is UAT-ready.**

- **Live target:** https://office.ncatfmd.com.ng
- **Design spec:** [`docs/superpowers/specs/2026-07-17-ncat-fmd-inventory-design.md`](docs/superpowers/specs/2026-07-17-ncat-fmd-inventory-design.md)
- **User guide:** [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) — role-oriented walkthrough of every module.
- **Go-live checklist:** [`docs/ADMIN_GO_LIVE_CHECKLIST.md`](docs/ADMIN_GO_LIVE_CHECKLIST.md) — the ordered steps to go live.

## Tech stack

- **Backend:** Laravel 12 (PHP 8.2+), MySQL (cPanel DB `almadin1_ncat`)
- **Frontend:** Inertia.js · React 18 · Tailwind CSS 3 (shadcn-idiom component kit) · Framer Motion · Recharts
- **Auth / RBAC / audit:** Laravel Breeze · spatie/laravel-permission · spatie/laravel-activitylog
- **PDF:** barryvdh/laravel-dompdf (branded, paper-exact vouchers)
- **Hosting / CI:** cPanel (SSH) · GitHub Actions → build → test-gate → tar-over-SSH deploy → migrate → cache → health check

## Build phases (all delivered)

| Phase | Scope | Status |
| --- | --- | --- |
| 0 | Scaffold, design system, auth, app shell, CI/CD | ✅ Delivered |
| 1 | Core data (types, 26 aircraft, ATA, stores) · Administration (users/roles/permissions/aircraft/stores/counters) · first-login password change | ✅ Delivered |
| 2 | Parts & stock engine: catalogue (AD38 fields), batches/serials, per-store movement ledger, tally views, transfers, adjustments, quarantine certification, fuel dump | ✅ Delivered |
| 3 | Documents: Work Orders (FMD numbering), Requisitions (paper-exact + removal), Receiving→Quarantine, SIV issuing, document counters | ✅ Delivered |
| 4 | Aircraft experience (fleet → registration → workspace), CAMP-style dashboard, Parts on Aircraft | ✅ Delivered |
| 5 | Paper-exact PDF vouchers, Reports & exports, notifications, a11y/perf/responsive polish | ✅ Delivered |

---

## Local development

**Requirements:** PHP 8.2+, Composer 2, Node 20+, npm.

```bash
composer install
npm ci                       # reproducible install from package-lock.json
cp .env.example .env
php artisan key:generate

# Build the schema and seed reference data + the Super Admin.
php artisan migrate --seed

# Run the test suite (in-memory sqlite — no MySQL needed).
php artisan test

# Dev servers — two terminals (or `composer run dev` if configured):
npm run dev
php artisan serve
```

> The committed `.env.example` points at the cPanel MySQL database
> (`almadin1_ncat`). Shared-cPanel MySQL is usually **not reachable from a dev
> machine**, so the test suite is configured (in `phpunit.xml`) to use
> in-memory sqlite. Against a local MySQL/sqlite dev DB, `migrate --seed` lands
> the full schema, the permission catalogue + starter roles, the four stores, the
> six aircraft types, the 26 aircraft, ATA chapters, document counters and the
> Super Admin. Real migrations run on the server via CI/CD.

**Seeded administrator** (created by `php artisan db:seed`):

| Field | Value |
| --- | --- |
| Email | `superadmin@ncatfmd.com.ng` |
| Password | `ChangeMe!NCAT2026` |

⚠️ **Change this password on first login.** The account is flagged
(`password_change_required = true`); first-login enforcement UI ships in Phase 1.

---

## Deployment (GitHub Actions → cPanel)

Pushing to `main` runs [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml):

1. Install PHP deps (with dev) → `npm ci` → `npm run build`
2. **Run the test suite — deploy is gated on green tests**
3. Reinstall production deps (`--no-dev --optimize-autoloader`)
4. Stream a **gzipped tar over SSH** and extract on the server (shared cPanel hosts usually lack `rsync`; excludes `.env`, `node_modules`, `.git`, runtime storage)
5. Remote: `php artisan migrate --force` → `optimize:clear` → `config:cache route:cache view:cache` → `storage:link`
6. `curl` health check on `https://office.ncatfmd.com.ng/up`

### ✅ Project Lead — manual setup checklist

#### 1. Add GitHub repository secrets
`Settings → Secrets and variables → Actions → New repository secret`:

| Secret | Value | Notes |
| --- | --- | --- |
| `SSH_HOST` | e.g. `ncatfmd.com.ng` or the server IP | cPanel/WHM SSH host |
| `SSH_USER` | your cPanel username | the account that owns the subdomain |
| `SSH_KEY` | **private** SSH key (full PEM, incl. BEGIN/END lines) | see step 2 |
| `DEPLOY_PATH` | absolute path to the app root on the server, e.g. `/home/almadin1/ncat_fmd_app` | **not** the docroot — see step 3 |

#### 2. Generate the SSH key in cPanel
`cPanel → Security → SSH Access → Manage SSH Keys`:
1. **Generate a new key** (ed25519 or RSA 2048+), no passphrase (CI can't type one).
2. **Authorize** the public key.
3. **View/Download the PRIVATE key** and paste its full contents into the `SSH_KEY` secret.
4. Ensure SSH access is enabled for the account (some hosts require a support request).

> Alternatively, generate locally with
> `ssh-keygen -t ed25519 -f ncat_deploy -C "gh-actions"`, then paste the public
> key into cPanel's *Authorized Keys* and the private key into `SSH_KEY`.

#### 3. Point the subdomain docroot at Laravel's `public/`  ← important
Laravel must serve from `public/`, never the app root.

> ⚠️ **Edit the `office.ncatfmd.com.ng` subdomain, NOT the main `ncatfmd.com.ng`
> domain.** Changing the main domain's document root breaks the main website.
> In `cPanel → Domains`, open the **`office.ncatfmd.com.ng`** row → *Manage*.

This account's home is `/home2/almadin1`, and its cPanel **locks the subdomain
document root to `~/public_html/`** (the New Document Root field has a fixed
`🏠/public_html/` prefix). So deploy the app under `public_html`:

**Recommended:**
- GitHub secret → `DEPLOY_PATH = /home2/almadin1/public_html/ncat_fmd_app`
- Subdomain **New Document Root** → `ncat_fmd_app/public`
  (resolves to `/public_html/ncat_fmd_app/public`), then **Update**.

If cPanel says the folder doesn't exist, run the GitHub deploy once first so
`ncat_fmd_app/public` exists, then set the doc root.

**Why it's safe:** the main domain serves the *subfolder* `/public_html/ncatfmd.com.ng`
and this subdomain serves `/public_html/ncat_fmd_app/public`. Nothing serves
`/public_html/` itself, so `/public_html/ncat_fmd_app/.env` and `vendor/` are not
reachable over HTTP.

**Alternative (app fully outside `public_html`, needs SSH):** keep
`DEPLOY_PATH = /home2/almadin1/ncat_fmd_app`, leave the doc-root field as-is, and
symlink the docroot to the app's public dir:
```bash
rm -rf ~/public_html/office.ncatfmd.com.ng
ln -s ~/ncat_fmd_app/public ~/public_html/office.ncatfmd.com.ng
```
Only use this if the host's `open_basedir` isn't scoped to the docroot (a fatal
`open_basedir`/`vendor/autoload.php not found` after deploy means it is — switch
to the recommended option).

#### 4. One-time server bootstrap (before the first deploy or on first run)
SSH into the server and, in `DEPLOY_PATH`:
```bash
cp .env.example .env
php artisan key:generate          # generate the production APP_KEY
# edit .env: set APP_ENV=production, APP_DEBUG=false, APP_URL, and DB_PASSWORD
php artisan migrate --force
php artisan db:seed --force        # creates the Super Admin (idempotent)
php artisan storage:link
```
Ensure `storage/` and `bootstrap/cache/` are writable by the web user.

#### 5. Rotate the database password
The DB password supplied for setup should be rotated in cPanel after go-live,
and the server `.env` updated (spec §8, §11).

### Production data seeding (Phase 1+)

All seeders are **idempotent** (`updateOrCreate` / `firstOrCreate`) and now run
automatically on every deploy (the pipeline runs `php artisan db:seed --force`
after `migrate`). Re-running never resets existing accounts or admin-adjusted
values. To seed manually on the server:

```bash
php artisan db:seed --force
```

This lands, in order: the permission catalogue + starter roles (Super Admin,
Stores Officer, Storekeeper, Engineer/Technician, Viewer), the four **stores**
(Quarantine, Bonded, Dope, Fuel Dump), the six aircraft **types**, the 26
**aircraft**, the **ATA** chapter list, the **document counters**, and the
initial Super Admin account.

**Document counters are provisional** (`confirmed = false`) and editable in
*Administration → Counters*. Seeded next-values (derived from the ground-truth
forms): Work Order **1344** (ledger latest was 1343), Requisition **1002**,
SIV **0294**, SRV **0202**. Confirm the exact continuation numbers with the
department before Phase 3 go-live.

---

## Security notes

- **No public self-registration** — accounts are admin-provisioned.
- `.env` and `docs.txt` are git-ignored and must never be committed. Credentials
  live only in server `.env` and GitHub secrets.
- Super Admin bypasses all ability checks via a `Gate::before` hook; granular
  spatie permissions and policies come online from Phase 1.
- All account changes are recorded via `spatie/activitylog`.

---

## Design system

NCAT brand tokens live in [`tailwind.config.js`](tailwind.config.js) (raw `ncat.*`
palette + CSS-variable semantic tokens in [`resources/css/app.css`](resources/css/app.css))
and the JS mirror [`resources/js/theme/tokens.js`](resources/js/theme/tokens.js).
Fonts (Plus Jakarta Sans + Inter) are **self-hosted** via `@fontsource` — no external
CDN calls. The component kit is in [`resources/js/Components/ui/`](resources/js/Components/ui/).

# NCAT FMD Inventory — Management Demo Runbook

A presentation script for the **Project Lead** to demo the FMD Inventory & Stores
system to NCAT management. Budget **~15–20 minutes**. Everything below is driven
by the seeded demo narrative (`php artisan demo:seed`) — a backdated ~10-week
story that lights **every** dashboard alert and produces a requisition in every
status.

> The demo data is disposable. It is seeded, shown, and purged. **Nothing in this
> runbook touches real records** — real operation has not started yet.

---

## Setup (do this before management arrives)

1. From the app root, seed the demo:
   ```
   php artisan demo:seed
   ```
   The command backdates ~10 weeks of movements and turns **demo mode ON**. If it
   refuses with *"Transactional data already exists (or a demo is active)"*, either
   a demo is already running or real data exists — run `php artisan demo:purge …`
   first (see Teardown), or pass `--force` only if you are certain it is safe to
   seed on top.

2. Open the app and sign in. Confirm the gold **"DEMO DATA"** banner is showing:
   a slim brand-gold bar reading **"Demo data — for presentation only"** pinned to
   the top of every authenticated page. If you do not see it, the seed did not
   activate demo mode — re-run `demo:seed`.

   > _Screenshot: gold "Demo data — for presentation only" banner across the top of the Dashboard_

3. Have the demo credentials to hand. All demo users share **one password** so you
   can role-switch quickly during the talk.

### Demo login credentials

Shared password (from `DemoSeeder::DEMO_PASSWORD`): **`DemoNCAT2026!`**
Email domain (from `DemoSeeder::DEMO_DOMAIN`): **`@demo.ncatfmd.local`**

| Name (as displayed) | Email | Role | Password |
|---|---|---|---|
| Femi Adewale (Demo) | `officer@demo.ncatfmd.local` | **Stores Officer** | `DemoNCAT2026!` |
| Grace Okoro (Demo) | `storeman@demo.ncatfmd.local` | **Storekeeper** | `DemoNCAT2026!` |
| Musa Ibrahim (Demo) | `engineer@demo.ncatfmd.local` | **Engineer/Technician** | `DemoNCAT2026!` |
| Ngozi Eze (Demo) | `viewer@demo.ncatfmd.local` | **Viewer** | `DemoNCAT2026!` |

**Who does what (this drives the "be this user" cues below):**
- **Stores Officer (Femi)** — the supervisor: approves requisitions, certifies
  quarantine, issues stock, posts fuel, runs reports. Your main presenter identity.
- **Storekeeper (Grace)** — the operator who receives and issues; **cannot** approve
  requisitions and **cannot** certify quarantine (segregation of duties). All the
  seeded SRVs/SIVs/requisitions were raised in her name, so her name appears on the
  paperwork.
- **Engineer/Technician (Musa)** — raises Work Orders and Requisitions only.
- **Viewer (Ngozi)** — read-only.

> Suggested identity: run the whole flow as **Femi Adewale (Stores Officer)**, and
> make the two deliberate role-switches below (to Grace) as a *feature* — they show
> management that the system enforces who-can-do-what.

---

## The flow

### 1 — Dashboard: the alert panel, fully lit

**Be:** Femi Adewale (Stores Officer). **Nav:** Dashboard (`dashboard`).

The seed is engineered so **every** CAMP-style alert category has at least one live
item. Walk the panel and name each one:

- **Below minimum** — *Concorde Battery RG-24* (`CM-2612`) driven down to its min level.
- **At/below reorder** — *Main Wheel Tyre, DA-40* (`505C61-8`) issued down to ≤ its reorder level.
- **Above maximum** — *Airframe Hardware Item 1* (`HW-0001`) overstocked at 320 vs a max of 200.
- **Expired shelf-life** — *Aeroshell W100 Oil* (`W100-1QT`) batch `OIL-2210`, expired ~2 months ago.
- **Expiring soon (≤90 days)** — *Oil Filter* (`CH48110-1`) batch `OF-2405` (~45 days) and *Fuel Tank Sealant* (`PR-1440-B2`).
- **Quarantine aging** — a sealant SRV sitting in the Quarantine store ~14 days awaiting certification.
- **Pending approvals** — **2** submitted requisitions waiting on an approver (badge on the Requisitions sidebar item).

Point out that these are **derived live from the ledger**, not hand-set — this is the
day-one operational picture the department currently reconstructs by hand from paper.

> _Screenshot: Dashboard alert panel with every category populated + the "approvals" badge on Requisitions_

### 2 — Aircraft fleet grid

**Be:** Femi Adewale. **Nav:** Aircraft Types (`aircraft-types`).

Show the fleet: 6 aircraft types as cards → drill into a type to see its
registrations. This is the aircraft-centric entry point the department thinks in.

> _Screenshot: aircraft-type cards / registrations grid_

### 3 — Open the 5N-CAK workspace

**Be:** Femi Adewale. **Nav:** open **5N-CAK** (`aircraft.show`, URL `/aircraft/5N-CAK`).

The workspace strips together everything about one tail number:

- **Work Orders** — 5N-CAK's **open** snag work order *"SNAG: R/H main wheel tyre
  worn out"* (FMD/… reference, auto-numbered). This is the story thread: worn tyre →
  work order → requisition for a replacement.
  > Other live work across the fleet for contrast: 5N-CZB (*ECU A failed* — in
  > progress), 5N-BZH *100 HRS inspection* (in progress), 5N-BZE *ANNUAL* (open),
  > and 5N-CAJ (*landing light* — closed) so a completed WO is visible too.
- **Parts on Aircraft** — the CAMP-style installed-parts view. 5N-CAK's transponder
  (`GTX-335`, serial `SN-GTX-OLD-01`) was removed to the Avionics Workshop and is
  now tracked as **at repair** (repair order `RO-2025-0087`) — full serial history,
  not just a quantity.

> _Screenshot: 5N-CAK workspace showing its work order tab and the Parts on Aircraft panel_

### 4 — Its requisition

**Be:** Femi Adewale. **Nav:** the Requisitions strip in the 5N-CAK workspace (or `requisitions.index` filtered to 5N-CAK).

5N-CAK carries a requisition in **every status**, which is ideal for showing the
lifecycle on one aircraft:
- **draft** — Main Wheel Tyre (`505C61-8`)
- **submitted** — Nose Wheel Tyre (`5.00-5`) ← we approve this one next
- **approved (awaiting issue)** — Brake Pad Set (`066-10500`)
- **issued + removal completed** — Transponder (`GTX-335`), with the technician's
  removal section filled in (removed serial, zone, reason, repair facility).

Open the **submitted** nose-wheel-tyre requisition and show the paper-exact layout:
one part per voucher, aircraft/position, part & stock code, and the approval section
still blank.

> _Screenshot: submitted requisition detail (single-unit voucher, approval section empty)_

### 5 — Role-switch to the Stores Officer to approve

**Point to make: the system enforces segregation of duties.**

- *(optional, ~20 s)* Log in as **Grace Okoro (Storekeeper)** and open the same
  requisition — there is **no Approve control**. A storekeeper receives and issues
  but may not approve.
- Log back in as **Femi Adewale (Stores Officer)**. **Nav:** Requisitions
  (`requisitions.index`) → the approval queue (the "approvals" badge). Open the
  submitted nose-wheel-tyre requisition and **Approve** it (`requisitions.approve`).
  Its status flips to **approved**, and the pending-approvals count on the Dashboard
  drops.

> _Screenshot: requisition approval queue and the Approve action / confirmed "approved" state_

### 6 — Issue it on an SIV (with FEFO for shelf-life)

**Be:** Femi Adewale (Stores Officer) — *note both Stores Officer and Storekeeper
hold `issues.post`, so Grace could do this too.* **Nav:** Issuing → new SIV
(`issuing.create`).

Create a Store Issue Voucher from the **Bonded** store. Add a **shelf-life** line —
*Aeroshell W100 Oil* (`W100-1QT`) — and point out that the system selects the batch
by **earliest expiry first (FEFO)** for shelf-life parts, so the oldest stock leaves
first. Post it (`issuing.post`); it writes an OUT movement on the ledger.

> _Screenshot: SIV line for a shelf-life part with the FEFO-selected batch_

### 7 — Open the Tally card to show the movement

**Be:** Femi Adewale. **Nav:** Tally Cards (`tally-cards.index`) → the part you just issued.

The AD38-layout tally card shows the **brought-forward** balance, each voucher line
(including the SIV you just posted), running balance, and **carried-forward** total,
with MAX/MIN/REORDER shown — exactly the paper card, kept automatically.

Then flip to the **consolidated** view (all stores) to show the same part across
every store in one card. Emphasise: the ledger is **append-only** — corrections are
adjustment movements, never edits.

> _Screenshot: AD38 tally card with the new issue line, then the consolidated (all-stores) view_

### 8 — Stores → certify a quarantined item (role-switch to a certifier)

**Point to make again: receiver ≠ certifier.** The storekeeper *received* the SRV
into Quarantine but **cannot certify** it (no `quarantine.certify`). Certification is
the Stores Officer's job.

**Be:** Femi Adewale (Stores Officer — the certifier). **Nav:** Stores
(`stores.index`) → **Quarantine** store → the certification queue.

Certify the aging *Fuel Tank Sealant* (`PR-1440-B2`) receipt. Because the part is
**flammable**, certification **releases it to the Dope store** (not Bonded) — the
system routes it correctly. Posting creates a paired transfer out of Quarantine, and
the quarantine-aging alert clears.

> _Screenshot: Quarantine certification queue → "release to Dope" for the flammable sealant_

### 9 — Fuel Dump: issue fuel to an aircraft

**Be:** Femi Adewale (Stores Officer — holds `fuel.post`; Storekeeper does too).
**Nav:** Stores → **Fuel Dump** (`fuel.index`).

Use the simplified fuel UI to **issue** fuel in litres to a tail number — e.g.
Jet A-1 to **5N-CAK** for a training sortie (`fuel.issue`). Same ledger underneath,
but no certification step for fuel. Show the fuel balance drop.

> _Screenshot: Fuel Dump issue form (litres → aircraft) and the updated fuel balance_

### 10 — Reports: run one and export

**Be:** Femi Adewale (Stores Officer — holds `reports.view`; the Storekeeper does
**not**, so the Reports module is hidden for her). **Nav:** Reports (`reports`).

Run a report — e.g. **Quarantine Aging** or **Stock Summary (per store)** — then
**export to CSV** and **export to PDF** (`reports.export`). This is the analytics
layer management gets for free on top of the day-to-day workflow.

> _Screenshot: a report on screen with the CSV / PDF export buttons_

### 11 — Print a voucher PDF (paper-exact)

**Be:** Femi Adewale. **Nav:** open the requisition you approved (or the SIV you
posted) → **Print / PDF** (`requisitions.pdf` / `issuing.pdf`).

The PDF reproduces the department's real form field-for-field with NCAT branding and
blank wet-ink signature lines — a stores clerk can print and file it exactly like the
paper form today. Because **demo mode is active**, every generated voucher carries a
light diagonal **"DEMO"** watermark across the page (and the gold on-screen banner is
the second tell), so a demo printout can never be mistaken for — or filed as — a real
document. Close by reminding management that *these vouchers are demo copies* and will
be wiped in teardown.

> _Screenshot: paper-exact requisition/SIV PDF (note the demo indicator)_

---

## Teardown (run after the demo, before anyone enters real data)

The demo is **fully disposable**. Purge empties every transactional table and returns
the system to a clean pre-launch state.

**Premise — read this out loud to yourself before running it:** `demo:purge` does a
**full truncate** of all transactional data. That is safe **only because real
operation has not started**. The day real records exist, this command must never be
run again.

### Interactive (recommended — you are at the console)

```
php artisan demo:purge --i-understand-this-deletes-all-transactional-data
```

The command first warns you, then **asks you to type the application name** (the
configured `APP_NAME`) to confirm. Type it exactly; any mismatch aborts with nothing
deleted.

### Scripted / non-interactive (documented automated use)

```
php artisan demo:purge --i-understand-this-deletes-all-transactional-data --no-interaction-confirmed
```

`--no-interaction-confirmed` skips the typed app-name prompt. Use only in scripts.

> Omitting `--i-understand-this-deletes-all-transactional-data` is refused outright —
> the acknowledgement flag is mandatory either way.

### What purge does (in order)

1. **Backs up the database first** — if the backup fails, the purge **aborts** and
   nothing is deleted.
2. **Truncates all transactional tables** in child→parent order (notifications,
   activity log, stock movements/balances, SIV/SRV items + headers, requisitions,
   work orders, part serials/batches, parts).
3. **Deletes the demo users** (only accounts flagged `is_demo` — never real users).
4. **Restores the document counters** to their pre-demo starting numbers and marks
   them **unconfirmed**.
5. **Clears the demo flag** — demo mode goes OFF.

### The verification report

The command prints three tables:
- **Transactional tables — must all read 0.** If any row is non-zero, the command
  reports *"Purge verification FAILED"* and exits non-zero — do not proceed to
  go-live; investigate.
- **Preserved reference data** — users, roles, aircraft, stores, ATA chapters,
  document counters — should still be populated. Real reference data is untouched.
- **Document counters — restored, with `Confirmed = no`.** The starting numbers are
  back to their pre-demo values but flagged **unconfirmed**, which is your prompt to
  re-confirm the real next-numbers with the department before go-live.

After a clean report: the gold demo banner is **gone**, and the system is back to a
clean pre-launch state. Proceed with the go-live checklist (`ADMIN_GO_LIVE_CHECKLIST.md`).

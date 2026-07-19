# NCAT FMD Inventory & Stores — User Guide

A role-oriented walkthrough of the Flight Maintenance Department (FMD) Inventory &
Stores system for the Nigerian College of Aviation Technology, Zaria.

- **Live site:** https://office.ncatfmd.com.ng
- **Sign in:** open the site and enter your email and password. Accounts are
  created by an administrator — **there is no self-registration.** On your very
  first sign-in you will be asked to set a new password before you can continue.

> _Screenshot: the NCAT-branded login page (split-panel art)._

---

## 1. Orientation — what you see after signing in

Every screen shares the same shell:

- **Sidebar** (left) — the modules you are permitted to use. Grouped into
  **Overview**, **Operations**, **Catalogue** and **System**. You only see a
  module if your role grants the matching permission, so two people may see
  different menus.
- **Top bar** — the **Global search** box, the **notifications bell**, and your
  **profile menu** (account settings, sign out).
- **Content area** — the current module.

The sidebar modules, in order, are: **Dashboard · Aircraft Types · Stores · Work
Orders · Requisitions · Receiving · Issuing · Tally Cards · Parts · Opening
Balances · Reports · Administration.**

> _Screenshot: the app shell — sidebar, top bar, dashboard._

### Global search (⌘K / Ctrl-K)

Press **⌘K** (Mac) or **Ctrl-K** (Windows) from anywhere to jump into the search
box in the top bar, or just click it. Type at least two characters to search
across **parts, aircraft, work orders, requisitions, receiving (SRV) and issuing
(SIV)**. Results are grouped by type and are permission-filtered — you only see
what you are allowed to open. Use the arrow keys to move and **Enter** to open;
your five most recent searches are remembered.

> _Screenshot: the ⌘K global-search dropdown with grouped results._

### Notifications bell

The bell surfaces the same live alerts as the dashboard — items below reorder or
minimum, above maximum, expired/expiring stock, items awaiting certification,
requisitions pending approval and open work orders — filtered to the ones **you**
can act on. Click through to the pre-filtered screen.

> _Screenshot: the notifications bell dropdown._

---

## 2. The Dashboard (everyone)

The Dashboard is the command centre. It is available to every signed-in user; the
cards adapt to your permissions.

- **Alert panel** — coloured cards, each showing a live count and drilling into a
  pre-filtered list. The cards are: **Below Reorder, Below Minimum, Above
  Maximum, Expired, Expiring ≤90d, Awaiting Certification, Pending Approval,
  Open Work Orders.** You only see a card if you hold the permission to act on it
  (e.g. *Awaiting Certification* shows only to certifiers, *Pending Approval*
  only to approvers).
- **KPI tiles** — **Distinct Parts**, **Stock Value (₦)** (on-hand value where a
  unit price is known, fuel excluded), **Open Work Orders**, and **Fuel (litres)**
  on hand.
- **Charts** — **Stock movement trend** (in vs out, last 12 weeks), **Consumption
  by aircraft type**, and **Receiving vs Issuing** (last 12 months). Powered by
  Recharts.
- **Recent activity** — the latest audit-log lines you are permitted to see.

> _Screenshot: the dashboard with alert cards, KPI tiles and charts._

---

## 3. The Aircraft experience (Aircraft Types)

Open **Aircraft Types** in the sidebar (requires `aircraft.view`).

1. **Fleet grid** — the six aircraft **types** as cards (BARON-58, DA-40NG,
   DA-42NG, TB-9, TB-20, TBM-850).
2. Choosing a type reveals its **registrations** (the 26 airframes).
3. Selecting a registration opens the **Aircraft Workspace** at
   `/aircraft/{registration}` (e.g. `/aircraft/5N-XYZ`) — a clean, memorable URL.

The workspace has four tabs:

- **Parts on Aircraft** — the CAMP-style view of serialised parts currently
  installed on this airframe, with serial number, position/zone and install date.
  Parts land here automatically when a serialised part is issued (SIV) to the
  aircraft.
- **Work Orders** — snags and scheduled inspections raised for this aircraft.
- **Requisitions** — spare-part requisitions raised for this aircraft.
- **Issues (SIV)** — Store Issue Vouchers whose lines reference this aircraft.

> _Screenshot: the aircraft workspace with the Parts on Aircraft tab open._

---

## 4. Storekeeper workflow — Receiving → Certification → Issuing

The Storekeeper receives stock into Quarantine, and issues certified stock out of
Bonded/Dope. **Note the segregation of duties:** a Storekeeper receives but does
**not** certify — certification is a Stores Officer action (receiver ≠ certifier).

### 4a. Receiving — Store Receipt Voucher (SRV) → Quarantine

Sidebar: **Receiving** (`receiving.view`; posting requires `receiving.post`).

1. Open **Receiving** and click **New SRV** (`Receiving → Create`).
2. Fill the SRV header, mirroring the paper Store Receipt Voucher:
   **destination store** (defaults to **Quarantine**; **Fuel Dump** for fuel),
   date, **LPO / petty-cash reference**, supplier, head of receiving department,
   and storekeeper.
3. Add item lines: quantity (in figures — the words are generated on the PDF),
   part, folio no., rate, amount (₦), invoice no., account code, and where
   relevant batch / serial / expiry.
4. **Save** to keep it as a draft, then **Post** the SRV. Posting writes
   **IN** movements into the destination store (movement type `receiving`).

> ⚠️ Received stock lands in **Quarantine** and is **not issuable** until it is
> certified. Fuel received into the **Fuel Dump** needs no certification step.

> _Screenshot: the SRV entry form with item lines and destination store._

### 4b. Certification — release Quarantine → Bonded / Dope

Sidebar: **Stores** (`stores.view`). The certify action requires
`quarantine.certify` — held by the **Stores Officer**, not the Storekeeper.

1. Open **Stores** and go to the **Quarantine** store card / certification queue.
   (The Dashboard *Awaiting Certification* card links straight here.)
2. Review the quarantined receipt.
3. Choose a decision: **Release to Bonded** (default serviceable store),
   **Release to Dope** (automatically for flammable parts), or **Reject / return
   to supplier** (documented).
4. Confirming posts **paired transfer movements** (out of Quarantine, into
   Bonded/Dope — movement type `certification_transfer`). Only certified stock
   becomes issuable.

> _Screenshot: the Stores module with the Quarantine certification queue._

### 4c. Issuing — Store Issue Voucher (SIV)

Sidebar: **Issuing** (`issues.view`; posting requires `issues.post`).

1. Open **Issuing** and click **New SIV** (`Issuing → Create`).
2. Fill the SIV header (requisition-for, ordered-by, school/section,
   approved-by, entered-by) mirroring the paper Store Issue Voucher.
3. Add item lines — each line may **reference an approved requisition** (you can
   bundle several onto one SIV) or be a **standalone consumable issue**. Capture
   part, description, quantity required (figures — words on the PDF), quantity
   issued, stores folio, rate, amount and charging code.
4. **Post** the SIV. Posting writes **OUT** movements from the issuing store
   (Bonded/Dope, movement type `issue`). For shelf-life parts the earliest-expiry
   batch is preferred (FEFO).

> _Screenshot: the SIV entry form with lines referencing approved requisitions._

### Fuel Dump (simplified)

Sidebar: **Stores → Fuel** (`stores.view`; posting requires `fuel.post`). Fuel is
received into and issued from the **Fuel Dump** in litres, to a specific aircraft,
on a dedicated simplified screen — same ledger, no certification step
(movement types `fuel_receive` / `fuel_issue`).

> _Screenshot: the fuel dump receive/issue screen._

---

## 5. Engineer / Technician workflow — Work Order → Requisition → Removal

The Engineer/Technician raises the paperwork that drives a part change.

### 5a. Raise a Work Order

Sidebar: **Work Orders** (`work_orders.view`; creating requires
`work_orders.create`).

1. Open **Work Orders** and click **New Work Order**.
2. Pick the **aircraft**, the **work type** (**Snag**, **Scheduled Inspection**,
   or **Other**), and enter the title/description (the snag text, or the
   inspection name such as "100 HRS INSPECTION").
3. Save. The system assigns the FMD reference automatically in the ledger format
   **`FMD/{TYPE}/{MM}/{YY}/{serial}`** (a global running serial that continues the
   department's paper sequence). Status moves through **Open → In Progress →
   Closed**.

> _Screenshot: the work-order register (ledger-style) and the new-WO form._

### 5b. Raise a Requisition (one unit per voucher)

Sidebar: **Requisitions** (`requisitions.view`; creating requires
`requisitions.create`).

1. Open **Requisitions** and click **New Requisition** (`Requisitions → Create`).
2. Complete the Aircraft Spare Parts Requisition Sheet — **one part / one unit per
   voucher**: date, required-for, aircraft, engine serial no., position,
   authorised-by, supply source, the part (with full description and part no.),
   stock code, serial number, batch no. + year, bin/balance line, and an optional
   **link to a Work Order**.
3. **Submit** the requisition. It moves **Draft → Submitted**, entering the
   approval queue. (See §6.)

> _Screenshot: the requisition voucher form (single unit)._

### 5c. Complete the Removal section

After the storeman has issued the serviceable unit, the technician completes the
**removal section** on the requisition (`requisitions.removal`):

1. Open the requisition and go to **Removal**.
2. Record the **serial removed**, zone, unit-changed-by, **reason for removal**,
   signature/date, and — if the part goes for repair — repair facility/workshop,
   date sent, and repair order reference.
3. Saving updates the removed serial's status (e.g. **removed-unserviceable** or
   **at-repair**) and closes out the voucher.

> _Screenshot: the requisition removal section._

---

## 6. Approver workflow — Requisitions approval queue (Stores Officer)

Requires `requisitions.approve`.

1. Open **Requisitions**. Filter to **Submitted** (the Dashboard *Pending
   Approval* card links straight to this filtered queue).
2. Open a submitted requisition and review it.
3. **Approve** or **Reject** (`requisitions.approve` / `requisitions.reject`).
   Approved requisitions become available for the storeman to issue against via
   an SIV; the status flows **Submitted → Approved → Issued → Closed**.

> _Screenshot: the requisitions approval queue filtered to Submitted._

---

## 7. Tally Cards

Sidebar: **Tally Cards** (`tally.view`).

A tally card is the per-part ledger view in the department's **AD38** layout,
showing brought-forward (B/F) and carried-forward (C/F) balances, the voucher
number on each line, and the MAX / MIN / REORDER levels.

1. Open **Tally Cards** and pick a part.
2. View the card **per store** (Quarantine, Bonded, Dope, Fuel Dump) or the
   **consolidated** card across all stores.
3. Click **Print / PDF** to produce the paper-exact AD38 card
   (`tally-cards/{part}/pdf`).

> _Screenshot: an AD38 tally card, per-store and consolidated._

---

## 8. Parts catalogue & Opening Balances

### Parts

Sidebar: **Parts** (`parts.view`; editing requires `parts.manage`).

The catalogue holds every part with its full AD38 fields — part number,
description, ATA chapter, aircraft type (or cross-type consumable), stock code,
ledger folio, unit of issue, unit price (₦), bin location, **min / max / reorder
levels**, and flags (serialised, shelf-life, flammable, fuel). Filter by ATA
chapter, drill into per-store balances and batch/serial history. The Dashboard
alert cards deep-link into this list pre-filtered (e.g. `state=below_reorder`).

> _Screenshot: the parts catalogue with ATA filter and per-store balances._

### Opening Balances (CSV import)

Sidebar: **Opening Balances** (`stock.adjust`). Used once, at go-live, to digitise
the existing paper tally cards.

1. Open **Opening Balances** and download the **CSV template**.
2. Fill in each part's opening quantity per store.
3. **Preview** the file (validation runs first), then **Import**. Imported rows
   post `opening_balance` movements so the ledger starts from a known baseline.

> _Screenshot: the opening-balances CSV preview and import._

---

## 9. Reports (5 reports · PDF / CSV export)

Sidebar: **Reports** (`reports.view`).

Five filterable reports are available:

1. **Stock Summary** — on-hand balances (per store).
2. **Movement Register** — the stock-movement ledger.
3. **Expiry Report** — expired and expiring shelf-life batches.
4. **Per-Aircraft Consumption** — issued quantity by aircraft type.
5. **Quarantine Aging** — how long items have sat awaiting certification.

Each report screen offers filters (store, part, aircraft type, user, date range,
state, etc.). Every report **exports** to:

- **CSV** — streams the full filtered set (UTF-8 with BOM so Excel renders ₦
  correctly).
- **PDF** — an A4 landscape, NCAT-branded document (capped for very large sets;
  pull those as CSV).

> _Screenshot: a report screen with filters and the PDF/CSV export buttons._

---

## 10. Printing paper-exact PDFs

Every voucher prints as a branded, paper-exact PDF that mirrors the department's
forms field-for-field. Open the document and choose **Print / PDF**:

- **Requisition Sheet** — `requisitions/{id}/pdf`
- **Store Receipt Voucher (SRV)** — `receiving/{id}/pdf`
- **Store Issue Voucher (SIV)** — `issuing/{id}/pdf`
- **Tally Card (AD38)** — `tally-cards/{part}/pdf`
- **Reports** — `reports/{report}/export?format=pdf`

Figures-to-words on the SRV/SIV are generated automatically on the PDF.

> _Screenshot: a paper-exact SRV/SIV/Requisition PDF alongside the original form._

---

## 11. Administration (Super Admin)

Sidebar: **Administration** (visible with any of `users.view`, `roles.view`,
`aircraft.view`, `stores.view`, `ata.view`, `counters.view`, `audit.view`). The
**Super Admin** bypasses all permission checks.

From the Administration dashboard (`/admin`):

- **Users** (`users.view` / `users.manage`) — create users, assign roles, reset
  passwords. New users are flagged for a forced password change on first login.
- **Roles & permissions** (`roles.view` / `roles.manage`) — the grouped
  permission matrix. Starter roles: **Super Admin, Stores Officer, Storekeeper,
  Engineer/Technician, Viewer**. Roles are fully editable and new roles can be
  created.
- **Fleet — aircraft & types** (`aircraft.view` / `aircraft.manage`) — manage the
  26 aircraft and 6 types.
- **Stores** (`stores.view` / `stores.manage`) — manage the four physical stores;
  extensible.
- **ATA chapters** (`ata.view` / `ata.manage`) — the ATA-100 chapter list.
- **Document counters** (`counters.view` / `counters.manage`) — the running
  numbers for **Work Orders, Requisitions, SIV and SRV**. Set the starting values
  here to continue the paper sequences. (See the go-live checklist.)
- **Activity log** (`audit.view`) — the full audit trail (spatie/activitylog);
  logins/logouts and every mutation are recorded.

> _Screenshot: the Administration area — roles/permissions matrix and document
> counters._

---

## Appendix — Roles at a glance

| Role | What they do here |
| --- | --- |
| **Super Admin** | Everything, including all Administration. Bypasses permission checks. |
| **Stores Officer** | Certifies quarantine, transfers/adjusts stock, posts fuel, approves requisitions, posts SIV, manages parts, views reports. |
| **Storekeeper** | Posts Receiving (SRV) and Issuing (SIV), posts fuel, views stores/stock/parts/tally. **Does not certify** (segregation of duties). |
| **Engineer/Technician** | Raises Work Orders and Requisitions, completes removals, views stock/parts/tally/aircraft. |
| **Viewer** | Read-only across stores, documents, parts, tally and reports. |

_Permissions are managed live in Administration → Roles; the table above reflects
the seeded defaults._

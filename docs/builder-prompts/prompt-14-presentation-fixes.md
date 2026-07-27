# Builder Prompt #14 — Presentation Fixes and Demo Data Expansion

**From:** CTO
**Urgency:** the Project Lead presents to NCAT management today. This is one pass, and it goes straight to production.
**Push instruction:** commit and push **directly to `main`**. Do not create a branch and do not open a PR. The workflow on `main` runs the test job first and the deploy job only runs if it passes, so the gate still applies; you simply lose the ability to abandon a branch if it fails. If the push is rejected, that is branch protection and the Project Lead will clear it. Watch the run to completion and confirm on the live site.

## 1 and 2. Remove the grid overlay, permanently

The faint grid producing boxes across the login artwork and the fleet cards goes. It is the `bg-grid-faint` utility, used in three places:

- `resources/js/Layouts/AuthLayout.jsx:29` — login left panel, at `opacity-[0.10]`, 44px cells
- `resources/js/Components/aircraft/FleetCard.jsx:38` — aircraft type cards, at `opacity-[0.15]`, 22px cells
- `resources/js/Pages/Aircraft/Workspace.jsx` — the aircraft workspace, **not named by the Project Lead but included deliberately**, because leaving it on one surface after removing it from the other two is worse than either choice. Flag it in your report so it can be reverted if they disagree.

Delete the overlay elements, and delete the `grid-faint` definition in `tailwind.config.js:128` so it cannot be reintroduced by accident. "Permanently" was the word used.

Check what each overlay was doing for the composition before you pull it. On the login panel it sits over a photographic background alongside a gradient scrim; if removing it leaves the headline or the NCAT lockup sitting on a brighter patch of sky and losing contrast, adjust the scrim to hold legibility. Do not replace the grid with another texture. Report the measured contrast of the headline and body copy over the image afterwards.

## 3. Shipment timeline icons overlap the text

The stage icons sit on top of the entry text instead of in the rail gutter.

You changed this in Phase 11, from `left-0` to `-left-9`, on the reasoning that a badge at `left-0` inside a container carrying `pl-9` lands on the text. Be aware of the trap in that reasoning: an absolutely positioned element resolves `left` against its nearest positioned ancestor's **padding box**, not its content edge. Which element is actually establishing the containing block for `Node` decides whether `-left-9` puts the badge in the gutter or pushes it a full 36px outside the container. The constants are `RAIL_BADGE_LEFT` and `RAIL_LINE_LEFT` in `resources/js/Components/logistics/ShipmentTimeline.jsx`, with the rail gutter created by `Rail`'s `pl-9`.

Fix it by looking at rendered pixels, not by reasoning about classes. This is the second attempt at the same few lines, and the first one passed review while still being wrong on screen. Verify at 1440, 768 and 390 pixels wide, on a shipment with several events, on the ghost node for an overdue shipment, and on the arrival node with its check icon. Confirm the connector line runs through the badge centres and that no icon touches the text column at any width.

## 4. Expand the demo data for the presentation

The new modules are seeded too thinly to demonstrate. Vendors has two rows, purchase orders one, repair orders one, shipments two, loans two. A list with one row does not show management a working module, and the filters have nothing to filter.

Raise the volume so every new module looks like a system in use. Targets, adjust with judgement:

- **Vendors, 10 to 12.** Mix of `supplier`, `repair_organization` and `both`, local and international. Use the two that appear on their own paper forms (Diamond Aircraft Industries GmbH in Austria, Brinkley Aerospace in England) plus plausible others, with full address blocks so the order PDFs render properly.
- **Purchase orders, 8.** Cover every status: draft, issued, partially received, received, closed, cancelled. At least one partially received against a real SRV so the receiving linkage is visible.
- **Repair orders, 5.** Cover draft, issued, at vendor, returned, closed. Keep the existing one tied to the at-repair serial, since that demonstrates the removal loop.
- **Shipments, 6.** Varied timeline stages, at least one overdue with its ghost node, at least two arrived with the SRVs they produced, one closed. **Include at least one event carrying an attachment**, generated at seed time, so the Phase 10 attachment feature is visible rather than merely present.
- **Loans, 6.** Both directions, mixing on-loan, overdue and returned. Keep the borrowed transponder fitted to 5N-CAK, since it demonstrates the loaned-property marking.
- **Approval workflow.** Configure a second approval level so the chain management asked for is visible as a chain rather than a single step, and leave at least two requisitions mid-chain, approved at level one and pending at level two. **Only do this if `demo:purge` removes the added level and restores the single seeded default**, proven by a test. If that cannot be done cleanly in the time available, skip it and say so.

Keep the narrative coherent rather than random: these are the same six aircraft types, the same parts catalogue and the same vendors throughout, and the dates should sit sensibly relative to each other on the existing backdated timeline.

**The purge guarantee is not negotiable.** Every new row must be removed by `demo:purge`, new vendors carry the demo marker, the zero-count report covers everything, and the seed to purge to seed cycle stays green. The Project Lead purges this data after the presentation and starts the department on an empty database, so a single surviving demo row is a real problem.

## Verification before you push

- Full suite green locally. It will run again on `main`, but a red test after a direct push leaves `main` broken with no branch to abandon, and the Project Lead is presenting today.
- Browser-verify: the login page with no grid and legible headline, the aircraft types page with no grid, a shipment timeline with correct icon placement at three widths, and each new module's list page looking populated.
- Run `demo:purge` locally after seeding, read the zero-count output, then seed again.

## Report back

Short report: what you removed and whether the login composition needed compensating, what was actually wrong with the timeline geometry this time, the seeded volumes per module, whether the approval level was included, and confirmation that the purge cycle is clean. Note the Workspace grid removal explicitly so it can be reverted if the Project Lead wants it kept.

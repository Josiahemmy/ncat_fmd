# Open tickets

Things that are real, understood, and deliberately not being fixed in the
current phase. Each one records what was measured, who can actually act on it,
and what would settle the open question.

Raised: 26 July 2026 (Phase 10 pre-UAT closeout), extended in Phase 11.

---

## T-1 — Login document TTFB is ~1.3 s on production

**Status:** open, not blocking UAT
**Owner:** shared-hosting configuration, with a smaller application-side share
**Measured:** 26 July 2026, from Nigeria, against `https://office.ncatfmd.com.ng`

Measured with `curl -w`, three requests chosen to separate the layers:

| Request | What it exercises | TTFB |
|---|---|---|
| `/build/assets/app-*.css` | network + Apache only, no PHP | 666 ms |
| `/up` | network + Apache + Laravel boot | 754 ms |
| `/login` | the above + full Inertia page render | 1,296 ms |

Connection setup on the `/login` request: DNS 4 ms, TCP connect 208 ms, TLS
complete at 416 ms.

Reading the layers apart:

- **~416 ms is connection setup** (DNS, TCP, TLS) before a single byte of
  request is sent. That is distance and the host's TLS termination. The
  application cannot influence it.
- **~250 ms is Apache producing a static byte** after the connection exists
  (666 − 416). Also below the application.
- **~88 ms is Laravel booting** (754 − 666). That is a small number, and it is
  the part of this measurement that bears on the OPcache question below.
- **~542 ms is rendering the login page** (1,296 − 754). This is the only slice
  the application owns, and it is roughly 40% of the total.

So about two thirds of the latency sits beneath the application. A separate
earlier reading recorded 1,890 ms and a repeat during this session recorded
4,258 ms from a different client, so the figure is variable, which is itself
consistent with a contended shared host.

**Next step if picked up:** profile what the login page renders server-side
before optimising anything. The 542 ms application slice is worth understanding,
but the 666 ms floor beneath it caps how much any application change can win.

---

## T-2 — Responses are served over HTTP/1.1, not HTTP/2

**Status:** open, not blocking UAT
**Owner:** the host. This is not an application setting.

Every response negotiated `HTTP/1.1` (`curl -w '%{http_version}'` returned
`1.1` for the document, a static asset, and `/up`).

The response headers confirm the server generated an HTTP/1.1 response rather
than the client merely requesting one:

```
Server: Apache
Connection: Keep-Alive
Keep-Alive: timeout=2, max=100
Transfer-Encoding: chunked
```

`Connection` and `Keep-Alive` are hop-by-hop headers, and HTTP/2 forbids them.
Their presence in the response means the response was framed as HTTP/1.1.

**Caveat, stated plainly:** the clients available on the machine used for this
check could not negotiate HTTP/2 at all (`curl: option --http2: the installed
libcurl version does not support this`, and PowerShell 5.1's client is
HTTP/1.1-only). So this proves the connections *used* were HTTP/1.1; it does
**not** prove the server would refuse HTTP/2 if a capable client asked.

**What would settle it:** one request from a client with ALPN support, e.g.
`curl --http2 -I https://office.ncatfmd.com.ng/up` from any modern machine, or
the Network panel in browser devtools with the Protocol column shown.

**Who can fix it:** the host. `Server: Apache` on cPanel means HTTP/2 depends on
`mod_http2` being enabled account-wide or server-wide. That is a support-ticket
item for the hosting provider, not a change anyone can make in this repository.
It matters because the login document pulls 13 preloaded assets (see the `Link:`
preload header), and HTTP/1.1 serialises those across a small number of
connections.

---

## T-3 — Whether OPcache is enabled could not be determined from response headers

**Status:** open question, cheap to answer on the server
**Owner:** whoever has cPanel or SSH access

The response headers do not disclose OPcache state. The only header touching
PHP is `X-Powered-By: PHP/8.2.32`, which reports the version and nothing about
the opcode cache.

**The weak signal available:** `/up` cost only ~88 ms more than a static file on
the same host. A Laravel application recompiling every PHP file on each request
normally costs considerably more than that on shared hosting, so this is *mildly
consistent* with OPcache being on. That is an inference from one number, not
evidence, and it should not be reported as a finding either way.

**What would settle it, in one command on the server:**

```
php -i | grep -i opcache.enable
```

or, from cPanel, **Select PHP Version → Extensions** and check whether
`opcache` is ticked, then **Options** for `opcache.enable`.

**Why it is worth answering:** it determines who owns T-1's application slice.
If OPcache is off, turning it on is a host-level toggle that would cut the
Laravel boot cost across every request, and it is free. If it is already on,
the 542 ms render slice is genuine application work and needs profiling instead.

---

## T-4 — Brand cyan does not clear 3:1 against the Silver border

**Status:** open, accepted deliberately, not blocking UAT
**Owner:** a design decision for the Project Lead and NCAT's brand owner
**Measured:** 26 July 2026 (Phase 11), against the shipped values

Phase 11 moved the 3:1 brand cyan from `#009DE0` to **`#008BC7`** (`198 100% 39%`)
so it clears 3:1 on every surface it is drawn **on**:

| Surface | `#009DE0` | `#008BC7` (shipped) |
|---|---|---|
| Card / popover `#FFFFFF` | 3.04:1 | 3.81:1 |
| Page background `#F8FAFC` | 2.91:1 | 3.64:1 |
| Muted `#F1F5F9` | 2.78:1 | 3.47:1 |
| Accent hover `#EAF5FB` | 2.75:1 | 3.43:1 |

**What is still short.** Against the Silver border and input colour
`#D9DFE7` (`--border` / `--input`, `218 23% 88%`), `#008BC7` measures
**2.84:1**, under the 3:1 bar.

This matters where cyan sits directly against that colour rather than against a
page or card: a focus ring drawn around an input whose border is Silver, and an
icon placed on a bordered control. WCAG 1.4.11 asks for 3:1 against *adjacent*
colours, not only against the backdrop, so this is a real shortfall rather than
a technicality.

**Correction to the Phase 11 report.** That report gave this as 2.60:1. That
figure was the row for `#0092D1` (41% lightness), a candidate that was not
shipped. The correct measurement for the value actually in the codebase is
2.84:1. The conclusion does not change; the number does.

**Why it was accepted rather than fixed.** Clearing 3:1 against Silver needs
the cyan at 37% lightness (`#0084BD`, 3.11:1) and, for any real margin, 35%
(`#007DB3`, 3.42:1). The 4.5:1 primary is `#006B99` at 30%. The contrast
*between* those two colours at that point is 1.29:1 at 35% and 1.22:1 at 34%,
which is to say they stop being distinguishable. The palette carries two
deliberately separate tiers, a 3:1 colour for icons, borders, focus rings,
chart series and large display type, and a 4.5:1 colour for button fills and
small text. Darkening the 3:1 colour far enough to clear Silver collapses the
tiers into one and takes the brand cyan out of the interface altogether, which
is a bigger loss than the shortfall it fixes.

**The options, so the decision is a real one:**

1. **Leave it.** Accepts 2.84:1 on ring-against-input-border, which is the
   narrow case, while every backdrop case passes with margin. This is what
   Phase 11 shipped.
2. **Darken the border instead of the cyan.** Moving `--border` from
   `218 23% 88%` to roughly 84% lightness raises the ring contrast without
   touching the brand colour at all. This is probably the better answer, and it
   was not explored because Phase 11 was scoped to the cyan. It changes the
   weight of every border and table rule in the product, so it wants a proper
   look rather than a token nudge.
3. **Give focus rings their own colour.** Rings do not have to be brand cyan.
   A dedicated ring colour would satisfy 1.4.11 everywhere and leave both brand
   tiers alone.

Option 2 is the one worth costing first.

---

## T-5 — Warn when a correcting shipment event is dated before what it supersedes

**Status:** open, usability gap with a real trap in it
**Owner:** application change, small
**Found:** 26 July 2026, during the Phase 11 repair rehearsal

`current_status` on a shipment header is derived from the event with the
**latest date**, not the most recently entered one. A correction dated earlier
than the entry it is putting right therefore lands on the timeline, reads
correctly to a human, and changes nothing on the Shipping list.

This is not hypothetical. It happened on the first attempt at repairing
SHP-26-0002: the correcting "Arrived at NCAT" event was dated 26 June, the true
arrival date, while the entry being corrected was dated 26 July. The event was
written, the timeline looked right, and the header stayed on "Shipped". It took
a database check to notice, and a clerk would have had no reason to look.

The trap is worst in exactly the situation the correction path exists for:
someone fixing an error naturally reaches for the date the thing really
happened, which is the date that does not work.

**Suggested fix.** When recording an event whose date is earlier than the
current latest event on that shipment, warn at entry rather than refusing:

> This entry is dated before the newest entry on this timeline (26 Jul 2026),
> so it will not change the status shown on the Shipping list. If you are
> correcting that entry, date this one today and put the real date in the note.

Refusing would be wrong. Backdating a genuinely late-arriving piece of news is
legitimate, and the timeline is meant to record what was believed and when. The
problem is silence, not the behaviour.

**Interim cover.** Documented in the user guide (§14a) and as an explicit step
in the go-live checklist's SHP-26-0002 rehearsal. Documentation is weaker than a
warning at the point of entry, which is why this stays open.

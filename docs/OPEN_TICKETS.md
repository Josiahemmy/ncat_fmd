# Open tickets

Things that are real, understood, and deliberately not being fixed in the
current phase. Each one records what was measured, who can actually act on it,
and what would settle the open question.

Raised: 26 July 2026 (Phase 10 pre-UAT closeout).

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

# Builder Prompt #1 — Phase 0: Foundation, Design System & CI/CD

**From:** CTO
**Project:** NCAT FMD Inventory System — see full spec at `docs/superpowers/specs/2026-07-17-ncat-fmd-inventory-design.md` (read it first, top to bottom).
**Deliverable required back:** a diff report summary (files created/changed, decisions made, anything blocked).

## Skills to invoke before starting
Invoke `superpowers` (brainstorming is already done — use `superpowers:executing-plans` discipline), `task-observer`, and for all UI work: `frontend-design`, `ui-ux-pro-max`, and `impeccable`. Use TDD where practical (`superpowers:test-driven-development`).

## Task

### 1. Scaffold
- In the project root, create a new Laravel 12 app (PHP 8.2+ compatible) with **Breeze (Inertia + React + Tailwind)**.
- Add and configure: `spatie/laravel-permission`, `spatie/laravel-activitylog`, `barryvdh/laravel-dompdf`. Frontend: `framer-motion`, `recharts`, shadcn/ui (init with Tailwind config mapped to NCAT tokens below).
- Git: init repo, remote `https://github.com/Josiahemmy/ncat_fmd`, branch `main`. **First commit must include a `.gitignore` that excludes `.env`, `docs.txt`, `/node_modules`, `/vendor`, `/public/build`.** Never commit credentials.
- `.env.example` with placeholders; local `.env` uses the MySQL credentials the Project Lead will supply (DB `almadin1_ncat`). Do not hardcode credentials anywhere.

### 2. NCAT design system
Create Tailwind design tokens + a `resources/js/theme` module from `NCAT_Brand_Assets/NCAT_Color_Palette.json`:
- Primary: Aviation Blue `#009DE0`, Deep Navy `#101A62`, Sky Blue `#13B8F0`; Accents: Aviation Cyan `#00C2FF`, Golden Yellow `#FFD600`, Sun Gold `#FFB800` (gold used sparingly — highlights/achievements only); Dark: Midnight Blue `#050A23`; Neutrals: Ink `#111318`, Graphite `#353A45`, Steel `#737B89`, Silver `#D9DEE7`, Mist `#F3F7FB`; Semantic: Success `#168A55`, Warning `#F59E0B`, Error `#D92D20`, Info `#1677C8`.
- Typography: Plus Jakarta Sans (headings) + Inter (body), self-hosted via `@fontsource` (no external CDN calls — CSP-friendly and works offline).
- Copy NCAT logos/favicons from `NCAT_Brand_Assets/` into `public/brand/`; wire favicons + `<title>` branding.
- Build the base component kit (shadcn-based, NCAT-skinned): Button, Card (incl. glass variant), Input/Select/DatePicker, Table shell, Badge, Modal, Toast, Skeleton, EmptyState, PageHeader, StatCard.

### 3. Layout shell
- **AuthLayout**: split-screen login — left panel Midnight/Deep Navy gradient with NCAT logo and a subtle animated aircraft silhouette (use an SVG from `aircrafts_svgs/`), right panel the form. Premium feel: soft motion, focus states, loading transitions.
- **AppLayout**: collapsible Deep Navy sidebar (logo, module nav with icons — Dashboard, Aircraft Type, Work Orders, Requisitions, Receiving, Issuing, Tally Cards, Parts, Reports, Administration; non-Dashboard items can route to styled "Coming in next phase" placeholder pages), topbar (global search input [non-functional placeholder], notification bell, user avatar menu with logout). Framer Motion page transitions between Inertia pages. Fully responsive: sidebar becomes drawer on mobile.
- **Dashboard page**: branded shell with greeting, date, 4 placeholder StatCards with skeleton→value animation, and an "empty state" chart card — real data comes in Phase 4.
- Auth: no public registration (remove/disable the register route); seed one Super Admin user (credentials in seeder, flagged for change on first login).

### 4. CI/CD (GitHub Actions)
- Workflow on push to `main`: PHP setup → `composer install --no-dev --optimize-autoloader` → Node setup → `npm ci && npm run build` → run test suite (must pass to deploy) → deploy via SSH (rsync/deployer) to the cPanel subdomain root for `office.ncatfmd.com.ng` → remote: `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`, storage:link → curl health check on `https://office.ncatfmd.com.ng/up`.
- Secrets via GitHub repo secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH`. Document in `README.md` exactly which secrets the Project Lead must add and how to generate the SSH key in cPanel.
- Handle the cPanel docroot correctly (public/ must be the web root for the subdomain, or use the documented symlink/docroot adjustment — pick one, document it).

### 5. Tests & verification
- Feature tests: login works, guests redirected, registration disabled, dashboard renders for authed user.
- Run the full build locally (`npm run build`, `php artisan test`) and verify zero errors before finishing.

## Definition of done
Login page + branded dashboard shell live at https://office.ncatfmd.com.ng, deployed automatically by GitHub Actions from `main`, tests green, no credentials in the repo.

## Report back
Diff report summary: files added/modified (grouped), packages installed with versions, design-system decisions, CI/CD setup steps the Project Lead must complete manually (GitHub secrets, cPanel docroot), and any blockers.

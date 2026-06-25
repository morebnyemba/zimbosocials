# Agent Guide — Zimbo Socials

> This file is written for AI coding agents. It assumes you know Laravel and React conventions but nothing about this specific project. All facts below are derived from the actual codebase; do not assume generic Laravel behavior where this project diverges from it.

---

## 1. Project Overview

**Zimbo Socials** is a social-media marketing (SMM) platform built as a Laravel 13 monolith with a React/Inertia frontend. It lets customers buy engagement services (followers, likes, views, etc.) while giving the business owner tools to manage services, orders, payments, support tickets, marketers, business contracts, and a reseller REST API.

Core product capabilities:

- **Public marketing site** with service catalog, contact page, and static legal pages.
- **Customer dashboard**: browse services, place orders, fund wallet, withdraw, open tickets, view referrals, manage contracts, regenerate API key.
- **Wallet & payments**: Paynow card/express/mobile-money checkout, manual bank/wallet deposits with proof upload, and admin approval/rejection of pending deposits.
- **Upstream order fulfilment**: orders are pushed to one or more configured external SMM providers; status and partial refunds are synced automatically.
- **Marketer ecosystem**: marketers can have public portfolios, social links, reviews, and apply to business contracts.
- **Business contracts**: business accounts can post contract opportunities; marketers apply and submit proof; contract owners review and release payouts.
- **Admin panel**: user/role/balance management, service catalog, order/transaction/ticket management, revenue analytics, upstream provider management, WhatsApp template sync, marketing campaigns, settings.
- **Reseller API**: `Authorization: Bearer {api_key}` under `/api/v1/...`.
- **Referral program**: first-deposit bonus and recurring order commission, configurable from settings.
- **Notifications**: in-app, email, and WhatsApp (Meta Cloud API or Twilio), driven by queued jobs.
- **PWA**: generated only in production builds via `vite-plugin-pwa`.

---

## 2. Technology Stack

| Layer | Tech |
|-------|------|
| Runtime | PHP `^8.3` |
| Backend framework | Laravel `^13.0` |
| Auth / verification | Laravel Breeze-style controllers, custom `AuthController`, `MustVerifyEmail`, Sanctum installed but not used for API (custom API key auth) |
| Frontend bridge | Inertia Laravel `^2.0` + `@inertiajs/react` `^2.0` |
| Frontend UI | React `^18.2`, TypeScript `^5`, Vite `^8`, Tailwind CSS `^3.2` |
| UI primitives | Base UI React, Radix Navigation Menu, `class-variance-authority`, `tailwind-merge`, `clsx`, `lucide-react` |
| Routing helpers | Ziggy (`tightenco/ziggy`) |
| Payments | Paynow PHP SDK (`paynow/php-sdk`) |
| Queue / scheduler | Laravel database queue + schedule worker |
| Database default | SQLite (`DB_CONNECTION=sqlite`) locally; MySQL expected in production |
| Cache/session | Database by default (configurable) |
| Build tooling | `laravel-vite-plugin`, `@vitejs/plugin-react`, `vite-plugin-pwa` |
| Code quality | Laravel Pint (`laravel/pint`) available; PHPUnit `^12.5.12` |

---

## 3. Repository Layout

Standard Laravel structure with these notable additions:

```
.
├── app/
│   ├── Console/Commands/         # Artisan commands (upstream sync, cleanup, WhatsApp template sync)
│   ├── Exceptions/               # Custom exceptions (e.g. InsufficientBalanceException)
│   ├── Http/
│   │   ├── Controllers/          # Controllers grouped by domain (Auth, Admin, Order, Wallet, etc.)
│   │   ├── Middleware/           # SetLocale, HandleInertiaRequests, IsAdmin, IsMarketer
│   │   └── Requests/             # Form request classes
│   ├── Jobs/                     # Queued jobs (notifications, upstream dispatch, audit log)
│   ├── Mail/                     # Localized mailables
│   ├── Models/                   # Eloquent models (User, Order, Service, Transaction, etc.)
│   ├── Notifications/            # Localized password-reset / verify-email notifications
│   ├── Policies/                 # BusinessContractPolicy, OrderPolicy
│   ├── Providers/
│   │   └── AppServiceProvider.php# Rate limiters, policy registration, settings boot loader
│   └── Services/                 # Domain services
│       ├── AI/                   # GeminiClient, ServiceEnricher
│       ├── Upstream/             # UpstreamProviderClient, OrderDispatchService
│       ├── DepositService.php
│       ├── NotificationService.php
│       ├── OrderService.php
│       ├── ReferralService.php
│       └── WhatsAppService.php
├── bootstrap/app.php             # Laravel 11+ application bootstrap; registers web/api/console routes and middleware aliases
├── config/
│   ├── services.php              # Third-party credentials (Paynow, WhatsApp, Tawk, Gemini, referrals)
│   ├── upstream.php              # Deprecated; provider config now lives in DB via `upstream_providers`
│   └── whatsapp-templates.php    # Local WhatsApp template definitions
├── database/
│   ├── factories/                # Model factories for tests
│   ├── migrations/               # Full migration set (users, services, orders, transactions, tickets, contracts, marketers, etc.)
│   └── seeders/DatabaseSeeder.php# Creates demo admin, marketer, customer, sample services, manual payment details
├── public/
│   ├── .htaccess                 # Standard Laravel rewrite
│   ├── cpanel-installer.php      # Browser-based cPanel installer (delete after use)
│   ├── index.php                 # Entry point; calls $app->usePublicPath(__DIR__)
│   └── build/                    # Vite production output
├── resources/
│   ├── css/app.css               # Tailwind directives only
│   ├── js/                       # Inertia/React app
│   │   ├── app.tsx               # Inertia app bootstrap
│   │   ├── bootstrap.ts          # Axios default header
│   │   ├── Components/           # Reusable components + `ui/` shadcn-style primitives
│   │   ├── Layouts/              # AdminLayout, AuthenticatedLayout, GuestLayout, MarketingLayout
│   │   ├── lib/                  # utils.ts (cn), i18n.ts (useTranslation)
│   │   ├── Pages/                # Inertia page components (mirror route names)
│   │   └── types/                # TypeScript declarations
│   ├── lang/{en,sn,nd}/          # Translation files (auth, mail, messages)
│   └── views/
│       ├── app.blade.php         # Inertia root view (also injects Tawk.to script)
│       └── ...                   # Legacy Blade views and email templates
├── routes/
│   ├── web.php                   # Main web routes
│   ├── api.php                   # Reseller API routes
│   └── console.php               # Scheduled commands
├── scripts/
│   └── build-cpanel.ps1          # PowerShell script that produces cPanel deployment zips
├── tests/
│   ├── Feature/                  # Feature tests
│   └── Unit/                     # Unit tests
├── composer.json                 # PHP dependencies and scripts
├── package.json                  # Node scripts and frontend dependencies
├── vite.config.js                # Vite + Laravel plugin + PWA (production only)
├── tailwind.config.js            # Tailwind config with brand colors
├── tsconfig.json                 # TypeScript paths: `@/*` -> `resources/js/*`
├── components.json               # shadcn-style alias config
└── phpunit.xml                   # PHPUnit config (SQLite in-memory, testing env)
```

---

## 4. Code Organization & Conventions

### Backend

- **Controllers** are grouped by domain under `app/Http/Controllers/`. Admin controllers are prefixed with `Admin`; marketing pages use `MarketingController`; authentication uses a mix of `AuthController` and Laravel Breeze-style controllers under `Auth/`.
- **Services** contain business logic that should not live in controllers:
  - `OrderService::placeOrder()` atomically validates, charges the wallet, creates the order, and dispatches upstream.
  - `DepositService::credit()` is the single source of truth for resolving pending deposits (used by webhooks, return-URL polls, and admin approvals).
  - `NotificationService::notify()` creates in-app notifications and dispatches email/WhatsApp jobs.
  - `ReferralService` awards first-deposit bonuses and order commissions.
  - `WhatsAppService` supports Meta Cloud API and Twilio.
  - `UpstreamProviderClient` talks to generic SMM-provider endpoints (`add`, `status`, `services`, `balance`).
- **Models** use typed Eloquent relations, casts, and helper scopes. `User` has role helpers (`isAdmin`, `hasMarketerAccess`, `canManageFinances`, etc.) and balance helpers (`deductBalance`, `creditBalance`).
- **Jobs** live in `app/Jobs/` and are pushed to the `notifications` queue (or `default` queue for `DispatchOrderUpstream`).
- **Middleware**:
  - `SetLocale` picks locale from session → user preference → `sn` (Shona) default.
  - `HandleInertiaRequests` shares `auth.user`, `flash`, `notifications_count`, `locale`, and `translations`.
  - `IsAdmin` / `IsMarketer` gate role access.
- **Rate limiters** are registered in `AppServiceProvider::registerRateLimiters()`: `paynow-init`, `wallet-proof-submit`, `wallet-manual-deposit`, `wallet-withdraw`, `api-key`.

### Frontend

- **Inertia pages** are resolved from `resources/js/Pages/${name}.tsx` via `import.meta.glob('./Pages/**/*.tsx')`.
- **Route naming** is important: Ziggy exposes named Laravel routes to React; the TypeScript path `ziggy-js` points to `vendor/tightenco/ziggy`.
- **Layouts** wrap pages; pages use `AuthenticatedLayout`, `AdminLayout`, `MarketingLayout`, or `GuestLayout`.
- **Components**:
  - `resources/js/Components/` contains Breeze-style form and nav components.
  - `resources/js/Components/ui/` contains shadcn-style primitives (`button`, `card`, `dialog`, `input`, `navigation-menu`).
- **Styling**: Tailwind with custom `brand.green` (`#0B3E09`) and `brand.orange` (`#DC7112`). Use the `cn()` utility from `@/lib/utils` for conditional class merging.
- **i18n**: `useTranslation()` reads translations injected via Inertia shared props (`translations` = `__('messages')`). Default locale is Shona (`sn`); English (`en`) and Ndebele (`nd`) are also supported.
- **PWA**: `vite-plugin-pwa` is only enabled in production builds. Manifest name is `Zimbo Socials`, theme color `#10b981`.

---

## 5. Build, Development & Test Commands

### First-time setup

```bash
composer setup
```

This runs: `composer install`, copies `.env.example` → `.env`, generates `APP_KEY`, runs migrations, installs npm deps with `--ignore-scripts`, and builds assets.

### Local development

Recommended single command (starts Laravel, Vite, queue worker, and scheduler):

```bash
npm run dev:all
```

Open the app at `http://127.0.0.1:8000` (not the Vite URL).

Alternative separate commands:

```bash
npm run serve   # Laravel dev server on 127.0.0.1:8000
npm run dev     # Vite HMR on 127.0.0.1:5174
```

Or via Composer:

```bash
composer dev
# Runs php artisan serve, queue:listen, schedule:work, and npm run dev concurrently.
```

### Production build

```bash
npm run build   # tsc + vite build -> public/build
```

### Database

```bash
php artisan migrate:fresh --seed   # reset DB with demo data
php artisan db:seed                # run DatabaseSeeder
```

The `DatabaseSeeder` creates:

- `admin@zimsocials.co.zw` / `password` (admin)
- `tendai@example.com` / `password` (marketer)
- `chiedza@example.com` / `password` (customer)
- sample services across Instagram, YouTube, TikTok, Facebook, Twitter/X, Telegram, WhatsApp
- manual payment details for EcoCash and InnBucks

### Testing

```bash
composer test
# Equivalent to: php artisan config:clear && php artisan test
```

Or directly:

```bash
php artisan test
vendor/bin/phpunit
```

- `phpunit.xml` defines two suites: `Unit` and `Feature`.
- Tests use an in-memory SQLite database (`DB_DATABASE=:memory:`), array cache/session/mail, and sync queue.
- Most feature tests use `RefreshDatabase`.
- Notable test coverage: authentication, profile, wallet flow, order placement guards, Paynow webhook, API controller, referrals, admin upstream import, contract workflow, marketing home, dashboard smoke.

### Code style

```bash
vendor/bin/pint          # Laravel Pint (dry-run by default; use --repair)
```

---

## 6. Deployment

This project is designed for manual deployment to a cPanel-style shared host. There is no CI/CD configuration in the repo.

### Build the deployment package

```powershell
.\scripts\build-cpanel.ps1
```

Optional switches:

- `-SkipAssets`   — skip `npm run build`
- `-SkipComposer` — skip production dependency install/restore

The script produces:

- `dist/my-app.zip` — Laravel application (extract to `/home/<user>/my-app`)
- `dist/public_html.zip` — web root (extract to `/home/<user>/public_html`)

It excludes `.git`, `node_modules`, `tests`, `dist`, IDE folders, `.env`, logs, and zip files; installs production Composer deps; rewrites `public_html/index.php` so `vendor/autoload.php` and `bootstrap/app.php` resolve from `../my-app`; then restores dev Composer deps locally.

### Install on cPanel

1. Upload/extract `my-app.zip` to `/home/<user>/my-app`.
2. Upload/extract `public_html.zip` to `/home/<user>/public_html`.
3. Visit `https://<domain>/cpanel-installer.php`.
4. Enter the application path, app URL, MySQL credentials, and admin account details.
5. Click **Run Installation**.
6. **Delete `cpanel-installer.php` immediately** after installation.

The installer:

- writes/updates `.env` (`APP_ENV=production`, `APP_DEBUG=false`)
- runs `key:generate --force`, `migrate --force`, `optimize:clear`, `view:cache`
- creates `public_html/storage` symlink → `my-app/storage/app/public`
- inserts the admin user

### Post-deployment

- Set up a cron job to run Laravel's scheduler every minute:

  ```bash
  * * * * * cd /home/<user>/my-app && php artisan schedule:run >> /dev/null 2>&1
  ```

- Run a queue worker for the `default` and `notifications` queues (or use a supervisor process):

  ```bash
  php artisan queue:work --queue=default,notifications --tries=3
  ```

- Ensure `public/.htaccess` is present (it handles Authorization and X-XSRF-Token headers).

---

## 7. Configuration & Environment

Copy `.env.example` to `.env` and configure at least:

| Variable | Purpose |
|----------|---------|
| `APP_NAME`, `APP_URL`, `APP_KEY` | Application identity and encryption key |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database (MySQL in production) |
| `PAYNOW_INTEGRATION_ID`, `PAYNOW_INTEGRATION_KEY` | Paynow gateway credentials (read from `config/services.php`) |
| `WHATSAPP_PROVIDER` | `meta` (default) or `twilio` |
| `WHATSAPP_API_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID` | Meta Cloud API credentials |
| `TWILIO_SID`, `TWILIO_WHATSAPP_FROM` | Twilio fallback credentials |
| `TAWK_PROPERTY_ID`, `TAWK_WIDGET_ID` | Optional Tawk.to live chat |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Optional Google Gemini for AI cleanup/translations during upstream service import |
| `REFERRAL_FIRST_DEPOSIT_REWARD`, `REFERRAL_ORDER_COMMISSION_PERCENT`, `REFERRAL_ORDER_COMMISSION_MIN_TOTAL` | Optional defaults; can be overridden in Settings table |

### Settings table

`AppServiceProvider::loadSettings()` reads the `settings` table at boot and overrides Laravel config values for:

- SMTP mail settings (`mail.mailers.smtp.*`, `mail.from.*`)
- WhatsApp config (`services.whatsapp.*`)
- App-level config (`app.*`)
- Generic `settings.{key}` values

Settings are cached for 5 minutes. If the table does not exist yet, the loader fails silently so migrations still run.

### Upstream providers

External SMM providers are configured via **Admin → Upstream Providers** (stored in `upstream_providers` and `service_upstreams`). The old `config/upstream.php` env vars (`SMM_PROVIDER_URL`, `SMM_PROVIDER_KEY`) are deprecated and no longer read by the application.

### Scheduled commands

`routes/console.php` schedules:

```php
Schedule::command('upstream:sync-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('upstream:sync-services')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('transactions:cleanup-stale --hours=24')->hourly()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours=48')->daily();
```

Available Artisan commands:

- `upstream:sync-services` — fetch service prices/constraints from active upstream providers and apply a configurable profit margin.
- `upstream:sync-orders` — sync status of pending/processing orders and auto-refund cancelled/partial orders.
- `transactions:cleanup-stale` — expire pending transactions older than N hours.
- `whatsapp:sync-templates` — push local WhatsApp templates to Meta; supports `--dry-run`, `--list`, `--delete-missing`.

---

## 8. Security Considerations

- **CSRF**: Paynow webhook routes are excluded from CSRF verification in `bootstrap/app.php` (`/paynow/webhook`) and `routes/web.php` (`/webhooks/payment`).
- **Rate limiting**: custom limiters are registered in `AppServiceProvider` for Paynow, wallet actions, and API access.
- **Payment webhooks**: Paynow status updates are verified via the Paynow SDK hash. The generic `/webhooks/payment` route also bypasses CSRF.
- **Balance operations**: always use `lockForUpdate()` (see `OrderService::placeOrder`, `DepositService::credit`, `SyncUpstreamOrders::processOrderUpdate`).
- **Admin authorization**: `User` has granular admin-role helpers (`hasFullAdminAccess`, `canManageFinances`, `canManageSupport`, `canManageCompliance`).
- **API authentication**: `ApiController` resolves users by `api_key` from a Bearer token. Rate-limited at 60 req/min per key.
- **Secrets**: `.env` and `auth.json` must not be committed. The cPanel build script strips `.env` and log files from the deployment package.
- **Installer exposure**: `public/cpanel-installer.php` must be deleted after production installation.
- **File uploads**: proof uploads for deposits/contracts go through `storage/app/public`; ensure `php artisan storage:link` or the cPanel installer symlink is present.
- **PWA manifest**: `manifest.webmanifest` and service worker are generated in production builds; do not expose development builds publicly.

---

## 9. Testing Strategy

- PHPUnit is the test runner; no Pest is installed despite the composer allow-plugin entry.
- Feature tests cover real user flows using `RefreshDatabase` and factories.
- Unit tests are minimal (`tests/Unit/ExampleTest.php` only).
- External HTTP calls in tests are faked with Laravel's `Http::fake()` or Paynow SDK mocks.
- Queue and mail are driven by sync/array drivers in the testing environment, so queued notifications can be asserted directly.

Run the full suite before deployments:

```bash
composer test
```

---

## 10. Notes for AI Agents

- **Do not change the cPanel installer or build script unless asked.** They encode the exact public/app split expected in production.
- **Add new Inertia pages** under `resources/js/Pages/` matching the route component name passed to `inertia('Name')`.
- **Add new routes** in `routes/web.php` (or `routes/api.php` for API). Keep route names consistent with existing `admin.`, `marketer.`, and action naming.
- **Use services for business logic** instead of putting it directly in controllers. If you touch wallet balance or deposits, reuse `DepositService` and `OrderService` to preserve atomicity and audit logging.
- **Use `NotificationService::notify()`** for any user-facing event; it handles in-app/email/WhatsApp consistently.
- **Lock rows** when mutating balance-sensitive records.
- **Respect i18n**: default locale is Shona. Use `__('messages.key')` on the backend and `useTranslation()` on the frontend. Add translation keys to `resources/lang/{en,sn,nd}/messages.php`.
- **Respect rate limiters** when adding wallet/payment/API routes.
- **Avoid modifying `config/upstream.php` env usage** — provider config now lives in the database.
- **Run tests after changes** and use `vendor/bin/pint` to keep PHP style consistent.

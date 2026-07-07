# Zimbo Socials

A social-media marketing platform for Zimbabwe: users fund a wallet (Paynow
gateway or manual proof-of-payment deposits), buy social media services
delivered through upstream SMM providers, and businesses hire vetted marketers
through an escrow-backed contract marketplace. Includes a referral program,
monthly leaderboard with wallet prizes, multi-channel notifications (in-app,
WhatsApp, email), AI-assisted tooling (Gemini), an admin panel, and a reseller
REST API.

**Stack:** Laravel 12 · Inertia.js + React (TypeScript) · Tailwind · Vite (PWA)
· MySQL · shared-hosting-friendly (no persistent daemons required).

## Local development

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run dev:all       # Laravel at :8000 + Vite HMR at :5174
```

Open the app via the Laravel URL: `http://127.0.0.1:8000`.

Run the test suite with `php artisan test`.

## Production requirements

### 1. Cron (critical)

**Nothing time-based works without this.** Order status syncing, queued
WhatsApp/email notifications, deposit cleanup, contract deadlines, backups and
the monthly leaderboard all run through Laravel's scheduler, which must be
invoked every minute by the host's cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

The admin dashboard shows a red warning banner whenever this cron stops
running (scheduler heartbeat older than 10 minutes).

### 2. Queue

`QUEUE_CONNECTION=database`. There is no persistent worker — the scheduler
drains the queue every minute (`queue:work --stop-when-empty --max-time=50`),
which is the standard shared-hosting pattern.

## Scheduled jobs

| Command | Schedule | Purpose |
|---|---|---|
| `upstream:sync-orders` | every 5 min | Pull order statuses from providers; auto-refund cancelled/partial |
| `queue:work --stop-when-empty` | every minute | Drain queued notifications/jobs |
| `transactions:cleanup-stale` | hourly | Expire unpaid deposits (final-polls Paynow first; never touches withdrawals) |
| `contracts:close-expired` | daily 01:00 | Close contracts past deadline, refund unused escrow |
| `upstream:sync-services` | daily 02:00 | Refresh provider service catalogues |
| `wallet:reconcile` | daily 03:00 | Verify every balance against the transaction ledger; alert on drift |
| `db:backup` | daily 03:30 | Dump the database to `storage/app/backups` (keeps 14) |
| `orders:flag-stuck` | daily 08:00 | Alert admins about active orders with no movement for 5+ days |
| `referral:warn-commission-expiry` | daily 09:00 | Warn referrers before their commission window lapses |
| `leaderboard:close-month --notify` | monthly | Snapshot rankings, credit prizes, notify winners |

## Money model

- `users.balance` is the wallet; every movement writes a `transactions` row
  (`deposit`, `order_charge`, `refund`, `withdrawal`, `bonus`,
  `contract_payout`, `contract_earning`).
- All balance mutations run inside DB transactions with row locks.
- Refunds are capped at *charged minus already refunded* per order, so double
  refunds are structurally impossible.
- Manual (non-gateway) deposits earn an instant bonus
  (`manual_deposit_bonus_percent` setting, default 5%); referred users get a
  welcome bonus on their first qualifying deposit (default 10%) and their
  referrer earns per-order commission, paid on order **completion**.
- `wallet:reconcile` is the safety net: any code path that drifts balance from
  ledger is reported per-user within a day.

## Security notes

- Admin logins require an emailed 6-digit code (second factor). Emergency
  disable from SSH/cron: `php artisan admin:2fa off` (setting:
  `admin_2fa_enabled`).
- Reseller API keys are stored as SHA-256 hashes; the plaintext is shown once
  at generation. Authenticate with `Authorization: Bearer <key>` against
  `/api/v1` (see the in-app Developer → API Docs page).
- Payment webhooks: Paynow is verified by the SDK's hash check;
  `/webhooks/payment` requires an HMAC-SHA256 `X-Webhook-Signature` and fails
  closed when unconfigured.

## Key configuration

Environment (see `.env.example`): Paynow (`PAYNOW_INTEGRATION_ID/KEY`),
WhatsApp Business API (Meta or Twilio), SMTP mail, Tawk.to
(`TAWK_PROPERTY_ID/WIDGET_ID`), Gemini (`GEMINI_API_KEY`).

Runtime settings live in the `settings` table (admin → Settings): deposit
bonus percent, referral program rates and windows, monetizer thresholds,
admin 2FA toggle, and more.

See `ARCHITECTURE_ANALYSIS.md` for a deeper architecture walkthrough.

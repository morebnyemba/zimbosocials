# ZimboSocials — Architecture Analysis & Missing-Logic Audit

*Analysis date: 2026-07-06. Scope: full backend code review of money flows, order lifecycle, upstream integration, contracts/escrow, referrals, leaderboard, notifications, and the reseller API.*

---

## 1. Architecture overview

**Stack:** Laravel 11/12 (bootstrap/app.php builder style) + Inertia (React/TS, shadcn) + Vite PWA, deployed to shared cPanel hosting (no persistent worker — the queue is drained by a cron-driven `queue:work --stop-when-empty --max-time=50` every minute).

**Domains:**

| Domain | Key pieces |
|---|---|
| Wallet | `users.balance` column + `transactions` ledger; `DepositService` (single credit path), `WalletController` (manual deposits w/ proof, withdrawals), `PaynowController` (gateway: web, EcoCash/OneMoney/InnBucks/O'mari; webhook + return-URL poll + client poll) |
| SMM orders | `OrderService::placeOrder` (atomic charge w/ row locks, duplicate-link guard) → `OrderDispatchService` (provider failover) → scheduled `upstream:sync-orders` every 5 min → `OrderStatusSyncService` (status mapping, auto-refund on cancelled/partial) |
| Contracts (escrow) | Business pre-funds budget×slots +10% fee → marketers apply → approval consumes slot → proof submission → proof approval releases budget to marketer; `closeContract` refunds unused slots |
| Referrals | Flat referrer reward + referred-user welcome % on first qualifying deposit; per-order % commission with activity window, lifetime window, per-referral cap; idempotent via transaction `reference` |
| Leaderboard | Monthly snapshot close (`leaderboard:close-month`) with wallet-bonus prizes |
| Notifications | `NotificationService` → in-app always, WhatsApp + email (queued) by type allow-lists |
| Reseller API | `routes/api.php` v1, plaintext bearer API key lookup, throttled |
| Admin | Transactions approve/reject, withdrawals process/reject (reject refunds reserved funds), order refund/force-sync/manual status, balance adjustment |

**What's done well:** consistent `lockForUpdate` discipline on every balance mutation; a single `DepositService::credit()` path shared by webhook/poll/admin; idempotent referral rewards via `reference`; HMAC webhook validation that fails closed; sensible provider failover; decent feature-test coverage for money paths.

---

## 2. Critical missing logic (money can be lost or duplicated)

### 2.1 Stale-transaction cleanup destroys reserved withdrawal funds
`CleanupStaleTransactions` (`app/Console/Commands/CleanupStaleTransactions.php:30`) expires **every** pending transaction older than 24 h with a blanket `->update(['status' => 'expired'])` — no `type` filter.

- **Withdrawals** debit the user's balance at request time ("funds reserved", `WalletController::withdraw`). If an admin doesn't process a withdrawal within 24 h, the hourly cleanup flips it to `expired` **without refunding** — the reserved money silently vanishes. (`rejectWithdrawal` exists precisely to return those funds; the cleanup bypasses it.)
- **Paynow deposits** are expired without one final poll against the stored poll URL. And because `DepositService::credit()` refuses anything not `pending`, a late webhook for an expired-but-actually-paid transaction is ignored → *customer paid, never credited*.
- **Manual deposits with a submitted proof** awaiting admin review are also expired after 24 h.
- The cleanup bypasses `DepositService::reject()`, so users get no notification and no audit log is written.

**Fix:** exclude `withdrawal` (or refund on expiry), poll Paynow before expiring gateway deposits, exclude deposits with `proof_url`, and route through the service for notify/audit.

### 2.2 `leaderboard:close-month --notify` fatally crashes and strands prizes
`CloseMonthlyLeaderboard.php:84` calls `$notificationService->notify($user, "🏆 …", 'leaderboard_prize')` but the signature is `notify(int $userId, string $type, string $title, string $body, array $data = [])` — a `User` object where an `int` is expected **and** only 3 of 4 required args. The scheduled run uses `--notify`, so it throws after awarding the **first** winner.

Worse, re-running doesn't recover: the command early-returns when `snapshotMonth()` creates 0 new rows (`:33`), so the already-snapshotted-but-unawarded winners are never processed again. One winner gets paid; everyone else never does.

### 2.3 Failed upstream dispatch = order stuck forever (and the retry job is dead code)
- `DispatchOrderUpstream` (5 tries, exponential backoff) is **never dispatched anywhere** — dispatch happens synchronously inside the HTTP request (`OrderService::placeOrder` → `dispatch()`).
- If all providers fail, the order remains `pending` / `pushed_to_upstream = false`. The scheduled sync only queries `pushed_to_upstream = true`; admin Force Sync explicitly refuses never-pushed orders; there is **no retry route/command at all**. The user-facing flash message (`messages.dispatch_retry`) promises a retry that doesn't exist.
- The customer's money stays charged; their only exit is manually cancelling.

**Fix:** wire the existing job in (dispatch it when the synchronous push fails, or always) and/or add a scheduled "re-push failed orders" pass with an attempt cap + auto-refund.

### 2.4 Double-refund paths in admin actions
- `AdminOrderController::refund()` only guards `status === 'refunded'`. An order already **auto-refunded** by the sync (status `cancelled`, full charge returned) or **partially refunded** (status `partial`) can be refunded again for the *full* charge.
- `AdminOrderController::updateStatus()` lets an admin set `cancelled`/`refunded` **without any money movement** — order says refunded, wallet was never credited (or vice-versa when combined with the refund button).
- `OrderStatusSyncService::syncSingleOrder()` (admin Force Sync) has no current-status precondition: force-syncing an order that already received a partial refund when upstream later reports `cancelled` triggers a second, full-charge refund (`applyStatusUpdate` refunds `charge`, not `charge − already_refunded`).

**Fix:** track cumulative refunded amount per order (or sum `refund` transactions by `order_id`) and refund only the remainder; block terminal→terminal transitions in Force Sync; make `updateStatus` delegate money-bearing statuses to the refund path.

### 2.5 Contract escrow leaks
- **`deadline_at` is written but never enforced.** No scheduled job expires overdue contracts, nothing blocks applications/approvals past the deadline, no auto-refund of unclaimed escrow. Dead field.
- **No way to revoke an approved application.** If a hired marketer never delivers, the slot counts as "consumed" (`slotConsumingStatuses`) forever, so `closeContract` will never refund that slot's escrow. The business's money is permanently stuck.
- **Escrow accounting is never drawn down.** Proof approval pays `contract->budget` to the marketer but `funded_amount` is never decremented and there's no check against remaining escrow; the 10 % platform fee has no revenue ledger entry — it just disappears into the business's debit.
- Proofs on a **closed** contract can still be approved and paid (`ContractProofController::review` never checks contract status).

### 2.6 Referral commission has no clawback
`rewardReferrerOnReferredOrder()` pays the referrer at **order placement**. If the order is then cancelled or refunded (user gets 100 % back), the commission stays. A referred account can farm commissions: place qualifying orders, wait, cancel. **Fix:** award on order completion (hook into `OrderStatusSyncService`), or reverse the bonus transaction on refund.

---

## 3. Significant gaps (correctness / abuse, not immediate money loss)

1. **Cancel/dispatch race:** `OrderService` commits the order, then dispatches outside the transaction. In that window the user can cancel (status `pending`, not pushed → refund succeeds), after which `OrderDispatchService::dispatch()` still pushes and overwrites status to `processing` — user gets both the refund and the delivery. Dispatch should re-check status (`pending`) atomically before pushing.
2. **Upstream push is not idempotent:** an HTTP timeout after the provider accepted the order records a failure with no `external_order_id`; any retry would purchase twice upstream. At minimum record "unknown outcome" distinctly and reconcile manually.
3. **`AdminOrderController::store()` (manual order)** creates an order with arbitrary charge but never debits the user or writes a transaction, and never dispatches upstream. If that order is later "refunded", the user is credited money they never spent.
4. **API `refill` endpoint is a stub** (`ApiController.php:180` — `// TODO: trigger refill with upstream provider`) that returns success while doing nothing. Resellers will believe refills were requested.
5. **No stuck-order escalation:** orders whose provider was deleted/deactivated are skipped by the sync forever; nothing flags orders sitting in `processing` beyond N days.
6. **No ledger reconciliation:** `balance` is a mutable column; `transactions` record `balance_before/after` but nothing ever verifies `SUM(transactions.amount) == users.balance`. Any of the bugs above would drift silently. A daily reconciliation command + alert would surface every money bug in this list.
7. **Manual deposit amount is user-asserted:** admin approval credits whatever amount the user typed; there's no way to correct it to what the proof actually shows short of reject-and-redo.
8. **API keys stored in plaintext** (`zvk_live_…` in `users.api_key`). Hidden from serialization, but a DB leak exposes every reseller key; no hashing, rotation timestamps, or per-key scopes.
9. **Monetizer unlock is recomputed live** (`hasMonetizerAccess`) but `monetizer_unlocked_at` is only set elsewhere — a user who qualifies once (threshold met) loses access when the window slides unless something persists the unlock; verify intended semantics.

---

## 4. Minor / hygiene

- `public/build.zip`, `my-app.zip`, `public/cpanel-installer.php` and a stray `Desktop - Shortcut.lnk` inside `app/Http/Controllers/` are committed to the repo. The installer + deploy script in `public/` is a live attack surface if it survives to production.
- `Order.link` for API orders is validated as `url` but nothing validates it matches the service's platform.
- `OrderStatusSyncService` refund rounds to 4 dp while contract math rounds to 2 dp — pick one money precision (store cents or always 4 dp) to avoid drift.
- `WalletController::manualDeposit` allows unlimited pending deposit rows (no throttle/dedup).
- README is still the stock Laravel readme — none of the real architecture is documented.

---

## 5. Suggested priority order

1. Fix `CleanupStaleTransactions` (withdrawals + final Paynow poll) — active money loss on a schedule. **(2.1)**
2. Fix the `CloseMonthlyLeaderboard` notify signature + unawarded-snapshot recovery. **(2.2)**
3. Wire up `DispatchOrderUpstream` retries for failed pushes. **(2.3)**
4. Refund-remainder tracking to kill all double-refund paths. **(2.4)**
5. Contract deadline enforcement + approved-application revocation. **(2.5)**
6. Referral commission on completion instead of placement. **(2.6)**
7. Add a daily balance-vs-ledger reconciliation command. **(3.6)**

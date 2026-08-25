# What the AI actually costs, and what controls it

Written after a week that cost $2.50 on fewer than five chats a day. All rates
are Gemini paid-tier, per million tokens, from
<https://ai.google.dev/gemini-api/docs/pricing>.

| | 2.5 Flash | 2.5 Flash-Lite |
|---|---|---|
| input (text/image/video) | $0.30 | $0.10 |
| audio input | $1.00 | $0.30 |
| **output — thinking tokens included** | **$2.50** | **$0.40** |
| cached input | $0.03 | $0.01 |
| **cache storage, per hour of TTL** | **$1.00** | **$1.00** |

The two bold rows are where the money went, and neither was visible on the
admin dashboard.

## The three findings

**1. Thinking was ~60% of a chat turn and was never counted.** 2.5 Flash ships
with thinking on and dynamic — it picks its own budget, up to 24,576 tokens,
and every one bills at the $2.50/M output rate. Against a ~260-token reply
that is the larger half of the turn. The API reports it as
`usageMetadata.thoughtsTokenCount`, separate from `candidatesTokenCount`, and
`GeminiClient::recordUsage()` was only reading the latter — so `ai_usage`
recorded the visible reply and nothing of the reasoning behind it.

**2. A cache TTL is a storage bill, not free parking.** Explicit caching bills
$1.00 per million cached tokens per hour, for the whole TTL, whether a message
arrives or not. The system prompt is ~13,700 tokens, so an hour of TTL cost
$0.0137 up front. A cached read only saves $0.27/M against fresh input, so the
cache breaks even at roughly **4+ messages per hour of TTL** and loses money
below that. At a handful of chats a day the 3600s TTL was the second largest
line on the bill — spent mostly on idle hours.

This is volume-dependent, not wrong in principle. Put the TTL back up when
traffic is steady all day; the break-even moves with messages-per-hour, not
with total messages.

**3. Every call went to the flagship, including the ones deciding nothing.**
The voice-fusion pass is a *second* model call on the same inbound message, and
it makes no decision — the flow is already chosen and every fact it may use is
handed to it in the step prompt. Same for nudges, moderation, dashboard
summaries and marketing copy. Flash-Lite output is $0.40/M against $2.50/M.

**4. The catalogue was re-billed as fresh input on every single message.**
The service catalogue, advert packages, support items and payment accounts are
byte-identical for every user and every message — several thousand tokens of
it — yet they were rebuilt into the user turn each time at the $0.30/M fresh
rate. They now sit in the cached prefix at $0.03/M. Only what genuinely varies
per user (locale glossary, knowledge-base hits for *this* question, balance,
orders, pending deposits) still rides in the user turn.

The cache key is a hash of that prefix, so it does double duty: change a price
and the hash changes, which means a stale catalogue cannot be served from an
old cache.

## Before and after, at ~30 messages/day

| | before | after |
|---|---|---|
| thinking tokens | $0.68 | $0.00 |
| cache storage | $0.48 | $0.14 |
| voice pass (2nd call per message) | $0.20 | $0.01 |
| fresh context input | $0.24 | $0.05 |
| cached system prompt | $0.09 | $0.11 |
| reply tokens | $0.14 | $0.14 |
| **7-day total** | **$1.82** | **$0.45** |

A 75% cut, leaving ~5.5x headroom under $2.50/week. The model reconstructs $1.82 of the observed
$2.50; the rest is media turns (audio input is $1.00/M), idle-customer nudges,
dashboard recommendations and retries, all of which the same changes reduce.

## What was deliberately NOT done

**The system prompt was not trimmed for cost.** It is ~13,700 tokens and looks
like the obvious target, but it lives in the cache: a 28% trim would save
**about six cents a week**, because cached reads are $0.03/M and storage is
prorated over a 15-minute TTL. The prompt earns its length in the eval scores.
Two changes were made to it for *correctness*, not spend:

- Rule 7b told the model never to quote a payment number while rule 6a ordered
  it to give the number from WHERE TO PAY. A prompt that contradicts itself
  makes a model waffle — and waffling is what thinking tokens are.
- Rules 5b/5c, and 4f/4g, each said the same thing twice.

**Conversation history was left alone.** 18 turns re-sent fresh on every
message looks expensive and compounds with chat length, but the arithmetic says
otherwise: a real WhatsApp conversation is around eight messages, so the
average turn carries ~315 tokens of history — **$0.02 a week**. Capping it to
four turns would save less than a cent and cost the assistant its memory of
what the customer already told it, which rule 4 exists to protect.

## The controls

Everything is in `config/services.php` under `gemini`, with env overrides
documented in `.env.example`.

| env | default | what it does |
|---|---|---|
| `GEMINI_THINKING_BUDGET` | `0` | thinking on the chat decision call. `-1` restores dynamic, `512` is the middle setting |
| `GEMINI_THINKING_BUDGET_TEXT` | `0` | thinking on plain-text calls |
| `GEMINI_MODEL_LIGHT` | `gemini-2.5-flash-lite` | model for calls that decide nothing. Set equal to `GEMINI_MODEL` to disable the split |
| `GEMINI_CACHE_TTL` | `900` | prompt-cache TTL — see finding 2 before raising it |
| `GEMINI_DAILY_BUDGET_USD` | `0.14` | hard ceiling; ~$0.98/week |
| `GEMINI_CHAT_LIGHT` | `false` | run the main conversation call on Flash-Lite too. The last big lever and the only one touching the flow/money decision — see below |

### The ceiling is the part that actually guarantees the budget

Everything above makes the bill *smaller*. `GEMINI_DAILY_BUDGET_USD` is what
makes it *bounded*. `AIGuard` now reads today's real spend from `ai_usage`
(thinking included) and stops calling the model once it passes the ceiling; the
router falls back to its deterministic menu. That is a degraded conversation,
which is the point — it is the failure mode you choose in advance instead of
the one that arrives on an invoice. Per-phone and global *call counts* were
never a budget: a call's cost swings by an order of magnitude with catalogue
size, media, and how much the model decided to think.

## After changing a thinking budget

Re-run the golden set. It makes real API calls, one per case:

```
php artisan whatsapp:ai-eval
```

The chat call is a grounded extract-and-decide task with a server-side
`responseSchema`, which is the shape that survives no-thinking best — but that
is a prediction, not a measurement, and this repo has the tool to measure it.
If flow accuracy drops, `GEMINI_THINKING_BUDGET=512` buys most of the reasoning
back at a fifth of the old cost and still lands near $0.86 for seven days.

## If you need it lower still

The remaining $0.45 is mostly two irreducible things: the reply tokens
themselves ($0.14) and holding the cache ($0.14). Below that you are trading
quality for cents, so these are dials rather than defaults:

| lever | saves | what it costs you |
|---|---|---|
| `GEMINI_CHAT_LIGHT=true` | ~$0.25/wk | the flow/money decision moves to Flash-Lite. **Eval first** — this is the call the whole prompt exists to protect |
| `GEMINI_CACHE_SYSTEM_PROMPT=false` | ~$0.14/wk storage, **costs ~$0.86/wk** in fresh input | net loss at any volume above roughly one message an hour. Only sensible if the bot goes nearly idle |
| `WHATSAPP_AI_MAX_SERVICES` below 150 | ~$0.01/wk per 10 services dropped | the model can only quote what it can see; rule 8 forbids it inventing the rest |
| `WHATSAPP_MEDIA_AI=false` / `WHATSAPP_AUDIO_AI=false` | varies | audio input is $1.00/M, 3.3x text. But reading a screenshot off a customer who cannot copy a link is a sale saved |

The honest summary: the defaults in this PR land near $0.45/week modelled, the
ceiling makes $0.98 a guarantee, and `GEMINI_CHAT_LIGHT` is the only remaining
lever big enough to notice.

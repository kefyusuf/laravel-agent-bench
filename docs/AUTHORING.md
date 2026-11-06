# Authoring guide

How to add scenarios **without degrading benchmark quality**. Read
[FORMAT.md](FORMAT.md) first — this document is about *quality*, not format.

## The quality bar in one paragraph

A scenario earns its place when a competent engineer, reading the diff of the defective
code without knowing what to look for, would reasonably merge it — and when the production
consequence is severe, deterministic, and *invisible in the visible test suite*. The
benchmark measures whether an agent finds the invariant that the tests don't encode.
Anything that a doc lookup answers, that a linter catches, or that a failing test would
point at, is trivia — and trivia is worse than nothing because it dilutes the signal.

## The lifecycle of a scenario

```
1. pick a family + a production failure you have actually seen
2. design the invariant FIRST, the defect second
3. write the broken snapshot (everything else correct and idiomatic)
4. write visible tests: happy path only, green on the broken snapshot
5. write hidden tests: fail on broken, pass on fix, message states the invariant
6. write the reference fix as an edit spec, generate the patch
7. write distractors: plausible fixes that keep visible green, fail hidden
8. run the gates (below), then PR review against REVIEW-CHECKLIST.md
```

Step 2 is the one authors skip. **Do not design the defect first.** The invariant is the
grading target; the defect is just the vehicle. If you cannot state the invariant as a
testable property, you do not have a scenario yet — you have a gotcha.

## The mandatory gate (RED / GREEN / DISTRACTOR)

`node bin/bench.mjs validate --dynamic` runs, per scenario and matrix cell (requires
`vendor:prepare` + PHP):

| Step | Expectation | Catches |
| --- | --- | --- |
| `broken/visible-green` | visible suite passes on the broken snapshot | defect leaks into the visible suite (scenario too easy) |
| `broken/hidden-red` | hidden suite fails on the broken snapshot | defect is not real / not observable |
| `solution/visible-green` | visible suite passes after reference fix | fix breaks the surface |
| `solution/hidden-green` | hidden suite passes after reference fix | hidden tests are wrong or over-constrained |
| `distractor:*/visible-green` | visible suite passes after distractor | distractor is not plausible |
| `distractor:*/hidden-red` | hidden suite fails after distractor | hidden tests don't discriminate |

A scenario is `status: "validated"` only when every row is green on every declared cell.
CI runs this on every PR; a PR that flips any row fails.

**Distractors are not optional.** They are the proof that hidden tests measure the
invariant rather than the reference fix. One distractor minimum, two recommended for
difficulty ≥ 3. Good distractor sources:

- the *surface* fix that addresses the symptom without the mechanism (fix the validation
  rule but not the index; check the status but not the reference; set the flag but keep
  the unconditional side effect);
- the *over-broad* fix that introduces a new hole (add `is_admin` to fillable; key the
  limiter by a client-controlled header; disable strict mode instead of eager loading);
- the *mislocated* fix (add `ShouldBeUnique` when the problem is ordering; move the
  dispatch in the wrong direction; raise the timeout instead of `retry_after`).

## Determinism rules (hidden tests)

In-process is the law: sqlite `:memory:`, array cache/session/mail, `Http::fake`,
database queue when enqueue behaviour matters, no sleeps, no real time.

Framework mechanics you must know before writing hidden tests (verified against
`laravel/framework` 12.x source; re-verify against your target cell):

- **`Queue::fake()` bypasses `afterCommit` deferral** — `QueueFake::push()` records
  immediately; the deferral lives in the real connection's `enqueueUsing()`. To observe
  enqueue ordering: set `QUEUE_CONNECTION=database` and assert on the `jobs` table.
  This also means `assertNothingPushed()` inside a transaction proves nothing.
- **`ShouldBeUnique` filtering runs at dispatch time** (`PendingDispatch::shouldDispatch()`
  → `UniqueLock`, before any queue layer) — so it applies even under `Queue::fake()`, and
  a *dropped* dispatch never reaches `assertPushed`. Uniqueness is observable only against
  a real connection; with no worker, queued jobs stay in the `jobs` table and the unique
  lock stays held (exactly like a backlog).
- **`assertDatabaseCount()`/`assertDatabaseHas()` third parameter is a connection name,
  not a message** — passing a message throws `InvalidArgumentException` even on correct
  code. Use `assertSame($expected, DB::table('t')->count(), 'invariant message')` instead.
- **The HTTP kernel does not flush scoped instances between requests** — only the queue
  worker and Octane's `FlushTemporaryContainerInstances` listener do. To test Octane-style
  leakage fairly, call `$this->app->forgetScopedInstances()` between two requests in one
  test: scoped fixes then re-resolve and pass, singletons stay frozen and fail. Keep
  request-dependent state out of controller *constructors* — `Route::getController()` caches
  the controller instance across requests in a long-lived process, which freezes
  constructor-injected dependencies even under a per-request binding.
- **Probabilistic concurrency tests are NOT gate-eligible.** A race test (N parallel
  workers overselling a counter) is scheduling-dependent: it passed 3× locally and
  failed in CI. Verify atomicity deterministically instead — `CONFIG RESETSTAT` +
  `INFO commandstats` around a single call proves the decision ran server-side
  (cmdstat_eval present) vs client-side (bare GET + DECR).
- **Scenarios with external services (e.g. the Redis cluster) share state across the
  visible AND hidden suites in one phpunit process.** Hidden tests must clean up every
  key representation they assert on — including keys a *visible* test may have written
  (the visible suite runs first in the same process). Assert-absent checks are only safe
  after an explicit delete in setUp.
- **phpredis swallows Lua script errors as a `false` return** — a script that dies
  mid-way leaves its earlier commands applied (Redis does not roll back scripts) and does
  not throw. Never rely on an EVAL error path in a hidden test; assert on resulting key
  state instead. On clusters, an EVAL whose keys span slots is rejected server-side
  (CROSSSLOT) and DOES throw `RedisClusterException`.
- **App config files merge shallowly per top-level key** (with a hardcoded one-level
  whitelist like `database.connections`). A snapshot `config/database.php` containing
  only `redis.seeds` keeps the base `connections` (top-level `array_merge`) but replaces
  the whole base `redis` block — harmless when the scenario drives Redis through raw
  phpredis instead of the framework connection.
- **`RefreshDatabase` wraps each test in a transaction** — which swallows any manual
  transaction play and prevents `afterCommit` callbacks from firing. Use
  `DatabaseMigrations` in hidden tests that manipulate transactions or observe commit
  boundaries.
- **The array cache store implements real lock semantics within a process** — a lock
  acquired in one `handle()` call stays held for the next one, which is precisely a
  production lock leak as far as the invariant is concerned.
- **SQLite treats NULLs as distinct in UNIQUE indexes** — a composite
  `(email, deleted_at)` index does *not* enforce live-row uniqueness by itself; pair it
  with the validation rule. Distinct `deleted_at` values must be guaranteed with
  `travel()` when a scenario soft-deletes twice.
- **Nested transactions are savepoints** — a controller's `DB::transaction` inside the
  test's `beginTransaction` commits only at the outer boundary; the transaction manager
  fires `afterCommit` callbacks at the outermost commit.

If a hidden test would rely on any mechanic you cannot cite from source or docs, redesign
the scenario.

## Choosing families

Each scenario belongs to exactly one family (`family` field) but families may — and the
best ones do — *involve* several features. The flagship
(`webhook-payout-idempotency`) is an afterCommit scenario whose surface spans webhooks,
transactions, queues, locks, and an external provider. The family is what is graded; the
surface is what makes it real.

Families currently covered by scenarios: see the README index. Families with **recipes**
below have no scenarios yet; a new author should start there.

### Recipe: scheduler overlap (`console/scheduler-overlap`)

Invariant: long tasks on overlapping crons must hold `withoutOverlapping` mutexes with an
expiry that covers the task's worst-case duration, and `onOneServer` requires a shared
central cache. Defect candidate: `withoutOverlapping()` with default expiry (1440 min)
versus a task that legitimately retries for hours, or `onOneServer` against the local file
cache. Hidden test: contract-style — introspect the schedule via the `Schedule` facade
(`$event->expression`, `$event->withoutOverlapping`, `$event->expiresAt`) and assert the
mutex covers the declared worst case. Mark `difficulty: 3`.

### Recipe: failed jobs & backoff (`queues/failed-jobs`)

Invariant: `backoff()`/`$backoff` arrays must cover the retry count (Laravel reuses the
last value past the array end), `$tries`/`retryUntil()` must agree, and `failed()` must
not assume unloaded relations (models are re-fetched via `SerializesModels`). Defect
candidate: a 3-try job with `[1, 5]` backoff that slams a rate-limited provider, or a
`failed()` handler that refunds based on `$this->order->status` refetched *after* another
process already changed it. Hidden test: assert the backoff array length vs `$tries`
contract plus `failed()`'s behaviour under a stubbed model.

### Recipe: notifications × transactions (`notifications/after-commit`)

Same mechanism as the flagship but with `Notification::send` and database-channel
side effects. **Only author this if the observable difference from the flagship is the
notification channel's own delivery semantics** (e.g. `Notification::fake` recording
before commit vs after). Otherwise skip — duplication dilutes the benchmark.

### Recipe: filesystem visibility (`filesystem/visibility-contract`)

Invariant: code that assumes `Storage::url()` works on any disk breaks on non-public
disks, and `temporaryUrl` is unsupported on the local driver — production S3 vs CI local
is the same "works locally, fails in prod" shape as `env()` after cache. Hidden test:
contract-style — assert the code path resolves access through `Storage::` APIs and
explicitly declares its disk, never through hand-built URLs.

### Recipe: API-resource serialization (`serialization/api-resources`)

Invariant: `whenLoaded()` vs unconditional relation access — a resource that touches
`$this->relation` forces lazy loads in list responses (overlaps with
`lazy-loading-in-accessor`; make the *serialization contract* the invariant: key present
iff loaded, dates serialized through a single canonical format). Hidden test: assert the
response shape with and without the loaded relation, and strict-mode no-lazy-load.

### Recipe: Redis cluster (`cache/redis-cluster`)

Invariant: multi-key operations (`MGET`, `MULTI`, lock + read-modify-write on different
keys) must hash to the same slot (hash tags) — silently broken by default on cluster.
Requires `requires: ["redis-cluster"]` and CI services; **do not fake it with the array
store** (the slot behaviour is the invariant). Mark `difficulty: 5`, `status: draft` until
a cluster-capable CI run passes.

## Style rules for snapshot code

- Write the snapshot like a real PR: docblocks where the upstream skeleton has them, no
  filler comments, no comments that explain the defect ("// FIXME: maybe not idempotent?"
  is forbidden — the defect ships without a marker).
- The defect's file must read top-to-bottom like correct code. Nothing in the snapshot may
  apologize for it.
- Related code should be *correctly* written (the payout job has its lock and its
  idempotency guard; the controller has its signature check) — half-broken snapshots
  teach agents to fix noise instead of finding the invariant.
- Match the base skeleton's style: modern Laravel 11+ idioms (`casts(): array`, promoted
  properties, `Foundation\Queue\Queueable`), 4-space indent, single quotes.

## Regenerating patches

Never hand-write patch files. Edit `scripts/gen-all-patches.mjs` (or
`scripts/genpatch.mjs` for one-offs) and regenerate:

```bash
node scripts/gen-all-patches.mjs    # rewrites all solution + distractor patches
node bin/bench.mjs validate         # every patch must apply cleanly
```

Edits use `find`/`replace` strings that must occur **exactly once** — the generator fails
loudly otherwise, which is your guard against drift between the edit spec and the
snapshot.

## Difficulty calibration

The dominant rule is the *number of correct mental models* an agent needs, not the number
of touched files — an N+1 spread across an accessor and a controller is still one mental
model.

- **1–2:** the defect is on the main path of one file; the agent needs one correct mental
  model (N+1, missing `throw()`).
- **3:** the defect spans two files or two layers (config ↔ job, index ↔ validation rule),
  or the fix must be applied at two layers coherently.
- **4:** the defect is invisible unless the agent knows a specific framework mechanic
  (morph-map storage, `Queue::fake` blind spots, singleton lifetime, `env()` after
  config:cache).
- **5:** cross-process or cross-service semantics that require modelling worker/cluster
  behaviour (Redis cluster, scheduler mutexes) — reserve for scenarios with real service
  backing in CI.

## What reviewers reject

See [REVIEW-CHECKLIST.md](REVIEW-CHECKLIST.md) for the full list; the three that end
review fastest:

1. The `user_request` mentions anything that only exists in `intentional_defect` or
   `non_obvious_invariant` (keyword overlap check is part of review).
2. The hidden test would also fail on a legitimate alternative fix (the author must have
   run at least the reference fix and one alternative through the dynamic gate).
3. The scenario duplicates an existing scenario's graded mechanism under a different
   surface.

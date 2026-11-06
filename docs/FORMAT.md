# Scenario format specification

Version 1. Normative. The machine-checked schema lives at
[`schema/scenario.schema.json`](../schema/scenario.schema.json).

## Directory layout

```
scenarios/<area>/<scenario-id>/
├── scenario.json          manifest (below)
├── snapshot/              the Laravel app the agent receives (overlay over stubs/base)
│   ├── app/…
│   ├── routes/
│   ├── database/migrations/
│   ├── config/            only if the defect needs a config override
│   ├── resources/views/   only if needed
│   ├── phpunit.xml        only if the scenario needs non-default test env
│   ├── bootstrap/app.php  only if the scenario needs middleware/CSRF configuration
│   └── tests/             VISIBLE tests (must pass on the broken snapshot)
├── hidden/                hidden tests, mirrored into tests/ at grade time only
│   └── tests/Feature/
└── patches/
    ├── solution.patch     reference fix (generated with scripts/gen-all-patches.mjs)
    └── distractor-*.patch plausible-but-wrong patches
```

Rules:

- `<scenario-id>` is kebab-case and **must equal** the `id` field.
- `<area>` must equal the first segment of the `family` field.
- The snapshot is an **overlay**: a file path that exists in `stubs/base` replaces the
  base file at materialization (used for `routes/web.php`, `bootstrap/app.php`,
  `phpunit.xml`, `app/Models/User.php`, …).
- Scenarios **must not** add composer dependencies. The base ships `laravel/framework`,
  `phpunit`, and `fakerphp/faker` — nothing else.

## The manifest

```jsonc
{
  "schema": 1,
  "id": "webhook-payout-idempotency",
  "family": "cross-feature/idempotency",   // "<area>/<topic>"
  "title": "…",
  "difficulty": 4,                          // 1 (fresh eyes spot it) … 5 (requires deep mechanics)
  "status": "draft",                        // "validated" only after the dynamic gate passes in CI
  "laravel": ["^12.0", "^13.0"],            // subset of bench.json matrix constraints
  "requires": [],                           // [] for in-process scenarios; "redis" etc. need CI services

  // ── agent-facing (injected into PROMPT.md) ─────────────────────────────
  "business_context": "Who the app serves, what runs in production, team constraints.",
  "user_request": "The verbatim work request. Symptom-driven. MUST NOT name or hint
                   at the defect, its location, or the invariant.",
  "safe_fix_constraints": ["What a correct fix must respect / must not do.", "…"],

  // ── grading-facing (never shown to the agent) ───────────────────────────
  "intentional_defect": {
    "summary": "One sentence: what is wrong.",
    "location": ["app/Http/Controllers/PaymentWebhookController.php"],
    "root_cause": "The precise mechanism of the failure.",
    "why_it_looks_correct": "Why reviewers, linters and the visible suite accept it."
  },
  "non_obvious_invariant": "The property that must remain true and is invisible to the visible tests.",
  "expected_diagnosis": "What a competent engineer must identify before touching code.",
  "failure_explanation": "Concrete production consequences, ordered by severity, with trigger conditions.",
  "public_surface": {
    "routes": ["POST /webhooks/payments"],
    "classes": ["App\\Jobs\\ProcessPayout"],
    "config_keys": [],
    "tables": ["orders"],
    "artisan_commands": []
  },
  "artifacts": {
    "solution": "patches/solution.patch",
    "distractors": ["patches/distractor-a.patch", "patches/distractor-b.patch"]
  }
}
```

### Field semantics that reviewers check hardest

| Field | Hard requirements |
| --- | --- |
| `user_request` | Symptom-driven (an incident, a support ticket, a dashboard observation). Must be solvable without naming the mechanism. No words that only appear in the defect discussion ("afterCommit", "uniqueId", "morph map"…). |
| `non_obvious_invariant` | Stated as a *property*, not as a fix ("a job in the queue implies a committed event", not "use afterCommit()"). |
| `expected_diagnosis` | Names the mechanism the agent must understand — this is the *knowledge* being tested. |
| `public_surface` | **The freeze contract.** Hidden tests couple only to routes/classes/tables listed here. If a fix would break without renaming these, they must be listed. `App\…`/`Database\…` classes are verified to exist in the snapshot by the structural validator. |
| `safe_fix_constraints` | At least 2 constraints; at least one guards against over-broad fixes (e.g. "denying every request is not a fix"). |

## Test rules

**Visible tests** (`snapshot/tests/`):

- Must pass on the broken snapshot *and* on the reference fix.
- Should cover the happy path the user_request implies — enough to make the app feel
  "done", few enough that nothing red flags the defect.
- Must not assert query counts, lazy-loading strictness, queue-table state, or anything
  else that would expose the invariant.

**Hidden tests** (`hidden/tests/`):

- Must FAIL on the broken snapshot **for the invariant's reason** (the assertion message
  must explain the invariant — CI reviews these messages).
- Must PASS on the reference fix and on every legitimate alternative fix.
- Must FAIL on every distractor patch.
- Must be deterministic: sqlite `:memory:`, `array` cache/session/mail drivers, no network
  (`Http::fake`), no sleep, no time-dependence (use `travel()` when timestamps matter).
- Must not use `RefreshDatabase` when they manipulate transactions manually (the wrapper
  transaction breaks `afterCommit` observation) — use `DatabaseMigrations` there.
- `Queue::fake()` **does not honour afterCommit** (deferral lives in the real connection's
  `enqueueUsing()`). To observe enqueue ordering, use `QUEUE_CONNECTION=database` and
  assert against the `jobs` table.
- `ShouldBeUnique` filtering runs **at dispatch time**, before the queue layer — so it
  also applies under `Queue::fake()`, and a *dropped* dispatch never reaches
  `assertPushed` at all. Uniqueness is observable only against a real connection (with no
  worker, queued jobs stay in the `jobs` table and the lock stays held — exactly like a
  production backlog).
- `assertDatabaseCount()`/`assertDatabaseHas()` third parameter is a **connection name**,
  never a message. For self-documenting failures use `assertSame($expected,
  DB::table('t')->count(), 'invariant message')` or model-attribute assertions.
- The HTTP kernel does not flush scoped container instances between requests — feature
  tests cannot distinguish singleton from scoped lifetime on their own. Model Octane's
  `FlushTemporaryContainerInstances` listener explicitly: between two requests in one
  test, call `$this->app->forgetScopedInstances()`. Scoped bindings then re-resolve
  (correct fix passes) while singletons stay frozen (defect fails) — see
  `singleton-request-state`.

## Design rules for the defect

1. **One defect per scenario.** Everything else in the snapshot is correct, idiomatic
   code — including parts of the same pipeline (a lock with a `finally` release around
   the broken dispatch is fine and desirable: it keeps the story honest).
2. **The defect must be real Laravel semantics**, verified against the framework source
   or docs — not a stylistic preference. Record the mechanism in `root_cause`.
3. **It must fail in production, pass locally.** The best defects are masked by a
   difference between the test environment and production (process lifetime, config:cache,
   queue driver, at-least-once delivery, worker concurrency).
4. **No trivia.** If a doc lookup alone answers the scenario, it is not a scenario.
5. **No ambiguity traps.** The defect's behaviour must be wrong under any reading of the
   requirements, not just a design taste.

## Matrix rules

- `laravel` lists bench.json cells the scenario validates against. Scenarios must target
  **all currently supported cells** unless the defect is version-specific (then `status`
  stays `draft` with a note in `intentional_defect.root_cause`).
- The base skeleton is compatible with both cells; scenario code must be too. CI runs the
  dynamic gate on every declared cell.
- See docs/SUPPORT-MATRIX.md for the current support policy and cell definitions.

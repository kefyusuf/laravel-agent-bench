# laravel-agent-bench

A benchmark that evaluates coding agents on **real-world Laravel engineering** — the
class of mistakes that survive green tests, pass code review, and detonate in production:
a queued job dispatched inside an open transaction, a unique-job key that drops tenant
work silently, a rate limiter that shares one bucket across all visitors, a billing
gateway that charges with a sandbox key under `config:cache`.

This is **not** a quiz. Every scenario is a working Laravel application snapshot with a
single, plausible, deliberately subtle defect. The visible test suite is green; passing it
is easy. Grading runs a **hidden** test suite that encodes the non-obvious production
invariant the defect violates.

## How it works

```
                  ┌────────────────────────────────────────────────┐
 agent receives   │  workspace = base skeleton + scenario snapshot  │  + PROMPT.md
                  │  (visible tests included, hidden tests NOT)     │    (business context,
                  └────────────────────────────────────────────────┘     user request,
                                        │                                constraints)
                                 agent edits code
                                        │
                  ┌────────────────────────────────────────────────┐
 grading only     │  + hidden tests → full suite must stay green    │  + anti-cheat
                  └────────────────────────────────────────────────┘    (protected files)
```

Key properties:

- **Defects are invisible to the visible suite.** All visible tests pass on the broken
  snapshot — an agent that only "makes the tests pass" does nothing and fails.
- **Hidden tests encode invariants, not trivia.** They are deterministic, run in-process
  (sqlite / array drivers / database queue), and are validated to fail on the broken
  snapshot for the *right* reason.
- **Every scenario ships a reference fix and distractors** — plausible-but-wrong patches
  that keep the visible suite green but fail hidden tests. This is a mutation-style gate
  that keeps scenario quality from degrading (see docs/AUTHORING.md).
- **Version matrix.** Scenarios are validated against Laravel `^13.0` (active) and
  `^12.0` (security fixes) — see docs/SUPPORT-MATRIX.md.

## Repository layout

```
bench.json                  harness configuration (matrix cells, paths)
schema/                     normative scenario manifest schema
stubs/base/                 shared Laravel skeleton (11+/12/13 compatible)
scenarios/<area>/<id>/      one defect per directory
  scenario.json             manifest: context, defect, invariant, diagnosis, constraints
  snapshot/                 the app code the agent receives
  hidden/                   hidden tests, injected only at grade time
  patches/                  reference solution + distractor patches
src/, bin/                  zero-dependency Node harness (runs scenarios; never ships to agents)
docs/                       format spec, authoring guide, architecture, review checklist
.github/workflows/          CI: structural + dynamic (RED/GREEN/DISTRACTOR) gates
```

## Requirements

- **Harness:** Node.js ≥ 18.17 (no npm packages). Git is used for patching and diffs.
- **Scenario execution:** PHP ≥ 8.2, Composer ≥ 2. Scenarios run Laravel with the
  sqlite / array / database drivers — no Redis, no network, no external services.

**No PHP locally? Use Docker.** The bench runner image ships PHP 8.3, Composer 2,
Node 20 and git; the repo is bind-mounted and caches persist on the host:

```bash
docker compose build bench                                     # once
npm run docker:test                                            # harness tests in container
npm run docker:validate                                        # structural validation
npm run docker:vendor                                          # composer install per matrix cell
npm run docker:gate                                            # dynamic RED/GREEN/DISTRACTOR gate
# or pass any bench command directly:
docker compose run --rm bench node bin/bench.mjs validate --dynamic --cell laravel-13
```

A `redis` service is defined under the `redis` compose profile for future scenarios that
declare `requires: ["redis"]` (start with `docker compose --profile redis up -d redis`;
it is reachable from the bench container as host `redis`). The
`redis-cluster-crossslot` scenario needs the real 3-master cluster instead:
`docker compose --profile redis-cluster up -d --wait` before the dynamic gate. On native
(plain-PHP) runs, export `REDIS_SEEDS=127.0.0.1:16379,127.0.0.1:16380,127.0.0.1:16381`
(the profile publishes those ports) — CI does both automatically.

## Quickstart

```bash
# 0. structural validation (no PHP needed)
npm test                      # harness unit tests
node bin/bench.mjs validate   # all scenario manifests, inventories, patch applicability

# 1. build the vendor cache per matrix cell (requires PHP + Composer)
node bin/bench.mjs vendor:prepare

# 2. dynamic validation: the benchmark's own quality gate
#    broken→visible green/hidden red · +solution→all green · +distractor→hidden red
node bin/bench.mjs validate --dynamic

# 3. list scenarios, read the exact agent prompt
node bin/bench.mjs list
node bin/bench.mjs prompt --scenario webhook-payout-idempotency

# 4. run a grading session: give a workspace to an agent, then grade its diff
node bin/bench.mjs materialize --scenario webhook-payout-idempotency --out /tmp/ws
#   ... let your agent work on /tmp/ws (PROMPT.md is inside) ...
node bin/bench.mjs run --scenario webhook-payout-idempotency \
    --agent "your-agent --workspace {{workspace}} --prompt {{prompt}}" --keep

# or grade a patch directly (offline evaluation)
node bin/bench.mjs run --scenario webhook-payout-idempotency --patch fix.patch
```

Verdicts: `pass` · `fail-visible` · `fail-hidden` · `no-change` · `tampered` (modified
`tests/`, `phpunit.xml`, `composer.json`, `bootstrap/`, `vendor/`) · `agent-timeout` ·
`error` (ungradeable run, e.g. no parsed test summary) · `patch-not-applicable`.

## Running agents

The image ships the OpenAI Codex CLI so real agents can be graded in-container (grading
needs PHP; the agent needs its auth). Mount your codex auth and pass the agent command:

```bash
docker compose run --rm -v "$HOME/.codex:/root/.codex" bench \
  node bin/bench.mjs run --scenario webhook-payout-idempotency --cell laravel-13 \
  --agent 'codex exec -C {{workspace}} --dangerously-bypass-approvals-and-sandbox \
           --skip-git-repo-check --color never "$(cat {{prompt}})"'
```

Notes:

- `{{workspace}}` / `{{prompt}}` are substituted by the harness; the agent command runs
  through the container shell. Codex's own bubblewrap sandbox cannot run inside a
  container (nested user namespaces), which is why `--dangerously-bypass-approvals-and-sandbox`
  is used — the Docker container is the outer sandbox, and the harness's tamper checks
  and hidden tests are the graded boundary.
- Any agent works the same way: a command that receives the workspace and a PROMPT.md
  path and edits the workspace. Verdicts: `pass`, `fail-visible`, `fail-hidden`,
  `no-change`, `tampered`, `agent-timeout`, `error`.
- `--json --out <file>` writes the full result (agent output tail, patch, verdicts) for
  downstream scoring.
- Prefer a host-side agent? See `examples/codex-agent-host.mjs` (materialize → agent on
  host → `bench run --patch`).

### Baseline score

`examples/baseline-agent.mjs` is a deterministic, non-LLM pattern-matcher that knows only
two common gotchas (missing `->afterCommit()` on dispatch inside a transaction; unchecked
`Http` responses). Its score on laravel-13:

| Verdict | Count | Scenarios |
| --- | --- | --- |
| `pass` | 1 | webhook-payout-idempotency |
| `fail-hidden` | 1 | provider-error-as-success (partial fix: status checked, reference not — caught by hidden tests) |
| `no-change` | 12 | everything requiring mechanics the naive agent does not know |

A pattern-matching agent scores **1/14**, and its half-fix is caught by hidden tests —
the benchmark measures invariant reasoning, not doc lookup.

### First real-agent sweep (2026-11-06)

Codex CLI (default model, ChatGPT auth) on laravel-13, one run per scenario:
**15/15 `pass`** — including the difficulty-5 Redis-cluster scenario (the agent
reproduced the CROSSSLOT failure, applied the tenant hash tag and kept the Lua
script atomic; 10+ runs included the agent running PHPUnit itself; evidence:
`var/gate-reports/agent-run-codex-sweep.json`). Honest calibration note: single-
defect scenarios with full shell + test feedback **saturate for frontier agents** —
even real-infrastructure scenarios. The demonstrated discriminative value of this
benchmark is on the other end of the spectrum: the deterministic baseline scores
1/15, and its partial fix fails the hidden tests. Raising the ceiling for frontier
models (multi-defect repos, longer-horizon feature work under invariants,
concurrency-shaped invariants) is the project's open design question.

## Scenario index

| Scenario | Family | Difficulty |
| --- | --- | --- |
| `webhook-payout-idempotency` | cross-feature / idempotency | 4 |
| `retry-after-vs-timeout` | queues / worker semantics | 3 |
| `unique-job-tenant-key` | queues / unique jobs | 4 |
| `lock-leak-on-exception` | cache / lock handling | 3 |
| `lazy-loading-in-accessor` | eloquent / N+1 | 2 |
| `morph-map-hardcoded-type` | eloquent / polymorphism | 4 |
| `idor-nested-resource` | auth / authorization | 3 |
| `rate-limiter-missing-key` | auth / rate limiting | 3 |
| `runtime-env-after-cache` | config / env contract | 4 |
| `singleton-request-state` | octane / state leakage | 4 |
| `soft-delete-unique-email` | database / unique constraints | 3 |
| `provider-error-as-success` | http / provider responses | 3 |
| `mass-assignment-silent-drop` | validation / mass assignment | 3 |
| `signed-url-toggle-replay` | security / signed urls | 3 |
| `redis-cluster-crossslot` | cache / redis cluster | 5 |

Areas with planned scenarios (recipes in docs/AUTHORING.md): scheduler overlap, failed-job
backoff, notifications + transactions, filesystem visibility, API-resource serialization.

## Contributing

Start with **docs/FORMAT.md** (the manifest contract) and **docs/AUTHORING.md** (the
quality bar, including the mandatory RED/GREEN/DISTRACTOR gate and worked recipes for
families not yet represented). **docs/REVIEW-CHECKLIST.md** is what every PR is reviewed
against.

## License

MIT — see [LICENSE](LICENSE).

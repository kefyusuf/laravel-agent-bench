# Architecture

The system model of this repository: modules, dependency directions, invariants, and
risks. Read this before changing the harness or the format.

## Modules and dependency directions

```
bench.json ──────────────► src/config.mjs (paths, matrix, agent contract)
                              │
scenarios/* ──► src/scenarios.mjs (discovery, inventory)
                 src/schema.mjs  (manifest validation)
                 src/prompt.mjs  (PROMPT.md builder)          ◄── grading-field firewall
                 src/materialize.mjs (base+overlay → git workspace, tamper manifest)
                 src/phpunit.mjs (php/phpunit invocation, summary parsing)
                 src/validate.mjs (structural + dynamic RED/GREEN/DISTRACTOR gate)
                 src/grade.mjs   (agent run | patch apply → tamper check → visible → hidden)
                              │
stubs/base/ ──► materializer ─┴─► var/workspaces (ephemeral, gitignored)
scripts/gen-*.mjs ──► scenarios/*/patches/*.patch (generated artifacts, never hand-edited)
```

Dependency rules (all enforced by convention; keep them that way):

- `src/*` never imports scenario code. Scenarios are data.
- `scripts/*` may import `src/*`; nothing imports `scripts/*`.
- The **prompt firewall**: only `prompt.mjs` touches agent-facing fields, and it has a
  unit test (`test/prompt.test.mjs`) asserting grading fields never leak. Any new
  agent-facing file must pass through that test.
- Generated patches are build artifacts: editing a `.patch` file by hand is a bug; change
  the edit specs and regenerate.

## The two interpretation layers

A scenario workspace is produced from three inputs with clear precedence:

```
stubs/base (skeleton)  ─┐
                        ├─ overlay merge (scenario wins) → composer.json rendered per cell
scenario/snapshot/     ─┘                              → git init + baseline commit
```

- The **agent layer** is the materialized workspace minus `hidden/` — it contains
  everything an agent may see, including its own visible tests.
- The **grading layer** adds `hidden/` on top. Hidden tests are copied into `tests/` at
  grade time only; they never exist in an agent's workspace.
- A `.bench/manifest.json` in every workspace records SHA-256 hashes of protected paths
  at baseline. Protected paths come from `bench.json` (`grader.protectedPaths`):
  `tests/`, `phpunit.xml`, `composer.json`, `bootstrap/app.php`,
  `bootstrap/providers.php`, and `vendor/` (an agent could otherwise patch phpunit
  itself or a provider that `exit(0)`s before tests run). **The authoritative manifest
  is the in-memory copy** returned by `materializeWorkspace()`; the on-disk file is
  agent-writable and only a fallback — `tamperCheck` compares against the in-memory
  hashes, so rewriting the on-disk manifest cannot forge a pass. The check runs twice:
  after the agent finishes and again after the visible phpunit run (which executes
  agent code that could mutate protected paths at runtime).

## Invariants (what must remain true)

1. **Prompt firewall** — no grading field (`intentional_defect`, `non_obvious_invariant`,
   `expected_diagnosis`, `failure_explanation`, patches) reaches the agent workspace.
2. **Patch fidelity** — every `.patch` in `scenarios/*/patches/` is reproducible from the
   edit specs in `scripts/gen-all-patches.mjs` against the current snapshot; the
   structural validator applies them with `git apply --check` to enforce it.
3. **Gate correctness** — for every scenario and declared cell:
   broken→visible PASS/hidden FAIL, +solution→all PASS, +every distractor→visible
   PASS/hidden FAIL. This is the benchmark's own test suite; it is what makes scenario
   quality auditable rather than anecdotal.
4. **Determinism** — scenarios run in-process (sqlite/array/database drivers); no network,
   no sleeps, no wall-clock dependence.
5. **Surface freeze** — hidden tests couple only to `public_surface` (routes, classes,
   tables, config keys). Fix strategies that rename the surface are out of contract and
   `safe_fix_constraints` says so.
6. **Base/cell compatibility** — the base skeleton and every scenario snapshot must run
   under every matrix cell they declare (CI dynamic gate across the matrix).

## Execution environments

The harness runs anywhere with Node ≥ 18.17 and git; scenario execution additionally
needs PHP 8.2+ and Composer. Two supported ways to get them:

- **Docker (recommended):** `docker/Dockerfile` builds the bench runner (php 8.3-cli +
  Composer 2 + Node 20 + git, system-wide `safe.directory '*'` for bind mounts). The
  compose service bind-mounts the repo and shadows `var/` with a named volume — the
  vendor cache and per-run workspaces live on the container filesystem, because
  materializing a workspace copies ~12k vendor files and a Windows bind mount makes
  that minutes-slow. A `redis` service exists under the `redis` profile for future
  `requires: ["redis"]` scenarios. See the README quickstart.
- **Native:** PHP/Composer/Node on PATH; `bench vendor:prepare` then `bench validate
  --dynamic`. The CI workflow uses this path.

Grading accepts an agent command (`--agent`, with `{{workspace}}`/`{{prompt}}`
placeholders, run through the shell) or a pre-made patch (`--patch`), so offline
evaluation and harness self-tests share one code path.

## Risk map

| Risk | Severity | Mitigation | Residual |
| --- | --- | --- | --- |
| Scenario defect doesn't reproduce (authoring error) | high | dynamic gate in CI (RED/GREEN/DISTRACTOR) | gate is the mitigation; scenarios start `draft` until it runs |
| Hidden test fails a *legitimate* alternative fix | high | distractors + review step "run one alternative fix"; assertion messages state the invariant, not the mechanism | semi-subjective; human review required |
| Prompt leakage of grading fields | high | single prompt builder + leak unit test | low |
| Tampering (agent edits tests/config to pass) | medium | hash manifest, `tampered` verdict | agent could game business logic in unseen ways — inherent to benchmarking |
| Base skeleton drift from upstream Laravel | medium | skeleton mirrored from verified upstream files; dynamic gate catches breakage on every CI run | none beyond CI coverage |
| Patch drift after snapshot edits | medium | regenerate-from-spec workflow + `git apply --check` | low |
| Harness summary parsing breaks on phpunit output changes | low | JUnit-style robust regex + unparsed fallback flagged in reports | cosmetic |
| Windows/Unix path & CRLF differences | medium | `core.autocrlf=false` in workspaces; POSIX-style relative paths in inventory | dynamic gate runs on Linux CI only today |
| phpunit major-version XML incompatibility across cells | low | phpunit.xml mirrors upstream 12.x skeleton (validated compatible with phpunit 11 and 12) | watch future phpunit 13 |

## Grading protocol

```
run --scenario X (--agent CMD | --patch F):
  materialize agent workspace (visible only) + write PROMPT.md        [manifest kept in memory]
  → run agent (or apply patch)
  → tamper check #1 (in-memory manifest hashes)
  → extract patch (git diff from the stored baseline commit —
    agent-made commits are still captured; .bench/ and PROMPT.md excluded)
  → run phpunit (visible suite)          unparsed/zero-test summary ⇒ fail-visible
                                          (an exit(0) with no tests executed is not a pass)
  → tamper check #2 (visible run executed agent code; re-verify)
  → inject hidden tests
  → run phpunit (full suite)             unparsed/zero-test summary ⇒ fail-hidden; pass ⇒ pass
  → verdict + captured outputs written to the report
```

`no-change` is a fail: a scenario exists because the broken state is production-broken
even though its tests are green. Anti-cheat behaviour is covered by `test/grade.test.mjs`
(commit capture, manifest-rewrite detection, zero-test rejection, cleanup semantics) via
a fake phpunit binary — no PHP required.

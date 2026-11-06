# v0.1.0 — 2026-11-07

First tagged release of laravel-agent-bench: a benchmark evaluating coding agents on
real-world Laravel engineering — defects that pass visible tests but violate production
invariants.

## Contents

- **17 scenarios** across 12 families, all dynamically validated (RED/GREEN/DISTRACTOR
  gate, 8/6 steps green per cell) on both matrix cells (Laravel 13 × PHP 8.3/8.4,
  Laravel 12 × PHP 8.2/8.3):
  - 14 single-defect find-and-fix scenarios (queues, eloquent, auth, cache, config,
    octane, database, http, validation, security)
  - 1 multi-defect scenario (`digest-pipeline-defects`, 3 defects across 3 layers)
  - 1 build scenario (`idempotent-checkout`, feature work under a written spec)
  - 1 concurrency scenario (`reservation-race`, real parallel worker processes)
  - 1 real-infrastructure scenario (`redis-cluster-crossslot`, real 3-master cluster)
- **Harness** (zero-dependency Node): scenario materialization over a shared skeleton,
  prompt firewall, protected-file tamper detection, git-diff grading, structural +
  dynamic validation, `run` / `run-all` / `diagnose` commands.
- **Evaluation modes**: fix (`bench run`), sweep (`bench run-all`), diagnosis
  (`bench diagnose`, no-edit analysis scored against mechanism keywords).
- **Docker bench runner** with in-container agent support (Codex CLI bundled), compose
  profiles for single Redis and a real 3-master Redis cluster, CI workflow with the
  full dynamic-gate matrix.
- **Evidence**: committed gate reports and real-agent run reports under
  `var/gate-reports/`.

## Known results (2026-11-06/07)

| Agent | Score | Notes |
| --- | --- | --- |
| Deterministic baseline (pattern-matcher) | 1/17 pass, 1 fail-hidden | partial fixes are caught by hidden tests |
| Codex CLI (frontier, full tooling) | 17/17 run → 16/17 pass, 1 fail-hidden | the build scenario (spec adherence) is the only failure |

Calibration summary: find-and-fix scenarios saturate for frontier agents with full
tooling; spec-adherent feature work does not. Both ends are demonstrated with committed
evidence.

## Validation methodology

Every scenario passes its own mutation-style gate: broken snapshot → visible suite green
+ hidden suite red (for the invariant's reason); + reference solution → all green; + every
distractor (plausible-but-wrong fix) → visible green, hidden red. CI runs the gate on
every push across the PHP/Laravel matrix.

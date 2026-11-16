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

# v0.1.2 — 2026-11-07

- **Statistical protocol**: `bench run-all --runs N` — each scenario graded N times;
  score tables render pass-rates per scenario (`3/3`, `1/3`, `0/3`) with totals over
  all runs. Evidence files record the pinned model.
- **First repeated-runs frontier sweep**: codex (gpt-5.6-luna, reasoning medium),
  3 runs × 20 scenarios = 60 graded runs: **53/60 pass**. Consistent failures:
  idempotent-checkout (0/3, spec adherence) and double-refund (0/3, compensation
  broken by an over-engineered fix). Genuine variance surfaced on
  redis-cluster-crossslot (2/3).
- **Harness**: report command (+unit tests), `--model` pinning recorded in evidence,
  multi-run report cells.
# v0.1.3 — 2026-11-16

- **Private cohorts (held-out mechanism)**: every scenario-consuming command accepts
  `--scenarios-dir <path>` — operators evaluate against private scenario directories
  never published. Release-based comparability, rotation policy and the honest limits
  (no true held-out on a public repo) are documented in docs/EVALUATION-PROTOCOL.md.
- **New scenario**: backfill-pagination (eloquent/chunking, difficulty 4) — chunk()
  re-evaluates its WHERE while the loop stamps the filtered column; deterministically
  skips rows at every batch boundary. Solution: chunkById. Distractor: lazy() (same
  offset semantics). Validated 6/6 on both cells.
- **Diagnosis mode rollout**: diagnosis_keywords on 19/20 scenarios (multi-defect
  excluded by design); sample sweeps with judge recorded.
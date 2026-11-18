# Evaluation protocol

How to run comparable evaluations, and how this benchmark stays meaningful as an
agent's training data ages.

## Release-based comparability

Scores are only comparable **within the same tag**. The scenario set, hidden tests and
distractors change between releases; a 17/20 on v0.1.1 and a 18/20 on v0.1.2 are not
comparable numbers. Always record the tag (or commit) next to every score.

## Repeated runs

Single runs are anecdotal. The statistical protocol is `bench run-all --runs 3` (3 runs
per scenario) with the score table rendering pass-rates (`3/3`, `1/3`, `0/3`). Report
the pinned model id (`--model`) and the CLI version next to every table — evidence
files record them.

## Private cohorts (held-out scenarios)

Everything in `scenarios/` is public and may end up in an agent's training data. For
evaluations where that matters, the harness supports **operator-private scenario
directories**:

1. Keep a private clone of this repo (or a private directory) containing scenarios that
   are never published.
2. Point the harness at it — every scenario-consuming command accepts an override:

   ```bash
   docker compose run --rm bench \
     node bin/bench.mjs run-all --scenarios-dir /app/private-scenarios \
     --agent 'codex exec -C {{workspace}} ...'
   ```

   `validate`, `validate --dynamic`, `run` and `diagnose` accept the same flag.
3. The private scenarios use the same manifest schema, the same base skeleton and the
   same gate — nothing about the harness changes.

Rotation policy (recommended): publish each family first as a *scored public cohort*,
then author successors into the private cohort as the public set saturates. The
demonstrated saturation point for frontier agents is find-and-fix (17+ consecutive
passes); private cohorts should weight build and exactly-once invariant classes.

## Judge calibration (diagnosis mode)

The diagnosis judge grades semantically against the known root cause; its calibration
is measured the same way benchmark scores are — against ground truth:

- Ground truth per scenario is `expected_diagnosis` (authored with the manifest).
- Current sample (codex-as-judge, 11 scenarios, pre-registered threshold met):
  judge-human agreement **11/11**, keyword-human agreement **9/11**. Both keyword
  misses are over-credits — a keyword passed a diagnosis a human graded partial:
  `scheduler-overlap` (the agent used the right vocabulary but factually misread
  the current code) and `transaction-boundary` (correct root cause, but the
  proposed saga/outbox remedy violated the scenario's `safe_fix_constraints`).
  Keywords never under-credited a correct diagnosis in this sample. Full study:
  `var/gate-reports/judge-agreement-study.json` with per-case evidence files.
- Known judge limitation: it shares model variance with the diagnosed agent when the
  same CLI/model is used for both roles, and diagnose mode does not yet pin a model
  (`run-all` does).

## Model diversity (adding a second agent)

All published sweeps so far use one CLI (codex). Cross-model results are the
strongest antidote to both model-specific leakage and single-vendor saturation
claims, and adding an agent requires no harness changes — an agent is just a
command that receives a workspace and a prompt file:

1. Install the second CLI into the bench image (`docker/Dockerfile`, next to
   `@openai/codex`) and rebuild, or mount it at run time.
2. Mount that agent's auth the same way codex's is mounted
   (`-v "$HOME/.<agent>:/root/.<agent>"`).
3. Run with the same template, e.g. for Claude Code:

   ```bash
   docker compose run --rm -v "$HOME/.claude:/root/.claude" bench \
     node bin/bench.mjs run-all --runs 3 --model claude-code \
     --agent 'claude -p --dangerously-skip-permissions \
              --cwd {{workspace}} "$(cat {{prompt}})"'
   ```

4. Record the agent identity in evidence via `--model` (pinned and written into
   every run file) so score tables are attributable.

The gating rule is unchanged: evidence files are the audit trail; a sweep without
a committed `--out` report does not count.

## What we do not do

- No leaderboard. Public scores are self-reported runs; the gate evidence files make
  them auditable, but nothing stops an operator from misreporting. Trust requires the
  committed evidence files.
- No true held-out set in this repository — a public repository cannot hold data out
  from training. The private-cohort mechanism above is the honest substitute.

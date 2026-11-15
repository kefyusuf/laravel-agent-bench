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

## What we do not do

- No leaderboard. Public scores are self-reported runs; the gate evidence files make
  them auditable, but nothing stops an operator from misreporting. Trust requires the
  committed evidence files.
- No true held-out set in this repository — a public repository cannot hold data out
  from training. The private-cohort mechanism above is the honest substitute.

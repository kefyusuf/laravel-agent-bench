# Contributing

Thanks for considering a contribution. This benchmark holds agents to a high bar —
this document explains how to keep it there.

## The golden rule

**Every scenario must survive its own mutation-style gate.** A broken snapshot whose
visible tests pass, hidden tests that fail for the invariant's reason, a reference fix
that turns them green, and distractors (plausible-but-wrong patches) that stay visibly
green but fail hidden. CI runs this gate on every PR; a PR that flips any row fails.

Read in this order before opening a PR:

1. [docs/FORMAT.md](docs/FORMAT.md) — the manifest contract
2. [docs/AUTHORING.md](docs/AUTHORING.md) — the quality bar, determinism rules, recipes
3. [docs/REVIEW-CHECKLIST.md](docs/REVIEW-CHECKLIST.md) — what reviewers check

## Submitting a scenario

```bash
# author it under scenarios/<area>/<id>/ (see FORMAT.md), then:
node --test test/                                # harness tests
node bin/bench.mjs validate                      # structural (no PHP needed)
npm run docker:vendor                            # once, builds per-cell vendor caches
npm run docker:gate                              # dynamic gate, both matrix cells
```

Open the PR with:

- the dynamic-gate CI run attached (all declared cells green),
- **one alternative legitimate fix** demonstrated passing the hidden tests (guards
  against over-fitted hidden tests),
- the REVIEW-CHECKLIST items confirmed.

A scenario lands with `status: "validated"` only after the gate has passed on every
declared matrix cell.

## Submitting agent results

Agent evidence is data, not code — submit it as PRs adding
`var/gate-reports/agent-run-<agent>-<scenario>.json` files:

- run the agent yourself against a materialized workspace (see README "Running agents"),
- name files `agent-run-<agent-label>-<scenario>.json` (or use `bench run-all --out`),
- include the agent CLI version, the pinned model id, and the date in the PR description
  (`bench report` picks files up and regenerates the score table),
- do not hand-edit verdicts; rerun instead.

The score table is generated (`bench report`) — regenerated tables are committed
together with the evidence they summarize.

## Multi-defect, build, and concurrency scenarios

These classes have extra rules — read the dedicated sections in
[docs/AUTHORING.md](docs/AUTHORING.md) before authoring them:

- multi-defect: every defect gets its own hidden test; partial-fix distractors are the
  expected distractor shape;
- build: `task.feature_spec` must be precise enough that hidden tests are the contract,
  not the author's taste;
- concurrency: probabilistic race tests are not gate-eligible — verify atomicity
  deterministically (see the commandstats technique in AUTHORING.md).

## What gets rejected fastest

- a defect a linter or a doc lookup would catch (trivia),
- a `user_request` that leaks the mechanism (keyword-overlap check in review),
- hidden tests that a legitimate alternative fix would fail,
- probabilistic hidden tests,
- hand-written patches (regenerate from `scripts/gen-all-patches.mjs`).

## License

By contributing, you agree that your contributions are licensed under the MIT License.

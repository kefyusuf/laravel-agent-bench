# Scenario review checklist

Every scenario PR is reviewed against this list. Reviewers: work top to bottom; any
"NO" without a written disposition blocks the merge. PRs must include the CI run of the
dynamic gate (`validate --dynamic`) for every declared matrix cell.

## A. The defect

- [ ] Exactly **one** intentional defect; everything else in the snapshot is correct and idiomatic (including neighbouring code in the same pipeline).
- [ ] The defect's mechanism is real, current Laravel semantics — the manifest's `root_cause` names the mechanism and it can be cited from framework source or docs.
- [ ] `intentional_defect.why_it_looks_correct` is actually true: no linter rule, static analysis default, or obvious code smell flags it.
- [ ] The failure is production-severe and the trigger is realistic (at-least-once delivery, process lifetime, config:cache, worker concurrency, cross-process timing).
- [ ] The scenario does not duplicate the *graded mechanism* of an existing scenario under a different surface.

## B. The agent-facing contract

- [ ] `user_request` is symptom-driven; no keyword of the mechanism discussion (afterCommit, uniqueId, morph map, retry_after, …) appears anywhere agent-facing. Environment facts in `business_context` (queue driver, config:cache, Octane process model, naming a framework feature the app already uses) are acceptable — what is banned is naming the *fix* or stating the invariant in code terms.
- [ ] `business_context` gives the environment facts the agent needs (queue driver in prod vs tests, Octane, config:cache) without pointing at the defect.
- [ ] `safe_fix_constraints` (≥ 2) guard against over-broad fixes and forbid the degenerate "solution" (deny everything, disable the guard, delete the feature).
- [ ] `public_surface` is complete: every route/class/table/config key a hidden test touches is listed, and every listed `App\…` class exists in the snapshot.

## C. Tests

- [ ] Visible tests pass on the broken snapshot and on the reference fix (dynamic gate row 1).
- [ ] Hidden tests fail on the broken snapshot **for the invariant's reason** — the failing assertion is the one whose message states the invariant.
- [ ] Hidden tests pass on the reference fix **and on at least one alternative legitimate fix** (author demonstrates in the PR).
- [ ] Hidden assertion messages state the invariant, not the fix ("a job in the queue implies a committed event", not "use ->afterCommit()").
- [ ] Hidden tests are deterministic (no sleep, no wall clock; `travel()` used where timestamps matter; `Http::fake`; no network; no Redis).
- [ ] `RefreshDatabase` is NOT used in hidden tests that manipulate transactions manually (use `DatabaseMigrations`).
- [ ] No `Queue::fake()` in tests that observe afterCommit ordering or ShouldBeUnique uniqueness (both are bypassed by the fake).
- [ ] Visible tests don't assert anything that hints at the invariant (query counts, strict mode, queue-table state).

## D. Patches

- [ ] `patches/solution.patch` is the minimal coherent fix; it does not refactor unrelated code.
- [ ] ≥ 1 distractor mandatory (≥ 2 strongly recommended for difficulty ≥ 3); each keeps visible green and fails hidden (gate rows).
- [ ] All patches generated from edit specs (`scripts/gen-all-patches.mjs`), not hand-written.
- [ ] Every patch applies cleanly to a fresh materialization (`bench validate`).

## E. Manifest hygiene

- [ ] `scenario.json` passes structural validation (schema, inventory, patch applicability).
- [ ] `id` == directory name; `family` first segment == directory area; `laravel` lists supported cells.
- [ ] `difficulty` matches the calibration table in AUTHORING.md.
- [ ] `status` is `draft` until the dynamic gate has passed on every declared cell in CI; then `validated` with the run referenced in the PR.

## F. Docs

- [ ] README scenario index row added/updated.
- [ ] If the family is new: one-line note in AUTHORING.md (or a recipe if the family is only documented).

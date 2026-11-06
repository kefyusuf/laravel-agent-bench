import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { runGrade } from '../src/grade.mjs';
import { makeGradeFixture } from './helpers.grade-fixture.mjs';

let fx;

beforeEach(() => {
  fx = makeGradeFixture();
  process.env.BENCH_PHP = 'node';
});

afterEach(() => {
  delete process.env.BENCH_PHP;
  delete process.env.FAKE_FAIL;
  fx.cleanup();
});

test('patch mode: reference fix grades as pass', () => {
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    patchFile: path.join(fx.scenDir, 'patches', 'solution.patch'),
  });
  assert.equal(r.verdict, 'pass');
  assert.equal(r.visible.tests, 1);
  assert.equal(r.hidden.tests, 2);
});

test('patch mode: failing hidden suite grades as fail-hidden', () => {
  process.env.FAKE_FAIL = 'hidden';
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    patchFile: path.join(fx.scenDir, 'patches', 'solution.patch'),
  });
  assert.equal(r.verdict, 'fail-hidden');
  assert.equal(r.visible.pass, true);
  assert.equal(r.hidden.pass, false);
});

test('agent that commits its work still produces a diff (baseline-diff capture)', () => {
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    agentCmd: `node "${fx.agents.commit}" {{workspace}} {{prompt}}`,
  });
  assert.equal(r.verdict, 'pass');
  assert.ok(r.patch.includes('Thing.php'), 'diff must capture committed agent work');
  assert.ok(r.patch.includes('fixed'));
});

test('agent that does nothing grades as no-change', () => {
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    agentCmd: `node "${fx.agents.noop}" {{workspace}} {{prompt}}`,
  });
  assert.equal(r.verdict, 'no-change');
});

test('tampering with tests is detected even when the on-disk manifest is rewritten', () => {
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    agentCmd: `node "${fx.agents.tamper}" {{workspace}} {{prompt}}`,
  });
  assert.equal(r.verdict, 'tampered');
  assert.deepEqual(r.tamper, ['tests']);
});

test('zero-test exit(0) bypass is rejected as ungradeable', () => {
  const r = runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    agentCmd: `node "${fx.agents.plant}" {{workspace}} {{prompt}}`,
  });
  assert.equal(r.verdict, 'fail-visible');
  assert.match(r.visible.output, /did not execute a parsed, non-empty test summary/);
});

test('workspaces are cleaned up unless keep=true', () => {
  runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    patchFile: path.join(fx.scenDir, 'patches', 'solution.patch'),
  });
  assert.equal(readdirSync(fx.workspacesRoot).length, 0);

  runGrade({
    cfg: fx.cfg,
    scenario: fx.scenario,
    cell: fx.cell,
    workspacesRoot: fx.workspacesRoot,
    patchFile: path.join(fx.scenDir, 'patches', 'solution.patch'),
    keep: true,
  });
  const kept = readdirSync(fx.workspacesRoot);
  assert.equal(kept.length, 1);
  const ws = path.join(fx.workspacesRoot, kept[0]);
  assert.ok(existsSync(path.join(ws, 'PROMPT.md')));
  assert.ok(existsSync(path.join(ws, 'tests', 'HiddenTest.php')));
  assert.ok(existsSync(path.join(ws, '.bench', 'manifest.json')));
});

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { buildScoreTable } from '../src/report.mjs';

test('buildScoreTable aggregates array, sweep and single-run shapes', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'score-table-'));
  try {
    writeFileSync(path.join(dir, 'agent-run-codex-sweep.json'), JSON.stringify({
      agent: 'codex-cli',
      cell: 'laravel-13',
      summary: [{ scenario: 'alpha', verdict: 'pass' }, { scenario: 'beta', verdict: 'fail-hidden' }],
    }));
    writeFileSync(path.join(dir, 'agent-run-codex-gamma.json'), JSON.stringify([
      { scenario: 'gamma', verdict: 'pass' },
    ]));
    writeFileSync(path.join(dir, 'agent-run-baseline-alpha.json'), JSON.stringify({
      scenario: 'alpha', verdict: 'no-change',
    }));

    const { agents, markdown } = buildScoreTable(dir);

    assert.equal(agents.codex.alpha, 'pass');
    assert.equal(agents.codex.beta, 'fail-hidden');
    assert.equal(agents.codex.gamma, 'pass');
    assert.equal(agents.baseline.alpha, 'no-change');

    assert.ok(markdown.includes('| Scenario | baseline | codex |'));
    assert.ok(markdown.includes('| alpha | · no-change | ✅ pass |'));
    assert.ok(markdown.includes('| **pass total** | 0/1 | 2/3 |'));
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('buildScoreTable handles an empty directory', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'score-table-empty-'));
  try {
    const { markdown } = buildScoreTable(dir);
    assert.ok(markdown.includes('no agent evidence found'));
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

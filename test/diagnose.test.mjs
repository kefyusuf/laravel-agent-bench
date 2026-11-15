import { test } from 'node:test';
import { readFileSync } from 'node:fs';
import { buildJudgePrompt, parseJudgeVerdict, scoreDiagnosis } from '../src/prompt.mjs';
import assert from 'node:assert/strict';

const doc = { expected_diagnosis: 'Dispatches must be deferred with afterCommit so the queue only sees committed work.' };

test('buildJudgePrompt embeds ground truth and diagnosis, never the fix keywords list', () => {
  const p = buildJudgePrompt(doc, 'The dispatch runs before the transaction commits.');
  assert.ok(p.includes('known root cause'));
  assert.ok(p.includes('afterCommit so the queue only sees committed work'));
  assert.ok(p.includes('<diagnosis>'));
  assert.ok(p.includes('"verdict"'));
});

test('parseJudgeVerdict extracts the JSON verdict from chatty output', () => {
  const out = [
    'Thinking about the diagnosis...',
    'I will grade it now.',
    '{"verdict": "correct", "reasons": "Identifies the afterCommit deferral precisely."}',
    'Done grading.',
  ].join('\n');
  assert.deepEqual(parseJudgeVerdict(out), {
    verdict: 'correct',
    reasons: 'Identifies the afterCommit deferral precisely.',
  });
});

test('parseJudgeVerdict returns null when no verdict JSON is present', () => {
  assert.equal(parseJudgeVerdict('no json here'), null);
});

test('scoreDiagnosis OR-matches keywords inside the diagnosis tags', () => {
  const out = '<diagnosis>The dispatch is not deferred; use afterCommit.</diagnosis>';
  const s = scoreDiagnosis(out, ['aftercommit', 'queue worker']);
  assert.equal(s.pass, true);
  assert.deepEqual(s.matched, ['aftercommit']);
  assert.deepEqual(s.missing, ['queue worker']);
});

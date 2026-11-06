import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validateScenarioDocument, classToWorkspacePath } from '../src/schema.mjs';

const knownCells = ['^12.0', '^13.0'];

function validDoc() {
  return {
    schema: 1,
    id: 'some-scenario',
    family: 'queues/after-commit',
    title: 'A sufficiently long title',
    difficulty: 3,
    status: 'draft',
    laravel: ['^12.0', '^13.0'],
    requires: [],
    business_context: 'x'.repeat(80),
    user_request: 'x'.repeat(20),
    intentional_defect: {
      summary: 'x',
      location: ['app/Jobs/SomeJob.php'],
      root_cause: 'x',
      why_it_looks_correct: 'x',
    },
    non_obvious_invariant: 'x'.repeat(40),
    expected_diagnosis: 'x'.repeat(40),
    safe_fix_constraints: ['a', 'b'],
    failure_explanation: 'x'.repeat(80),
    public_surface: { routes: [], classes: [], tables: [] },
    artifacts: { solution: 'patches/solution.patch', distractors: ['patches/d1.patch'] },
  };
}

test('valid document produces no errors', () => {
  assert.deepEqual(validateScenarioDocument(validDoc(), { dirName: 'some-scenario', knownCells }), []);
});

test('id must equal directory name', () => {
  const errors = validateScenarioDocument(validDoc(), { dirName: 'other-name', knownCells });
  assert.ok(errors.some((e) => e.includes('directory name')));
});

test('unknown fields are rejected', () => {
  const doc = validDoc();
  doc.mystery = 1;
  const errors = validateScenarioDocument(doc, { dirName: 'some-scenario', knownCells });
  assert.ok(errors.some((e) => e.includes('mystery')));
});

test('laravel entries must reference known cells', () => {
  const doc = validDoc();
  doc.laravel = ['^11.0'];
  const errors = validateScenarioDocument(doc, { dirName: 'some-scenario', knownCells });
  assert.ok(errors.some((e) => e.includes('^11.0')));
});

test('missing required field is flagged', () => {
  const doc = validDoc();
  delete doc.non_obvious_invariant;
  const errors = validateScenarioDocument(doc, { dirName: 'some-scenario', knownCells });
  assert.ok(errors.some((e) => e.includes('non_obvious_invariant')));
});

test('classToWorkspacePath maps App and Database namespaces', () => {
  assert.equal(classToWorkspacePath('App\\Jobs\\Ship\\PackOrder').replaceAll('\\', '/'), 'app/Jobs/Ship/PackOrder.php');
  assert.equal(classToWorkspacePath('Database\\Factories\\UserFactory').replaceAll('\\', '/'), 'database/factories/UserFactory.php');
  assert.equal(classToWorkspacePath('Other\\Thing'), null);
});

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { buildPrompt } from '../src/prompt.mjs';

const doc = {
  business_context: 'A payments platform.',
  user_request: 'Make the payout flow robust.',
  safe_fix_constraints: ['No schema changes', 'Keep tests green'],
  public_surface: {
    routes: ['POST /webhooks/payments'],
    classes: ['App\\Jobs\\ProcessPayout'],
    config_keys: ['services.billing.secret'],
    tables: ['orders'],
    artisan_commands: ['app:settle-payouts'],
  },
  intentional_defect: { summary: 'SECRET-DEFECT-XYZ' },
  non_obvious_invariant: 'SECRET-INVARIANT-XYZ',
  expected_diagnosis: 'SECRET-DIAGNOSIS-XYZ',
  failure_explanation: 'SECRET-FAILURE-XYZ',
};

test('prompt contains agent-facing sections', () => {
  const p = buildPrompt(doc);
  assert.ok(p.includes('A payments platform.'));
  assert.ok(p.includes('Make the payout flow robust.'));
  assert.ok(p.includes('No schema changes'));
  assert.ok(p.includes('POST /webhooks/payments'));
  assert.ok(p.includes('App\\Jobs\\ProcessPayout'));
  assert.ok(p.includes('services.billing.secret'));
  assert.ok(p.includes('orders'));
  assert.ok(p.includes('app:settle-payouts'));
});

test('prompt never leaks grading fields', () => {
  const p = buildPrompt(doc);
  for (const secret of ['SECRET-DEFECT-XYZ', 'SECRET-INVARIANT-XYZ', 'SECRET-DIAGNOSIS-XYZ', 'SECRET-FAILURE-XYZ']) {
    assert.ok(!p.includes(secret), `prompt leaked ${secret}`);
  }
});

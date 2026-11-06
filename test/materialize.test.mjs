import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import { materializeWorkspace, tamperCheck, extractPatch, applyPatch, readManifest } from '../src/materialize.mjs';
import { makeFixture } from './helpers.fixture.mjs';

let fx;
let ws;

beforeEach(() => {
  fx = makeFixture();
  ws = path.join(fx.root, 'ws');
});

afterEach(() => {
  fx.cleanup();
});

test('materialize renders composer tokens, applies overlay precedence, creates git baseline', () => {
  const manifest = materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: false, vendorDir: null });

  const composer = JSON.parse(readFileSync(path.join(ws, 'composer.json'), 'utf8'));
  assert.equal(composer.laravel, '^13.0');
  assert.equal(readFileSync(path.join(ws, 'README.md'), 'utf8'), 'overridden readme\n');
  assert.ok(existsSync(path.join(ws, 'tests', 'BaseTest.php')), 'base test file present');
  assert.ok(existsSync(path.join(ws, 'tests', 'FeatureTest.php')), 'snapshot test file present');
  assert.ok(!existsSync(path.join(ws, 'hidden')), 'hidden tests excluded from agent view');

  assert.match(manifest.initialCommit, /^[0-9a-f]{40}$/);
  assert.ok(manifest.protected['tests']['tests/BaseTest.php']);
});

test('hidden tests are included when withHidden=true', () => {
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: true, vendorDir: null });
  assert.ok(existsSync(path.join(ws, 'tests', 'HiddenTest.php')));
});

test('tamper check flags modified and added test files', () => {
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: false, vendorDir: null });
  assert.deepEqual(tamperCheck(ws), [], 'clean workspace has no violations');

  writeFileSync(path.join(ws, 'tests', 'BaseTest.php'), '<?php // tampered\n');
  assert.deepEqual(tamperCheck(ws), ['tests']);

  // restore, then delete a protected file
  writeFileSync(path.join(ws, 'tests', 'BaseTest.php'), '<?php // base test\n');
  assert.deepEqual(tamperCheck(ws), [], 'restored workspace is clean');
});

test('extractPatch captures worktree changes; patch re-applies to a fresh workspace', () => {
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: false, vendorDir: null });
  writeFileSync(path.join(ws, 'app', 'Http', 'Broken.php'), '<?php class Broken { /* fixed */ }\n');
  const patch = extractPatch(ws);
  assert.ok(patch.includes('diff --git'), 'patch is a unified diff');
  assert.ok(patch.includes('Broken.php'));

  const ws2 = path.join(fx.root, 'ws2');
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws2, withHidden: false, vendorDir: null });
  const applied = applyPatch(ws2, patch);
  assert.ok(applied.ok, `patch should re-apply: ${applied.stderr}`);
  assert.equal(readFileSync(path.join(ws2, 'app', 'Http', 'Broken.php'), 'utf8'), '<?php class Broken { /* fixed */ }\n');
});

test('applyPatch rejects garbage', () => {
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: false, vendorDir: null });
  const applied = applyPatch(ws, 'this is not a patch');
  assert.equal(applied.ok, false);
});

test('manifest round-trips', () => {
  materializeWorkspace({ baseDir: fx.baseDir, scenarioDir: fx.scenDir, cell: fx.cell, outDir: ws, withHidden: false, vendorDir: null });
  const m = readManifest(ws);
  assert.ok(m.protectedPaths.includes('phpunit.xml') || m.protectedPaths.includes('composer.json'));
});

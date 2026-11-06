import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadConfig, cfgPath, cellById } from '../src/config.mjs';

test('loadConfig reads bench.json and tags root', () => {
  const cfg = loadConfig();
  assert.ok(cfg.matrix.length >= 1);
  assert.ok(cfg._root && cfg._root.length > 0);
});

test('cfgPath resolves relative to repo root', () => {
  const cfg = loadConfig();
  assert.ok(cfgPath(cfg, 'scenarios').includes('scenarios'));
});

test('cellById finds matrix cells', () => {
  const cfg = loadConfig();
  assert.equal(cellById(cfg, cfg.matrix[0].id), cfg.matrix[0]);
  assert.equal(cellById(cfg, 'does-not-exist'), null);
});

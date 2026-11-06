#!/usr/bin/env node
/**
 * Generate a patch for a scenario by materializing a workspace, applying find/replace
 * edits, and emitting `git diff` output. Guarantees syntactically valid, applicable diffs.
 *
 * Usage:
 *   node scripts/genpatch.mjs --scenario <id> --out <patch-name> --edits <edits.json> [--new-file <rel=path> ...]
 *
 * edits.json: [{ "file": "app/Http/Controller.php", "find": "...", "replace": "..." }, ...]
 * Each `find` must occur exactly once in the target file.
 */
import { readFileSync, writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { loadConfig, cfgPath } from '../src/config.mjs';
import { discoverScenarios } from '../src/scenarios.mjs';
import { materializeWorkspace } from '../src/materialize.mjs';

const args = { _: [] };
for (let i = 2; i < process.argv.length; i++) {
  const key = process.argv[i]?.replace(/^--/, '');
  if (key === 'new-file') {
    (args['new-file'] ??= []).push(process.argv[++i]);
  } else {
    args[key] = process.argv[++i] === undefined ? true : process.argv[i];
  }
}
if (!args.scenario || !args.out || !args.edits) {
  console.error('usage: genpatch --scenario <id> --out <name.patch> --edits <edits.json> [--new-file rel=path ...]');
  process.exit(2);
}

const cfg = loadConfig();
const scenario = discoverScenarios(cfgPath(cfg, 'scenarios')).find((s) => s.id === args.scenario);
if (!scenario) {
  console.error(`scenario "${args.scenario}" not found`);
  process.exit(2);
}

const cell = cfg.matrix[0];
const ws = path.join(cfg._root, cfg.paths.var, 'scratch', `genpatch-${scenario.id}`);
materializeWorkspace({
  baseDir: path.join(cfg._root, cfg.paths.base),
  scenarioDir: scenario.dir,
  cell,
  outDir: ws,
  withHidden: false,
  vendorDir: null,
});

const edits = JSON.parse(readFileSync(args.edits, 'utf8'));for (const edit of edits) {
  const abs = path.join(ws, edit.file);
  if (!existsSync(abs)) {
    console.error(`edit target missing: ${edit.file}`);
    process.exit(1);
  }
  const content = readFileSync(abs, 'utf8');
  const occurrences = content.split(edit.find).length - 1;
  if (occurrences !== 1) {
    console.error(`edit target "${edit.file}": find-string occurs ${occurrences} times (expected 1)`);
    process.exit(1);
  }
  writeFileSync(abs, content.replace(edit.find, edit.replace));
  console.error(`edited ${edit.file}`);
}

for (const spec of args['new-file'] ?? []) {
  const eq = spec.indexOf('=');
  if (eq === -1) {
    console.error(`--new-file expects rel=path, got: ${spec}`);
    process.exit(2);
  }
  const rel = spec.slice(0, eq);
  const dest = path.join(ws, rel);
  mkdirSync(path.dirname(dest), { recursive: true });
  writeFileSync(dest, readFileSync(spec.slice(eq + 1), 'utf8'));
  console.error(`added ${rel}`);
}

// intent-to-add so that brand-new files appear in `git diff` output
spawnSync('git', ['add', '-N', '.'], { cwd: ws });

const diff = spawnSync('git', ['diff', '--no-color', '--binary'], { cwd: ws, encoding: 'utf8' });
if (diff.status !== 0) {
  console.error(`git diff failed: ${diff.stderr}`);
  process.exit(1);
}
if (!diff.stdout.trim()) {
  console.error('no changes produced — refusing to write an empty patch');
  process.exit(1);
}

const dest = path.join(scenario.dir, 'patches', args.out);
mkdirSync(path.dirname(dest), { recursive: true });
writeFileSync(dest, diff.stdout);
console.error(`wrote ${dest}`);
rmSync(ws, { recursive: true, force: true });

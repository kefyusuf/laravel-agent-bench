import { mkdtempSync, mkdirSync, writeFileSync, rmSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

/**
 * Create a throwaway fixture exercising the full grade flow:
 * a fake "phpunit" (JS, run via BENCH_PHP=node), a tiny base stub, a scenario
 * with visible + hidden tests, and patch artifacts.
 */
export function makeGradeFixture() {
  const root = mkdtempSync(path.join(tmpdir(), 'bench-grade-'));

  // minimal base stub
  const baseDir = path.join(root, 'base');
  mkdirSync(path.join(baseDir, 'tests'), { recursive: true });
  mkdirSync(path.join(baseDir, 'bootstrap'), { recursive: true });
  mkdirSync(path.join(baseDir, 'vendor', 'phpunit', 'phpunit'), { recursive: true });
  writeFileSync(path.join(baseDir, 'composer.json.in'), '{"laravel":"%%LARAVEL%%","phpunit":"%%PHPUNIT%%","php":"%%PHP%%"}');
  writeFileSync(path.join(baseDir, '.gitignore'), '.bench/\nPROMPT.md\n');
  writeFileSync(path.join(baseDir, 'tests', 'BaseTest.php'), '<?php // base test\n');
  writeFileSync(path.join(baseDir, 'bootstrap', 'app.php'), '<?php // bootstrap\n');
  writeFileSync(path.join(baseDir, 'bootstrap', 'providers.php'), '<?php return [];\n');
  writeFileSync(
    path.join(baseDir, 'vendor', 'phpunit', 'phpunit', 'phpunit'),
    [
      "const fs = require('fs');",
      "const path = require('path');",
      "const ws = process.cwd();",
      "const hasHidden = fs.existsSync(path.join(ws, 'tests', 'HiddenTest.php'));",
      "if (fs.existsSync(path.join(ws, 'EMPTY_RUN'))) process.exit(0); // simulate a zero-test exit(0) bypass attempt",
      "if (process.env.FAKE_FAIL === 'hidden' && hasHidden) {",
      "  console.log('Tests: 2, Assertions: 2, Failures: 1.');",
      "  process.exit(1);",
      '}',
      "console.log(hasHidden ? 'OK (2 tests, 4 assertions)' : 'OK (1 test, 2 assertions)');",
      'process.exit(0);',
    ].join('\n'),
  );

  // scenario
  const scenDir = path.join(root, 'scenarios', 'demo', 'grade-scenario');
  mkdirSync(path.join(scenDir, 'snapshot', 'app'), { recursive: true });
  mkdirSync(path.join(scenDir, 'snapshot', 'tests'), { recursive: true });
  mkdirSync(path.join(scenDir, 'hidden', 'tests'), { recursive: true });
  mkdirSync(path.join(scenDir, 'patches'), { recursive: true });

  writeFileSync(path.join(scenDir, 'scenario.json'), JSON.stringify({
    schema: 1,
    id: 'grade-scenario',
    family: 'demo/none',
    title: 'Grade flow fixture scenario',
    difficulty: 1,
    status: 'draft',
    laravel: ['^13.0'],
    requires: [],
    business_context: 'x'.repeat(80),
    user_request: 'x'.repeat(20),
    intentional_defect: { summary: 'x', location: ['app/Thing.php'], root_cause: 'x', why_it_looks_correct: 'x' },
    non_obvious_invariant: 'x'.repeat(40),
    expected_diagnosis: 'x'.repeat(40),
    safe_fix_constraints: ['a', 'b'],
    failure_explanation: 'x'.repeat(80),
    public_surface: { routes: [], classes: ['App\\Thing'], tables: [] },
    artifacts: { solution: 'patches/solution.patch', distractors: ['patches/distractor.patch'] },
  }, null, 2));
  writeFileSync(path.join(scenDir, 'snapshot', 'app', 'Thing.php'), 'defect\n');
  writeFileSync(path.join(scenDir, 'snapshot', 'tests', 'VisibleTest.php'), '<?php // visible\n');
  writeFileSync(path.join(scenDir, 'hidden', 'tests', 'HiddenTest.php'), '<?php // hidden\n');

  const solution = [
    '--- a/app/Thing.php',
    '+++ b/app/Thing.php',
    '@@ -1,1 +1,1 @@',
    '-defect',
    '+fixed',
    '',
  ].join('\n');
  const distractor = [
    '--- a/app/Thing.php',
    '+++ b/app/Thing.php',
    '@@ -1,1 +1,1 @@',
    '-defect',
    '+plausible-but-wrong',
    '',
  ].join('\n');
  writeFileSync(path.join(scenDir, 'patches', 'solution.patch'), solution);
  writeFileSync(path.join(scenDir, 'patches', 'distractor.patch'), distractor);

  // fake agent scripts
  const agent = (name, code) => {
    const p = path.join(root, name);
    writeFileSync(p, code);
    return p;
  };
  agent('agent-noop.cjs', '');
  agent('agent-commit.cjs', [
    "const fs = require('fs');",
    "const path = require('path');",
    "const { execSync } = require('child_process');",
    'const ws = process.argv[2];',
    "fs.writeFileSync(path.join(ws, 'app', 'Thing.php'), 'fixed');",
    "execSync('git add -A && git commit -m wip --no-gpg-sign', { cwd: ws, stdio: 'ignore' });",
  ].join('\n'));
  agent('agent-tamper.cjs', [
    "const fs = require('fs');",
    "const crypto = require('crypto');",
    "const path = require('path');",
    'const ws = process.argv[2];',
    "const testFile = path.join(ws, 'tests', 'VisibleTest.php');",
    "fs.writeFileSync(testFile, 'tampered');",
    "const manifestPath = path.join(ws, '.bench', 'manifest.json');",
    'const manifest = JSON.parse(fs.readFileSync(manifestPath, "utf8"));',
    "// self-verify: recompute the hashes so a disk-read check would pass",
    "const rel = 'tests/VisibleTest.php';",
    "manifest.protected['tests'][rel] = crypto.createHash('sha256').update(fs.readFileSync(testFile)).digest('hex');",
    'fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));',
  ].join('\n'));
  agent('agent-plant.cjs', [
    "const fs = require('fs');",
    "const path = require('path');",
    'const ws = process.argv[2];',
    "fs.writeFileSync(path.join(ws, 'EMPTY_RUN'), 'x');",
  ].join('\n'));

  const cell = { id: 'fixture', laravel: '^13.0', php: '^8.3', phpunit: '^11.5', tier: 'active' };

  const cfg = {
    _root: root,
    paths: { base: 'base', var: 'var', scenarios: 'scenarios' },
    matrix: [cell],
    grader: { protectedPaths: ['composer.json', 'phpunit.xml', 'tests', 'bootstrap/app.php', 'bootstrap/providers.php', 'vendor'] },
    agent: { timeoutSeconds: 10 },
  };

  return {
    root,
    baseDir,
    scenDir,
    cell,
    cfg,
    workspacesRoot: path.join(root, 'var', 'workspaces'),
    scenario: { id: 'grade-scenario', dir: scenDir, familyDir: 'demo', doc: JSON.parse(readFileSync(path.join(scenDir, 'scenario.json'), 'utf8')) },
    agents: {
      noop: path.join(root, 'agent-noop.cjs'),
      commit: path.join(root, 'agent-commit.cjs'),
      tamper: path.join(root, 'agent-tamper.cjs'),
      plant: path.join(root, 'agent-plant.cjs'),
    },
    cleanup: () => rmSync(root, { recursive: true, force: true }),
  };
}

export function readJson(p) {
  return JSON.parse(readFileSync(p, 'utf8'));
}

export function exists(p) {
  return existsSync(p);
}

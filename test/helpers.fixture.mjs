import { mkdtempSync, mkdirSync, writeFileSync, rmSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

/** Create a throwaway workspace fixture exercising materialize/patches/tamper machinery. */
export function makeFixture() {
  const root = mkdtempSync(path.join(tmpdir(), 'bench-fixture-'));

  // minimal base stub
  const baseDir = path.join(root, 'base');
  mkdirSync(path.join(baseDir, 'tests'), { recursive: true });
  writeFileSync(path.join(baseDir, 'composer.json.in'), '{"laravel":"%%LARAVEL%%","phpunit":"%%PHPUNIT%%","php":"%%PHP%%"}');
  writeFileSync(path.join(baseDir, 'README.md'), 'base readme\n');
  writeFileSync(path.join(baseDir, 'tests', 'BaseTest.php'), '<?php // base test\n');
  writeFileSync(path.join(baseDir, '.gitignore'), '.bench/\nPROMPT.md\n');

  // scenario with an overlay that overrides base file
  const scenDir = path.join(root, 'scenarios', 'demo', 'demo-scenario');
  mkdirSync(path.join(scenDir, 'snapshot', 'app', 'Http'), { recursive: true });
  mkdirSync(path.join(scenDir, 'snapshot', 'tests'), { recursive: true });
  mkdirSync(path.join(scenDir, 'hidden', 'tests'), { recursive: true });
  mkdirSync(path.join(scenDir, 'patches'), { recursive: true });

  writeFileSync(path.join(scenDir, 'scenario.json'), JSON.stringify({
    schema: 1,
    id: 'demo-scenario',
    family: 'demo/none',
    title: 'Demo scenario for harness tests',
    difficulty: 1,
    status: 'draft',
    laravel: ['^13.0'],
    requires: [],
    business_context: 'x'.repeat(80),
    user_request: 'x'.repeat(20),
    intentional_defect: { summary: 'x', location: ['app/Http/Broken.php'], root_cause: 'x', why_it_looks_correct: 'x' },
    non_obvious_invariant: 'x'.repeat(40),
    expected_diagnosis: 'x'.repeat(40),
    safe_fix_constraints: ['a', 'b'],
    failure_explanation: 'x'.repeat(80),
    public_surface: { routes: [], classes: ['App\\Http\\Broken'], tables: [] },
    artifacts: { solution: 'patches/solution.patch', distractors: ['patches/distractor.patch'] },
  }, null, 2));
  writeFileSync(path.join(scenDir, 'snapshot', 'README.md'), 'overridden readme\n');
  writeFileSync(path.join(scenDir, 'snapshot', 'app', 'Http', 'Broken.php'), '<?php class Broken { /* defect */ }\n');
  writeFileSync(path.join(scenDir, 'snapshot', 'tests', 'FeatureTest.php'), '<?php // visible test\n');
  writeFileSync(path.join(scenDir, 'hidden', 'tests', 'HiddenTest.php'), '<?php // hidden test\n');

  const cell = { id: 'fixture', laravel: '^13.0', php: '^8.3', phpunit: '^11.5', tier: 'active' };

  // patches: solution appends a line; distractor appends a different marker
  const solution = [
    '--- a/app/Http/Broken.php',
    '+++ b/app/Http/Broken.php',
    '@@ -1,1 +1,2 @@',
    " <?php class Broken { /* defect */ }",
    '+// fixed',
    '',
  ].join('\n');
  const distractor = [
    '--- a/app/Http/Broken.php',
    '+++ b/app/Http/Broken.php',
    '@@ -1,1 +1,2 @@',
    " <?php class Broken { /* defect */ }",
    '+// plausible but wrong',
    '',
  ].join('\n');
  writeFileSync(path.join(scenDir, 'patches', 'solution.patch'), solution);
  writeFileSync(path.join(scenDir, 'patches', 'distractor.patch'), distractor);

  return { root, baseDir, scenDir, cell, solution, distractor, cleanup: () => rmSync(root, { recursive: true, force: true }) };
}

export function readJson(p) {
  return JSON.parse(readFileSync(p, 'utf8'));
}

export function exists(p) {
  return existsSync(p);
}

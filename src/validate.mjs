import { existsSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { hasHiddenTests, hasVisibleTests, listFilesRecursive } from './scenarios.mjs';
import { validateScenarioDocument, classToWorkspacePath } from './schema.mjs';
import { applyPatch, materializeWorkspace } from './materialize.mjs';
import { parseSummary, runPhpunit, tail } from './phpunit.mjs';
import { resolveVendorDir } from './grade.mjs';

/**
 * Structural validation — no PHP required.
 * Checks manifest schema, file inventory, public-surface classes, and (if git available) patch applicability.
 */
export function validateStructural(cfg, scenario) {
  const errors = [];
  const warnings = [];
  const doc = scenario.doc;

  if (scenario.parseError) {
    errors.push(`scenario.json is not valid JSON: ${scenario.parseError}`);
    return { ok: false, errors, warnings };
  }

  errors.push(...validateScenarioDocument(doc, {
    dirName: scenario.id,
    knownCells: cfg.matrix.map((c) => c.laravel),
  }));

  // family directory consistency
  if (doc?.family && doc.family.split('/')[0] !== scenario.familyDir) {
    errors.push(`family "${doc.family}" does not match directory segment "${scenario.familyDir}"`);
  }

  // inventory
  const snapshotDir = path.join(scenario.dir, 'snapshot');
  if (!existsSync(snapshotDir)) {
    errors.push('missing snapshot/ directory');
  } else if (listFilesRecursive(snapshotDir).length === 0) {
    errors.push('snapshot/ is empty');
  }
  if (!hasVisibleTests(scenario.dir)) errors.push('no visible tests found under snapshot/tests/');
  if (!hasHiddenTests(scenario.dir)) errors.push('no hidden tests found under hidden/');

  const artifacts = doc?.artifacts ?? {};
  const patchArtifacts = ['solution', ...(artifacts.distractors ?? [])]
    .map((key) => ({ key, rel: artifacts[key] }))
    .filter((a) => typeof a.rel === 'string');
  for (const { key, rel } of patchArtifacts) {
    const p = path.join(scenario.dir, rel);
    if (!existsSync(p)) errors.push(`missing patch artifact: ${key} (${rel})`);
  }

  // hidden tests must not ship inside the snapshot
  if (existsSync(snapshotDir)) {
    const snapshotFiles = listFilesRecursive(snapshotDir);
    for (const f of snapshotFiles) {
      if (f.startsWith('tests/') && f.includes('Hidden')) {
        warnings.push(`snapshot test looks like a hidden test: ${f}`);
      }
    }
  }

  // public surface classes must exist in the snapshot
  for (const fqcn of doc?.public_surface?.classes ?? []) {
    const rel = classToWorkspacePath(fqcn);
    if (rel === null) {
      warnings.push(`cannot map class "${fqcn}" to a workspace path; verify manually`);
      continue;
    }
    if (!existsSync(path.join(snapshotDir, rel))) {
      errors.push(`public_surface class "${fqcn}" maps to missing snapshot file "${rel}"`);
    }
  }

  // defect location files must exist
  for (const loc of doc?.intentional_defect?.location ?? []) {
    if (!existsSync(path.join(snapshotDir, loc))) {
      errors.push(`intentional_defect.location references missing snapshot file "${loc}"`);
    }
  }

  // patch applicability (git only)
  if (existsSync(snapshotDir)) {
    for (const { key, rel } of patchArtifacts) {
      const p = path.join(scenario.dir, rel);
      if (!existsSync(p)) continue;
      const check = checkPatchApplies(cfg, scenario, path.join(cfg._root, cfg.paths.var, 'scratch-check'), p);
      if (!check.ok) errors.push(`patch "${key}" does not apply cleanly: ${tail(check.stderr, 10)}`);
    }
  }

  return { ok: errors.length === 0, errors, warnings };
}

function checkPatchApplies(cfg, scenario, scratchRoot, patchPath) {
  const cell = cfg.matrix[0]; // applicability is version-independent (same overlay)
  const ws = path.join(scratchRoot, `${scenario.id}--${cell.id}--${process.pid}`);
  let materialized = false;
  try {
    materializeWorkspace({
      baseDir: path.join(cfg._root, cfg.paths.base),
      scenarioDir: scenario.dir,
      cell,
      outDir: ws,
      withHidden: false,
      vendorDir: null,
      protectedPaths: cfg.grader?.protectedPaths,
    });
    materialized = true;
    const text = readFileSync(patchPath, 'utf8');
    return applyPatch(ws, text, { checkOnly: true });
  } finally {
    if (materialized && existsSync(ws)) {
      rmSync(ws, { recursive: true, force: true });
    }
  }
}

/**
 * Dynamic validation (requires PHP + vendor cache): the benchmark's own quality gate.
 *   broken snapshot  -> visible PASS, hidden FAIL
 *   + solution patch -> visible PASS, hidden PASS
 *   + distractor i   -> visible PASS, hidden FAIL
 */
export function validateDynamic(cfg, scenario, cell, { workspacesRoot, keep = false } = {}) {
  const vendorDir = resolveVendorDir(cfg, cell);
  if (!vendorDir) {
    return { ok: false, fatal: `no vendor cache for cell "${cell.id}"; run \`bench vendor:prepare --cell ${cell.id}\`` };
  }
  const doc = scenario.doc;
  const baseDir = path.join(cfg._root, cfg.paths.base);
  const report = {
    scenario: scenario.id,
    cell: cell.id,
    ok: true,
    fatal: null,
    steps: [],
  };
  const ws = path.join(workspacesRoot, `val-${scenario.id}--${cell.id}--${process.pid}`);

  const mat = (withHidden) => {
    materializeWorkspace({
      baseDir,
      scenarioDir: scenario.dir,
      cell,
      outDir: ws,
      withHidden,
      vendorDir,
      protectedPaths: cfg.grader?.protectedPaths,
    });
  };
  // A suite only "ran" if the summary parsed and executed tests; a red step must
  // additionally report real failures (not a timeout or a crash before testing).
  const ranGreen = (r) => r.code === 0 && r.tests !== null && r.tests > 0;
  const ranRed = (r) => r.code !== 0 && r.tests !== null && r.tests > 0;
  const run = () => {
    const r = runPhpunit({ workspaceDir: ws });
    return { code: r.code, ...parseSummary(r), output: tail(`${r.stdout}\n${r.stderr}`) };
  };
  const apply = (rel) => {
    const text = readFileSync(path.join(scenario.dir, rel), 'utf8');
    const r = applyPatch(ws, text);
    if (!r.ok) {
      report.ok = false;
      report.fatal = `patch "${rel}" failed to apply during dynamic validation: ${tail(r.stderr, 10)}`;
      return false;
    }
    return true;
  };
  const step = (name, expectation, runResult) => {
    const pass = expectation(runResult);
    if (!pass) report.ok = false;
    // runResult also carries a `pass` (phpunit's own summary verdict); the step's
    // `pass` is the gate expectation, so spread runResult first and override.
    report.steps.push({ ...runResult, name, pass });
    return pass;
  };

  try {
    // 1. broken snapshot: visible green, hidden red
    mat(false);
    step('broken/visible-green', ranGreen, run());
    mat(true);
    step('broken/hidden-red', ranRed, run());

    // 2. solution: visible green, hidden green
    mat(false);
    if (apply(doc.artifacts.solution)) {
      step('solution/visible-green', ranGreen, run());
      mat(true);
      if (apply(doc.artifacts.solution)) {
        step('solution/hidden-green', ranGreen, run());
      }
    }

    // 3. distractors: visible green, hidden red
    for (const rel of doc.artifacts.distractors ?? []) {
      mat(false);
      if (apply(rel)) {
        step(`distractor:${rel}/visible-green`, ranGreen, run());
        mat(true);
        if (apply(rel)) {
          step(`distractor:${rel}/hidden-red`, ranRed, run());
        }
      }
    }
  } finally {
    if (!keep && existsSync(ws)) {
      rmSync(ws, { recursive: true, force: true });
    }
  }

  return report;
}

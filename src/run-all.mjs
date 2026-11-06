import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { discoverScenarios } from './scenarios.mjs';
import { runGrade, resolveVendorDir } from './grade.mjs';

/**
 * Run one agent command against every scenario on one matrix cell and aggregate.
 * Used for real benchmarking sweeps: `bench run-all --agent '<cmd>' [--cell <id>]`.
 * Returns { results, passed } and writes per-scenario JSON reports to outDir.
 */
export function runAll({ cfg, scenariosDir, agentCmd, cell, workspacesRoot, outDir, keep = false, only = null }) {
  const all = discoverScenarios(scenariosDir);
  const scenarios = only ? all.filter((s) => s.id === only) : all;
  mkdirSync(outDir, { recursive: true });

  const results = [];
  for (const scenario of scenarios) {
    if (scenario.doc && !scenario.doc.laravel.includes(cell.laravel)) continue;
    process.stdout.write(`running ${scenario.id} ...\n`);
    const r = runGrade({
      cfg,
      scenario,
      cell,
      workspacesRoot,
      agentCmd,
      keep,
    });
    results.push(r);
    writeFileSync(path.join(outDir, `${scenario.id}.json`), JSON.stringify(r, null, 2));
    process.stdout.write(`${r.verdict.toUpperCase()} ${scenario.id}\n`);
  }

  const passed = results.filter((r) => r.verdict === 'pass').length;
  return { results, passed };
}
